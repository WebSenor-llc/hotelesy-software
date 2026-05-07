<?php

namespace App\Services\Reservation;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\ReservationRoomNight;
use App\Models\RoomType;
use App\Services\NumberGeneratorService;
use App\Services\TaxCalculationService;
use App\Services\TenantContext;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates, modifies, and cancels reservations.
 *
 * All operations are transactional.
 * Inventory is locked at the daily_inventory level (row-level pessimistic lock
 * via AvailabilityService::reserve). Failure to acquire inventory => DB
 * rollback => no orphaned data.
 */
class ReservationService
{
    public function __construct(
        private AvailabilityService $availability,
        private NumberGeneratorService $numbers,
        private TaxCalculationService $tax,
        private TenantContext $context,
    ) {}

    /**
     * Required keys in $data:
     *   - property_id
     *   - guest_id (or guest_data array to create one)
     *   - arrival_date, departure_date (Y-m-d)
     *   - room_type_id
     *   - rate_plan_id (optional)
     *   - rooms_count (default 1)
     *   - adults, children
     *   - source_type (default 'direct')
     */
    public function create(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $property = Property::findOrFail($data['property_id']);
            $arrival = Carbon::parse($data['arrival_date']);
            $departure = Carbon::parse($data['departure_date']);
            $nights = $arrival->diffInDays($departure);

            if ($nights < 1) {
                throw new \InvalidArgumentException('Departure must be after arrival.');
            }

            $roomTypeId = $data['room_type_id'];
            $roomCount = (int) ($data['rooms_count'] ?? 1);

            $reserved = $this->availability->reserve($roomTypeId, $arrival, $departure, $roomCount);
            if (!$reserved) {
                throw new InventoryUnavailableException('Insufficient inventory for the selected dates.');
            }

            $guest = $this->resolveGuest($data);
            $ratePlan = isset($data['rate_plan_id']) ? RatePlan::find($data['rate_plan_id']) : null;
            $roomType = RoomType::findOrFail($roomTypeId);

            $nightlyRate = (float) ($data['nightly_rate']
                ?? $ratePlan?->computeRate((float) $roomType->base_rate)
                ?? $roomType->base_rate);

            $taxBreakdown = $this->tax->compute($property, $nightlyRate, 'room');
            $taxPerNight = (float) $taxBreakdown['total'];
            $roomRevenue = $nightlyRate * $nights * $roomCount;
            $totalTax = $taxPerNight * $nights * $roomCount;
            $totalAmount = $roomRevenue + $totalTax;

            $reservation = Reservation::create([
                'property_id' => $property->id,
                'reservation_number' => $this->numbers->reservationNumber($property),
                'guest_id' => $guest->id,
                'guest_name' => $guest->display_name,
                'guest_phone' => $guest->phone,
                'guest_email' => $guest->email,
                'company_id' => $data['company_id'] ?? null,
                'source_type' => $data['source_type'] ?? 'direct',
                'source_name' => $data['source_name'] ?? null,
                'ota_booking_id' => $data['ota_booking_id'] ?? null,
                'ota_channel_code' => $data['ota_channel_code'] ?? null,
                'arrival_date' => $arrival,
                'departure_date' => $departure,
                'arrival_time' => $data['arrival_time'] ?? $property->check_in_time,
                'departure_time' => $data['departure_time'] ?? $property->check_out_time,
                'nights' => $nights,
                'rooms_count' => $roomCount,
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'infants' => $data['infants'] ?? 0,
                'status' => $data['status'] ?? Reservation::STATUS_CONFIRMED,
                'status_changed_at' => now(),
                'status_changed_by' => auth()->id(),
                'room_revenue' => $roomRevenue,
                'total_tax' => $totalTax,
                'total_discount' => 0,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance_amount' => $totalAmount,
                'currency' => $property->currency,
                'advance_amount' => $data['advance_amount'] ?? 0,
                'advance_received' => ($data['advance_amount'] ?? 0) > 0,
                'special_requests' => $data['special_requests'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'is_vip' => $data['is_vip'] ?? false,
                'is_complimentary' => $data['is_complimentary'] ?? false,
                'is_house_use' => $data['is_house_use'] ?? false,
                'billing_to' => $data['billing_to'] ?? 'guest',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'device_id' => $data['device_id'] ?? request()->header('X-Device-Id'),
            ]);

            for ($i = 0; $i < $roomCount; $i++) {
                $resRoom = ReservationRoom::create([
                    'property_id' => $property->id,
                    'reservation_id' => $reservation->id,
                    'room_type_id' => $roomTypeId,
                    'rate_plan_id' => $ratePlan?->id,
                    'arrival_date' => $arrival,
                    'departure_date' => $departure,
                    'nights' => $nights,
                    'adults' => $data['adults'] ?? 1,
                    'children' => $data['children'] ?? 0,
                    'guest_name' => $guest->display_name,
                    'guest_id' => $guest->id,
                    'average_rate' => $nightlyRate,
                    'total_rate' => $nightlyRate * $nights,
                    'total_tax' => $taxPerNight * $nights,
                    'total_amount' => ($nightlyRate + $taxPerNight) * $nights,
                    'status' => 'booked',
                ]);

                foreach (CarbonPeriod::create($arrival, $departure->copy()->subDay()) as $night) {
                    ReservationRoomNight::create([
                        'property_id' => $property->id,
                        'reservation_id' => $reservation->id,
                        'reservation_room_id' => $resRoom->id,
                        'room_type_id' => $roomTypeId,
                        'rate_plan_id' => $ratePlan?->id,
                        'night_date' => $night,
                        'rate' => $nightlyRate,
                        'extra_adult_charge' => 0,
                        'extra_child_charge' => 0,
                        'extra_bed_charge' => 0,
                        'discount_amount' => 0,
                        'tax_amount' => $taxPerNight,
                        'net_amount' => $nightlyRate + $taxPerNight,
                    ]);
                }

                Folio::create([
                    'property_id' => $property->id,
                    'reservation_id' => $reservation->id,
                    'reservation_room_id' => $resRoom->id,
                    'folio_number' => $this->numbers->folioNumber($property),
                    'type' => 'guest',
                    'guest_id' => $guest->id,
                    'company_id' => $data['company_id'] ?? null,
                    'billing_name' => $data['billing_name'] ?? $guest->display_name,
                    'billing_address' => $data['billing_address'] ?? $guest->address,
                    'billing_gst' => $data['billing_gst'] ?? $guest->gst_number,
                    'currency' => $property->currency,
                    'status' => 'open',
                ]);
            }

            return $reservation->fresh(['rooms', 'folios', 'nights']);
        });
    }

    public function cancel(Reservation $reservation, ?string $reason = null, float $cancellationCharge = 0): Reservation
    {
        if (!$reservation->canCancel()) {
            throw new \DomainException("Cannot cancel reservation in status: {$reservation->status}");
        }

        return DB::transaction(function () use ($reservation, $reason, $cancellationCharge) {
            foreach ($reservation->rooms as $room) {
                $this->availability->release(
                    $room->room_type_id,
                    Carbon::parse($room->arrival_date),
                    Carbon::parse($room->departure_date),
                    1
                );
                $room->update(['status' => 'cancelled']);
            }

            $reservation->update([
                'status' => Reservation::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $reason,
                'cancellation_charge' => $cancellationCharge,
                'status_changed_at' => now(),
                'status_changed_by' => auth()->id(),
            ]);

            $reservation->folios()->where('status', 'open')->update([
                'status' => 'voided',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            return $reservation->fresh();
        });
    }

    public function markNoShow(Reservation $reservation, float $retentionCharge = 0): Reservation
    {
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            throw new \DomainException('Only confirmed reservations can be marked no-show.');
        }

        return DB::transaction(function () use ($reservation, $retentionCharge) {
            // Release future nights only; first-night charge typically retained
            foreach ($reservation->rooms as $room) {
                $arrival = Carbon::parse($room->arrival_date);
                $secondNight = $arrival->copy()->addDay();
                if (Carbon::parse($room->departure_date)->gt($secondNight)) {
                    $this->availability->release(
                        $room->room_type_id,
                        $secondNight,
                        Carbon::parse($room->departure_date),
                        1
                    );
                }
                $room->update(['status' => 'no_show']);
            }

            $reservation->update([
                'status' => Reservation::STATUS_NO_SHOW,
                'cancellation_charge' => $retentionCharge,
                'status_changed_at' => now(),
                'status_changed_by' => auth()->id(),
            ]);

            return $reservation->fresh();
        });
    }

    private function resolveGuest(array $data): Guest
    {
        if (isset($data['guest_id'])) {
            return Guest::findOrFail($data['guest_id']);
        }

        $guestData = $data['guest_data'] ?? null;
        if (!$guestData || empty($guestData['first_name'])) {
            throw new \InvalidArgumentException('guest_id or guest_data with first_name is required.');
        }

        if (!empty($guestData['phone'])) {
            $existing = Guest::where('phone', $guestData['phone'])->first();
            if ($existing) return $existing;
        }
        if (!empty($guestData['email'])) {
            $existing = Guest::where('email', $guestData['email'])->first();
            if ($existing) return $existing;
        }

        return Guest::create($guestData);
    }
}
