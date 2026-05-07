<?php

namespace App\Services\ChannelManager\Drivers;

use App\Models\Property;
use App\Services\ChannelManager\Contracts\ChannelManagerDriver;
use App\Services\ChannelManager\DTOs\SyncResult;
use Illuminate\Support\Collection;

/**
 * Null Object pattern. Used when a tenant has no channel manager configured.
 * All operations succeed quietly without making any HTTP calls.
 * Lets the rest of the system call channel manager methods without null checks.
 */
class NullDriver implements ChannelManagerDriver
{
    public function name(): string { return 'null'; }
    public function ping(Property $property): bool { return true; }

    public function pushInventory(Property $property, Collection $updates): SyncResult
    {
        return SyncResult::success($updates->count());
    }

    public function pushRates(Property $property, Collection $updates): SyncResult
    {
        return SyncResult::success($updates->count());
    }

    public function pushRestrictions(Property $property, Collection $updates): SyncResult
    {
        return SyncResult::success($updates->count());
    }

    public function pullBookings(Property $property, ?\DateTimeInterface $since = null): Collection
    {
        return collect();
    }

    public function acknowledgeBooking(Property $property, string $externalBookingId): bool
    {
        return true;
    }
}
