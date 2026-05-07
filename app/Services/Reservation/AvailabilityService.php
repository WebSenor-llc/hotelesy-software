<?php

namespace App\Services\Reservation;

use App\Models\DailyInventory;
use App\Models\Property;
use App\Models\RoomType;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes room availability for a property, room type, and date range.
 *
 * The canonical inventory source is daily_inventory.
 * Available rooms = total - sold - blocked - held + overbooking_allowed.
 *
 * For a stay of N nights, MIN(available across all N nights) is what's bookable.
 */
class AvailabilityService
{
    /**
     * Returns int available rooms for a single (room type, date) tuple.
     */
    public function availableForDate(int $roomTypeId, Carbon $date): int
    {
        $row = DailyInventory::where('room_type_id', $roomTypeId)
            ->whereDate('date', $date)
            ->first();

        if (!$row) {
            // No inventory row => use room_type's total room count
            $type = RoomType::find($roomTypeId);
            return $type?->rooms()->count() ?? 0;
        }

        return $row->availableRooms();
    }

    /**
     * Returns the minimum available rooms across the stay date range.
     * Date range: [arrival, departure) — departure date is NOT a stay night.
     */
    public function availableForStay(int $roomTypeId, Carbon $arrival, Carbon $departure): int
    {
        $period = CarbonPeriod::create($arrival, $departure->copy()->subDay());
        $min = PHP_INT_MAX;

        foreach ($period as $night) {
            $available = $this->availableForDate($roomTypeId, $night);
            if ($available < $min) {
                $min = $available;
            }
        }

        return $min === PHP_INT_MAX ? 0 : $min;
    }

    /**
     * Get a full availability grid for a property over a date range.
     * Returns: Collection<roomTypeId => Collection<dateString => availableInt>>
     */
    public function gridForProperty(Property $property, Carbon $from, Carbon $to): Collection
    {
        $period = CarbonPeriod::create($from, $to);
        $roomTypeIds = $property->roomTypes()->where('is_active', true)->pluck('id');

        $rows = DailyInventory::whereIn('room_type_id', $roomTypeIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('room_type_id');

        $grid = collect();
        foreach ($roomTypeIds as $rtId) {
            $byDate = collect();
            $rtRows = $rows->get($rtId, collect())->keyBy(fn($r) => $r->date->toDateString());
            foreach ($period as $night) {
                $key = $night->toDateString();
                $row = $rtRows->get($key);
                $byDate->put($key, $row?->availableRooms() ?? 0);
            }
            $grid->put($rtId, $byDate);
        }

        return $grid;
    }

    /**
     * Atomically reserve N rooms for the night range. Inside a DB transaction
     * with row-level locks on daily_inventory rows.
     *
     * Returns true if succeeded, false if insufficient inventory.
     */
    public function reserve(int $roomTypeId, Carbon $arrival, Carbon $departure, int $count = 1): bool
    {
        $period = CarbonPeriod::create($arrival, $departure->copy()->subDay());

        return \DB::transaction(function () use ($period, $roomTypeId, $count) {
            // Lock all relevant inventory rows
            foreach ($period as $night) {
                $row = DailyInventory::where('room_type_id', $roomTypeId)
                    ->whereDate('date', $night)
                    ->lockForUpdate()
                    ->first();

                if (!$row) {
                    $rt = RoomType::find($roomTypeId);
                    if (!$rt) return false;

                    $row = DailyInventory::create([
                        'tenant_id' => $rt->tenant_id,
                        'property_id' => $rt->property_id,
                        'room_type_id' => $roomTypeId,
                        'date' => $night,
                        'total_rooms' => $rt->rooms()->count(),
                        'rooms_sold' => 0,
                    ]);
                }

                if ($row->availableRooms() < $count) {
                    return false;
                }
            }

            // All clear - increment sold
            foreach ($period as $night) {
                DailyInventory::where('room_type_id', $roomTypeId)
                    ->whereDate('date', $night)
                    ->increment('rooms_sold', $count);
            }

            return true;
        });
    }

    /**
     * Release rooms back to inventory (cancellation / no-show).
     */
    public function release(int $roomTypeId, Carbon $arrival, Carbon $departure, int $count = 1): void
    {
        $period = CarbonPeriod::create($arrival, $departure->copy()->subDay());

        \DB::transaction(function () use ($period, $roomTypeId, $count) {
            foreach ($period as $night) {
                DailyInventory::where('room_type_id', $roomTypeId)
                    ->whereDate('date', $night)
                    ->where('rooms_sold', '>', 0)
                    ->decrement('rooms_sold', $count);
            }
        });
    }
}
