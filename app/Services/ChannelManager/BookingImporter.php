<?php

namespace App\Services\ChannelManager;

use App\Models\ChannelMapping;
use App\Models\Guest;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\ChannelManager\DTOs\PulledBooking;
use App\Services\Reservation\InventoryUnavailableException;
use App\Services\Reservation\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BookingImporter — converts a normalised PulledBooking DTO from any channel
 * manager driver into Reservation records in the PMS.
 *
 * Three flows:
 *   - 'new' booking → create Reservation
 *   - 'modified' → cancel old + create new (simplest correct path; rate change handled here)
 *   - 'cancelled' → cancel existing Reservation
 *
 * Idempotency: keyed by (property + ota_booking_id). Re-importing same booking is a no-op.
 *
 * Race protection: row lock on existing Reservation matching ota_booking_id, so
 * concurrent CM pulls cannot double-process.
 */
class BookingImporter
{
    public function __construct(private readonly ReservationService $reservations) {}

    public function import(Property $property, PulledBooking $booking): ?Reservation
    {
        return DB::transaction(function () use ($property, $booking) {
            $existing = Reservation::where('property_id', $property->id)
                ->where('ota_booking_id', $booking->externalBookingId)
                ->lockForUpdate()
                ->first();

            return match ($booking->action) {
                'new' => $existing ?: $this->createNew($property, $booking),
                'modified' => $existing
                    ? $this->modifyExisting($property, $existing, $booking)
                    : $this->createNew($property, $booking),
                'cancelled' => $existing
                    ? $this->cancelExisting($existing, $booking)
                    : null,
                default => throw new \InvalidArgumentException("Unknown action: {$booking->action}"),
            };
        });
    }

    /**
     * Bulk import — used by the scheduled `cm:pull-bookings` command.
     * One failed booking does not abort the batch.
     */
    public function importBatch(Property $property, iterable $bookings): array
    {
        $stats = ['created' => 0, 'modified' => 0, 'cancelled' => 0, 'failed' => 0];

        foreach ($bookings as $booking) {
            try {
                $result = $this->import($property, $booking);
                if ($result === null) {
                    $stats['cancelled']++;
                } elseif ($result->wasRecentlyCreated) {
                    $stats['created']++;
                } else {
                    $stats['modified']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('BookingImporter failed', [
                    'property_id' => $property->id,
                    'ota_booking_id' => $booking->externalBookingId ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /* ------------------------------------------------------------------ */

    private function createNew(Property $property, PulledBooking $booking): Reservation
    {
        // Multi-room OTA bookings: PulledBooking->rooms is an array of room lines.
        // For Phase 1, we collapse multi-room bookings into the FIRST room line plus
        // a count. ReservationService::create handles single room_type bookings.
        // Multi-room-type OTA bookings (rare for AxisRooms) are handled by creating
        // multiple linked reservations.
        $firstRoom = $booking->rooms[0] ?? null;
        if (! $firstRoom) {
            throw new \DomainException("Booking {$booking->externalBookingId} has no room lines.");
        }

        $guest = $this->resolveOrCreateGuest($booking);
        $roomType = $this->resolveRoomType($property, $firstRoom);
        $ratePlan = $this->resolveRatePlan($property, $firstRoom);
        $nightsCount = $booking->arrivalDate->diff($booking->departureDate)->days;
        $nightlyRate = $nightsCount > 0
            ? round((float) ($firstRoom['rate'] ?? $booking->totalAmount) / $nightsCount, 2)
            : (float) ($firstRoom['rate'] ?? 0);

        $data = [
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'guest_name' => trim($booking->guestFirstName . ' ' . ($booking->guestLastName ?? '')),
            'guest_phone' => $booking->guestPhone,
            'guest_email' => $booking->guestEmail,
            'arrival_date' => $booking->arrivalDate->format('Y-m-d'),
            'departure_date' => $booking->departureDate->format('Y-m-d'),
            'adults' => $booking->adults,
            'children' => $booking->children,
            'room_type_id' => $roomType->id,
            'rate_plan_id' => $ratePlan?->id,
            'rooms_count' => (int) ($firstRoom['count'] ?? 1),
            'nightly_rate' => $nightlyRate,
            'source_type' => 'ota',
            'source_name' => $booking->channelName,
            'ota_booking_id' => $booking->externalBookingId,
            'ota_channel_code' => $booking->channelCode,
            'special_requests' => $booking->specialRequests,
            'status' => Reservation::STATUS_CONFIRMED,
            'device_id' => 'channel_manager:axisrooms',
        ];

        try {
            return $this->reservations->create($data);
        } catch (InventoryUnavailableException $e) {
            // OTA oversold us. Create as waitlist with a flag so revenue manager can intervene.
            $data['status'] = Reservation::STATUS_WAITLIST;
            $data['internal_notes'] = "[AUTO] OTA booking arrived but inventory short: {$e->getMessage()}";
            return $this->createUnchecked($property, $data);
        }
    }

    private function modifyExisting(Property $property, Reservation $reservation, PulledBooking $booking): Reservation
    {
        $datesChanged = $reservation->arrival_date->format('Y-m-d') !== $booking->arrivalDate->format('Y-m-d')
            || $reservation->departure_date->format('Y-m-d') !== $booking->departureDate->format('Y-m-d');
        $countChanged = $reservation->rooms_count !== ((int) ($booking->rooms[0]['count'] ?? 1));

        if ($datesChanged || $countChanged) {
            $this->reservations->cancel($reservation, 'OTA modification — re-importing as new');
            return $this->createNew($property, $booking);
        }

        // Light update — guest details, special requests
        $reservation->update([
            'guest_name' => trim($booking->guestFirstName . ' ' . ($booking->guestLastName ?? '')),
            'guest_phone' => $booking->guestPhone,
            'guest_email' => $booking->guestEmail,
            'special_requests' => $booking->specialRequests,
            'sync_version' => ($reservation->sync_version ?? 0) + 1,
            'synced_at' => now(),
        ]);

        return $reservation->fresh();
    }

    private function cancelExisting(Reservation $reservation, PulledBooking $booking): Reservation
    {
        if ($reservation->status === Reservation::STATUS_CANCELLED) {
            return $reservation;
        }
        return $this->reservations->cancel(
            $reservation,
            "Cancelled by OTA: {$booking->channelCode} ({$booking->externalBookingId})"
        );
    }

    private function resolveOrCreateGuest(PulledBooking $booking): Guest
    {
        if ($booking->guestEmail) {
            $g = Guest::where('email', $booking->guestEmail)->first();
            if ($g) return $g;
        }
        if ($booking->guestPhone) {
            $g = Guest::where('phone', $booking->guestPhone)->first();
            if ($g) return $g;
        }

        return Guest::create([
            'first_name' => $booking->guestFirstName,
            'last_name' => $booking->guestLastName,
            'email' => $booking->guestEmail,
            'phone' => $booking->guestPhone,
            'country' => $booking->guestCountry,
            'source' => 'channel_manager',
        ]);
    }

    private function resolveRoomType(Property $property, array $roomLine): RoomType
    {
        $extId = $roomLine['external_room_type_id'] ?? null;
        if ($extId) {
            $mapping = ChannelMapping::where('property_id', $property->id)
                ->where('entity_type', 'room_type')
                ->where('external_id', (string) $extId)
                ->first();
            if ($mapping && ($rt = RoomType::find($mapping->internal_id))) {
                return $rt;
            }
        }

        // Fallback by name
        if (! empty($roomLine['name'])) {
            $rt = RoomType::where('property_id', $property->id)
                ->where('name', 'like', '%' . $roomLine['name'] . '%')
                ->first();
            if ($rt) return $rt;
        }

        throw new \DomainException(
            "Cannot resolve room type for OTA booking. Map external_id={$extId} via channel_mappings."
        );
    }

    private function resolveRatePlan(Property $property, array $roomLine): ?RatePlan
    {
        $extId = $roomLine['external_rate_plan_id'] ?? null;
        if (! $extId) return null;

        $mapping = ChannelMapping::where('property_id', $property->id)
            ->where('entity_type', 'rate_plan')
            ->where('external_id', (string) $extId)
            ->first();

        return $mapping ? RatePlan::find($mapping->internal_id) : null;
    }

    private function createUnchecked(Property $property, array $data): Reservation
    {
        $data['reservation_number'] = app(\App\Services\NumberGeneratorService::class)
            ->reservationNumber($property);
        return Reservation::create($data);
    }
}
