<?php

namespace App\Services\ChannelManager;

use App\Models\ChannelMapping;
use App\Models\DailyInventory;
use App\Models\DailyRate;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\ChannelManager\DTOs\InventoryUpdate;
use App\Services\ChannelManager\DTOs\RateUpdate;
use App\Services\ChannelManager\DTOs\RestrictionUpdate;
use App\Services\ChannelManager\DTOs\SyncResult;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * InventorySyncService — orchestrates outbound channel-manager pushes.
 *
 * Three operations:
 *   - syncInventory(property, dateRange) — pushes available rooms by date × room type
 *   - syncRates(property, dateRange)     — pushes daily_rates by date × rate plan
 *   - syncRestrictions(property, dateRange) — stop-sell, CTA, CTD, MLOS
 *
 * Wire format delegated to ChannelManagerDriver implementation.
 *
 * Typical use:
 *  - Scheduled command `cm:push-inventory` runs every 30 min for next 90 days
 *  - Event-driven sync fires immediately after reservation create/cancel for
 *    just the affected dates (Phase 2)
 */
class InventorySyncService
{
    public function __construct() {}

    public function syncInventory(Property $property, Carbon $from, Carbon $to): SyncResult
    {
        $driver = ChannelManagerFactory::forProperty($property);
        $period = CarbonPeriod::create($from, $to);
        $dates = collect($period)->map(fn(Carbon $d) => $d->toDateString())->all();

        $inventory = DailyInventory::where('property_id', $property->id)
            ->whereIn('date', $dates)
            ->get();

        $updates = collect();
        foreach ($inventory as $row) {
            $externalId = $this->mapRoomTypeId($property, $row->room_type_id);
            if (! $externalId) continue;

            $available = max(0, $row->total_rooms - $row->rooms_sold - $row->rooms_blocked + $row->overbooking_allowed);

            $updates->push(new InventoryUpdate(
                roomTypeId: $row->room_type_id,
                externalRoomTypeId: $externalId,
                date: Carbon::parse($row->date),
                availableRooms: $available,
                stopSell: (bool) $row->stop_sell,
            ));
        }

        if ($updates->isEmpty()) {
            return SyncResult::success(0, [], ['message' => 'No mapped inventory to sync.']);
        }

        $result = $driver->pushInventory($property, $updates);

        if ($result->success) {
            DailyInventory::where('property_id', $property->id)
                ->whereIn('date', $dates)
                ->update(['last_pushed_to_channels_at' => now()]);
        }

        return $result;
    }

    public function syncRates(Property $property, Carbon $from, Carbon $to): SyncResult
    {
        $driver = ChannelManagerFactory::forProperty($property);
        $period = CarbonPeriod::create($from, $to);
        $dates = collect($period)->map(fn(Carbon $d) => $d->toDateString())->all();

        $rates = DailyRate::where('property_id', $property->id)
            ->whereIn('date', $dates)
            ->get();

        $updates = collect();
        foreach ($rates as $row) {
            $externalRoomTypeId = $this->mapRoomTypeId($property, $row->room_type_id);
            $externalRatePlanId = $this->mapRatePlanId($property, $row->rate_plan_id);
            if (! $externalRoomTypeId || ! $externalRatePlanId) continue;

            $updates->push(new RateUpdate(
                roomTypeId: $row->room_type_id,
                ratePlanId: $row->rate_plan_id,
                externalRoomTypeId: $externalRoomTypeId,
                externalRatePlanId: $externalRatePlanId,
                date: Carbon::parse($row->date),
                singleRate: (float) ($row->single_rate ?? $row->rate ?? 0),
                doubleRate: (float) ($row->double_rate ?? $row->rate ?? 0),
                extraAdultRate: $row->extra_adult_rate ? (float) $row->extra_adult_rate : null,
                extraChildRate: $row->extra_child_rate ? (float) $row->extra_child_rate : null,
                currency: $property->currency ?? 'INR',
            ));
        }

        if ($updates->isEmpty()) {
            return SyncResult::success(0, [], ['message' => 'No mapped rates to sync.']);
        }

        return $driver->pushRates($property, $updates);
    }

    public function syncRestrictions(Property $property, Carbon $from, Carbon $to): SyncResult
    {
        $driver = ChannelManagerFactory::forProperty($property);
        $period = CarbonPeriod::create($from, $to);
        $dates = collect($period)->map(fn(Carbon $d) => $d->toDateString())->all();

        $rates = DailyRate::where('property_id', $property->id)
            ->whereIn('date', $dates)
            ->where(function ($q) {
                $q->where('stop_sell', true)
                    ->orWhereNotNull('cta')
                    ->orWhereNotNull('ctd')
                    ->orWhereNotNull('min_stay');
            })
            ->get();

        $updates = collect();
        foreach ($rates as $row) {
            $externalRoomTypeId = $this->mapRoomTypeId($property, $row->room_type_id);
            $externalRatePlanId = $this->mapRatePlanId($property, $row->rate_plan_id);
            if (! $externalRoomTypeId) continue;

            $updates->push(new RestrictionUpdate(
                roomTypeId: $row->room_type_id,
                ratePlanId: $row->rate_plan_id,
                externalRoomTypeId: $externalRoomTypeId,
                externalRatePlanId: $externalRatePlanId,
                date: Carbon::parse($row->date),
                minStay: $row->min_stay ? (int) $row->min_stay : null,
                maxStay: $row->max_stay ? (int) $row->max_stay : null,
                closedToArrival: (bool) ($row->cta ?? false),
                closedToDeparture: (bool) ($row->ctd ?? false),
                stopSell: (bool) $row->stop_sell,
            ));
        }

        if ($updates->isEmpty()) {
            return SyncResult::success(0, [], ['message' => 'No restrictions to sync.']);
        }

        return $driver->pushRestrictions($property, $updates);
    }

    /**
     * Pull bookings + import + acknowledge.
     */
    public function pullAndImport(Property $property, ?\DateTimeInterface $since = null): array
    {
        $driver = ChannelManagerFactory::forProperty($property);
        $bookings = $driver->pullBookings($property, $since);

        $importer = app(BookingImporter::class);
        $stats = $importer->importBatch($property, $bookings);

        foreach ($bookings as $booking) {
            try {
                $driver->acknowledgeBooking($property, $booking->externalBookingId);
            } catch (\Throwable $e) {
                \Log::warning('CM acknowledge failed', [
                    'property_id' => $property->id,
                    'booking_id' => $booking->externalBookingId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /* ---- mapping helpers ---- */

    private function mapRoomTypeId(Property $property, int $internalId): ?string
    {
        return ChannelMapping::where('property_id', $property->id)
            ->where('entity_type', 'room_type')
            ->where('internal_id', $internalId)
            ->value('external_id');
    }

    private function mapRatePlanId(Property $property, ?int $internalId): ?string
    {
        if (! $internalId) return null;
        return ChannelMapping::where('property_id', $property->id)
            ->where('entity_type', 'rate_plan')
            ->where('internal_id', $internalId)
            ->value('external_id');
    }
}
