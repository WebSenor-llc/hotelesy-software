<?php

namespace App\Services\ChannelManager\Contracts;

use App\Models\Property;
use App\Services\ChannelManager\DTOs\InventoryUpdate;
use App\Services\ChannelManager\DTOs\RateUpdate;
use App\Services\ChannelManager\DTOs\RestrictionUpdate;
use App\Services\ChannelManager\DTOs\PulledBooking;
use App\Services\ChannelManager\DTOs\SyncResult;
use Illuminate\Support\Collection;

/**
 * The contract every channel manager driver must implement.
 *
 * Channel managers (AxisRooms, STAAH, SiteMinder, RateGain) all do the same
 * 3 things from a PMS perspective:
 *   1. Push room inventory to OTAs
 *   2. Push rates and restrictions to OTAs
 *   3. Pull new/modified/cancelled bookings from OTAs
 *
 * This interface normalises those 3 operations. Each driver translates
 * to its specific XML/JSON wire format (AxisRooms uses XML, STAAH uses XML+JSON,
 * SiteMinder uses OTA spec XML).
 *
 * IMPORTANT: This is a stub-ready interface. AxisRoomsDriver below contains
 * a working skeleton but the actual XML payload format requires AxisRooms'
 * partner integration documentation + sandbox credentials, which Anthropic
 * cannot access. Ship the credentials to your dev team and they'll fill in
 * the wire format in 2-3 days.
 */
interface ChannelManagerDriver
{
    /**
     * Driver identifier - 'axisrooms', 'staah', 'siteminder', etc.
     */
    public function name(): string;

    /**
     * Test connectivity to the channel manager.
     */
    public function ping(Property $property): bool;

    /**
     * Push inventory (room counts) for one or more dates and room types.
     *
     * @param Collection<int,InventoryUpdate> $updates
     */
    public function pushInventory(Property $property, Collection $updates): SyncResult;

    /**
     * Push rates for date ranges.
     *
     * @param Collection<int,RateUpdate> $updates
     */
    public function pushRates(Property $property, Collection $updates): SyncResult;

    /**
     * Push restrictions (stop sell, CTA, CTD, min stay).
     *
     * @param Collection<int,RestrictionUpdate> $updates
     */
    public function pushRestrictions(Property $property, Collection $updates): SyncResult;

    /**
     * Pull new and modified bookings from the channel manager.
     * Returns normalised PulledBooking DTOs that BookingImporter handles.
     *
     * @return Collection<int,PulledBooking>
     */
    public function pullBookings(Property $property, ?\DateTimeInterface $since = null): Collection;

    /**
     * Acknowledge that a pulled booking has been processed.
     * Required by some channel managers (AxisRooms) to mark booking as read.
     */
    public function acknowledgeBooking(Property $property, string $externalBookingId): bool;
}
