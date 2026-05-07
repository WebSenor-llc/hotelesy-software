<?php

namespace App\Services\Banquet;

use App\Models\Banquet\BanquetBooking;
use App\Models\Banquet\BanquetHall;
use App\Models\Banquet\BanquetPackage;
use App\Models\Property;
use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BanquetBookingService — handles event booking lifecycle.
 *
 * Pipeline: enquiry → tentative (held with tentative_until expiry) → confirmed →
 *           completed (post-event) | cancelled.
 *
 * Hall availability is enforced via BanquetHall::isAvailableOn() with time-window
 * checking. Two events on the same date in the same hall must not have overlapping
 * start/end times.
 *
 * Pricing computation:
 *  - hall_rent: based on duration tier (full_day / half_day / hourly)
 *  - food_amount: pax × per_pax_rate (if package selected) OR manual entry
 *  - beverage / decor / av / other: manual entry
 *  - GST split: 18% on services (hall, decor, av, other), 5% on food + bev (Restaurant Supply)
 */
class BanquetBookingService
{
    public function __construct(private readonly NumberGeneratorService $numbers) {}

    public function createEnquiry(Property $property, array $data): BanquetBooking
    {
        return DB::transaction(function () use ($property, $data) {
            $hall = BanquetHall::findOrFail($data['hall_id']);

            // Compute hall rent based on duration
            $hallRent = $this->computeHallRent(
                $hall,
                Carbon::parse($data['event_date'] . ' ' . $data['event_start_time']),
                Carbon::parse($data['event_date'] . ' ' . $data['event_end_time']),
            );

            // Food via package, if any
            $foodAmount = 0;
            $package = null;
            if (! empty($data['package_id'])) {
                $package = BanquetPackage::find($data['package_id']);
                if ($package) {
                    $foodAmount = (float) $package->per_pax_rate * (int) $data['expected_pax'];
                }
            }

            $booking = BanquetBooking::create([
                'property_id' => $property->id,
                'hall_id' => $hall->id,
                'package_id' => $package?->id,
                'booking_number' => $this->numbers->generate($property, 'banquet', 'BNQ/' . $property->code),
                'guest_id' => $data['guest_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'event_name' => $data['event_name'],
                'event_type' => $data['event_type'] ?? 'other',
                'event_date' => $data['event_date'],
                'event_start_time' => $data['event_start_time'],
                'event_end_time' => $data['event_end_time'],
                'expected_pax' => $data['expected_pax'],
                'hall_rent' => $hallRent,
                'food_amount' => $foodAmount,
                'beverage_amount' => $data['beverage_amount'] ?? 0,
                'decor_amount' => $data['decor_amount'] ?? 0,
                'av_amount' => $data['av_amount'] ?? 0,
                'other_amount' => $data['other_amount'] ?? 0,
                'menu_details' => $data['menu_details'] ?? null,
                'setup_notes' => $data['setup_notes'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
                'status' => BanquetBooking::STATUS_ENQUIRY,
                'foliosales_owner_id' => $data['sales_owner_id'] ?? auth()->id(),
                'created_by' => auth()->id(),
            ]);

            $booking->recomputeTotals();
            return $booking->fresh();
        });
    }

    public function moveTo(BanquetBooking $booking, string $targetStatus, array $data = []): BanquetBooking
    {
        $valid = [
            BanquetBooking::STATUS_ENQUIRY => [BanquetBooking::STATUS_TENTATIVE, BanquetBooking::STATUS_CANCELLED],
            BanquetBooking::STATUS_TENTATIVE => [BanquetBooking::STATUS_CONFIRMED, BanquetBooking::STATUS_CANCELLED],
            BanquetBooking::STATUS_CONFIRMED => [BanquetBooking::STATUS_COMPLETED, BanquetBooking::STATUS_CANCELLED],
            BanquetBooking::STATUS_COMPLETED => [],
            BanquetBooking::STATUS_CANCELLED => [],
        ];

        if (! in_array($targetStatus, $valid[$booking->status] ?? [], true)) {
            throw new \DomainException("Cannot transition from {$booking->status} to {$targetStatus}.");
        }

        // Hall availability re-check on confirmation
        if ($targetStatus === BanquetBooking::STATUS_CONFIRMED) {
            $available = $booking->hall->isAvailableOn(
                $booking->event_date,
                $booking->event_start_time,
                $booking->event_end_time
            );
            // The booking itself is the only existing one; allow self-overlap
            $conflicts = BanquetBooking::where('hall_id', $booking->hall_id)
                ->where('id', '!=', $booking->id)
                ->whereDate('event_date', $booking->event_date)
                ->whereIn('status', [BanquetBooking::STATUS_CONFIRMED, BanquetBooking::STATUS_TENTATIVE])
                ->where(function ($q) use ($booking) {
                    $q->whereBetween('event_start_time', [$booking->event_start_time, $booking->event_end_time])
                      ->orWhereBetween('event_end_time', [$booking->event_start_time, $booking->event_end_time])
                      ->orWhere(function ($qq) use ($booking) {
                          $qq->where('event_start_time', '<=', $booking->event_start_time)
                             ->where('event_end_time', '>=', $booking->event_end_time);
                      });
                })
                ->exists();
            if ($conflicts) {
                throw new \DomainException('Hall has a conflicting booking at the requested time.');
            }
        }

        $updates = [
            'status' => $targetStatus,
        ];

        if ($targetStatus === BanquetBooking::STATUS_COMPLETED && isset($data['actual_pax'])) {
            $updates['actual_pax'] = (int) $data['actual_pax'];
            // If actual differs from expected, re-cost the food portion
            if ($booking->package_id) {
                $package = BanquetPackage::find($booking->package_id);
                if ($package) {
                    $updates['food_amount'] = (float) $package->per_pax_rate * (int) $data['actual_pax'];
                }
            }
        }

        $booking->update($updates);
        $booking->recomputeTotals();

        return $booking->fresh();
    }

    public function recordAdvance(BanquetBooking $booking, float $amount): BanquetBooking
    {
        $booking->update([
            'advance_received' => round((float) $booking->advance_received + $amount, 2),
        ]);
        return $booking->fresh();
    }

    /**
     * Compute hall rent based on duration:
     *  - >= 8 hours: full day rate
     *  - 4-7 hours: half day rate
     *  - <4 hours: hourly rate × hours
     */
    private function computeHallRent(BanquetHall $hall, Carbon $start, Carbon $end): float
    {
        $hours = $start->diffInHours($end);
        if ($hours <= 0) return 0;

        if ($hours >= 8 && (float) $hall->full_day_rate > 0) {
            return (float) $hall->full_day_rate;
        }
        if ($hours >= 4 && (float) $hall->half_day_rate > 0) {
            return (float) $hall->half_day_rate;
        }
        return round((float) $hall->hourly_rate * $hours, 2);
    }
}
