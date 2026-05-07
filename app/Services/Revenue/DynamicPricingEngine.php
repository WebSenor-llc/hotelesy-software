<?php

namespace App\Services\Revenue;

use App\Models\DailyInventory;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Revenue\PricingRule;
use App\Models\RoomType;
use Carbon\Carbon;

/**
 * DynamicPricingEngine — computes the recommended rate for a given
 * (property × room_type × stay_date) by applying matching active rules to
 * the base rate, in priority order.
 *
 * Rule types supported:
 *   - occupancy_based: bump rate when current occupancy is above threshold
 *   - days_to_arrival: discount last-minute (or surge close-in dates)
 *   - day_of_week: weekend premium
 *   - season: high-season multiplier
 *   - event: surge for known event dates (manually tagged)
 *
 * Output: ['base' => 5000, 'recommended' => 6500, 'rules_applied' => [...]]
 */
class DynamicPricingEngine
{
    public function compute(Property $property, RoomType $roomType, Carbon $stayDate): array
    {
        $base = (float) ($roomType->base_rate ?? $roomType->default_rate ?? 0);
        $rate = $base;
        $applied = [];

        $rules = PricingRule::active()
            ->where('property_id', $property->id)
            ->where(function ($q) use ($roomType) {
                $q->whereNull('room_type_id')->orWhere('room_type_id', $roomType->id);
            })
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matches($rule, $property, $roomType, $stayDate)) continue;
            $before = $rate;
            $rate = $rule->applyTo($rate);
            $applied[] = [
                'id' => $rule->id,
                'name' => $rule->name,
                'type' => $rule->rule_type,
                'before' => $before,
                'after' => $rate,
                'delta' => round($rate - $before, 2),
            ];
        }

        return [
            'base' => $base,
            'recommended' => $rate,
            'rules_applied' => $applied,
        ];
    }

    private function matches(PricingRule $rule, Property $property, RoomType $roomType, Carbon $stayDate): bool
    {
        $cond = $rule->conditions ?? [];

        return match ($rule->rule_type) {
            PricingRule::TYPE_OCCUPANCY => $this->matchOccupancy($cond, $property, $stayDate),
            PricingRule::TYPE_DAYS_TO_ARRIVAL => $this->matchDaysToArrival($cond, $stayDate),
            PricingRule::TYPE_DAY_OF_WEEK => $this->matchDayOfWeek($cond, $stayDate),
            PricingRule::TYPE_SEASON => $this->matchSeason($cond, $stayDate),
            PricingRule::TYPE_EVENT => $this->matchEvent($cond, $stayDate),
            default => true,
        };
    }

    private function matchOccupancy(array $cond, Property $property, Carbon $stayDate): bool
    {
        $totalRooms = (int) DailyInventory::where('property_id', $property->id)
            ->whereDate('date', $stayDate)
            ->sum('total_rooms');
        $sold = (int) DailyInventory::where('property_id', $property->id)
            ->whereDate('date', $stayDate)
            ->sum('rooms_sold');

        if ($totalRooms === 0) return false;
        $occPct = ($sold / $totalRooms) * 100;

        $min = $cond['min_occ'] ?? 0;
        $max = $cond['max_occ'] ?? 100;
        return $occPct >= $min && $occPct <= $max;
    }

    private function matchDaysToArrival(array $cond, Carbon $stayDate): bool
    {
        $days = now()->startOfDay()->diffInDays($stayDate->startOfDay(), false);
        $min = $cond['min_days'] ?? 0;
        $max = $cond['max_days'] ?? PHP_INT_MAX;
        return $days >= $min && $days <= $max;
    }

    private function matchDayOfWeek(array $cond, Carbon $stayDate): bool
    {
        $allowed = $cond['days'] ?? []; // [0..6] where 0=Sunday
        return in_array($stayDate->dayOfWeek, array_map('intval', $allowed), true);
    }

    private function matchSeason(array $cond, Carbon $stayDate): bool
    {
        if (empty($cond['from']) || empty($cond['to'])) return false;
        $from = Carbon::parse($cond['from']);
        $to = Carbon::parse($cond['to']);
        return $stayDate->between($from, $to);
    }

    private function matchEvent(array $cond, Carbon $stayDate): bool
    {
        $dates = $cond['event_dates'] ?? [];
        return in_array($stayDate->toDateString(), $dates, true);
    }

    /**
     * Forecast booking pace based on historical pickup curves.
     * Simple v1: average occupancy on same day-of-week from prior 90 days.
     * Production: ARIMA / Prophet model.
     */
    public function forecastOccupancy(Property $property, Carbon $stayDate): float
    {
        $sameDow = collect(range(1, 12))->map(fn($w) => $stayDate->copy()->subWeeks($w))->all();
        $totalRooms = (int) RoomType::where('property_id', $property->id)
            ->where('is_active', true)
            ->sum('total_rooms');

        if ($totalRooms === 0) return 0.0;

        $occupancies = [];
        foreach ($sameDow as $date) {
            $sold = (int) Reservation::where('property_id', $property->id)
                ->where('arrival_date', '<=', $date)
                ->where('departure_date', '>', $date)
                ->whereNotIn('status', ['cancelled', 'no_show', 'voided'])
                ->sum('rooms_count');

            $occupancies[] = ($sold / $totalRooms) * 100;
        }

        return count($occupancies) > 0 ? round(array_sum($occupancies) / count($occupancies), 2) : 0.0;
    }
}
