<?php

namespace App\Services\Integrations\DoorLock;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;

/**
 * DoorLock contract — abstracts hotel door-lock vendors.
 *
 * Major vendors supported (in production):
 *   - Onity (HT24, Advance) — most common in India
 *   - Saflok (RFID, Quantum) — Marriott, Hilton properties
 *   - dormakaba (Saflok successor)
 *   - Salto (RFID + BLE mobile keys)
 *   - Assa Abloy (VingCard)
 *
 * Each vendor's encoder talks to a local desktop app on the front office PC.
 * The PMS communicates with the encoder over a vendor-specific protocol
 * (TCP socket, named pipe, or HTTP local API).
 */
interface DoorLock
{
    public function name(): string;

    /**
     * Encode a key card (or send mobile key) for a reservation.
     * Returns the key reference for tracking, or throws on failure.
     */
    public function issueKey(Property $property, Reservation $reservation, Room $room, ?\DateTimeInterface $validFrom = null, ?\DateTimeInterface $validUntil = null): array;

    /**
     * Cancel an issued key (e.g. on early checkout, lost key).
     */
    public function revokeKey(Property $property, string $keyReference): bool;

    /**
     * Check if encoder is online and responsive.
     */
    public function ping(Property $property): bool;
}
