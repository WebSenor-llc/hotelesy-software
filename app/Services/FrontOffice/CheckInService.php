<?php

namespace App\Services\FrontOffice;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * Check-in workflow:
 *   1. Validate reservation status
 *   2. Allocate a physical room (if not already)
 *   3. Update room.status to occupied_clean and fo_status to occupied
 *   4. Update reservation_room.status to checked_in, set checked_in_at + by
 *   5. Update reservation.status to checked_in
 *   6. Folio is already open (created by ReservationService); no action needed here
 */
class CheckInService
{
    public function checkIn(Reservation $reservation, array $roomAllocations = [], ?int $userId = null): Reservation
    {
        if (!$reservation->canCheckIn()) {
            throw new \DomainException("Reservation in status '{$reservation->status}' cannot be checked in.");
        }

        return DB::transaction(function () use ($reservation, $roomAllocations, $userId) {
            $userId = $userId ?? auth()->id();

            $reservation->load('rooms');

            foreach ($reservation->rooms as $resRoom) {
                $roomId = $roomAllocations[$resRoom->id] ?? $resRoom->room_id;

                if (!$roomId) {
                    throw new \DomainException(
                        "No room allocated for reservation room {$resRoom->id}. Pass [reservation_room_id => room_id] in \$roomAllocations."
                    );
                }

                $this->checkInOneRoom($resRoom, $roomId, $userId);
            }

            $reservation->update([
                'status' => Reservation::STATUS_CHECKED_IN,
                'status_changed_at' => now(),
                'status_changed_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $reservation->fresh(['rooms.room', 'folios']);
        });
    }

    private function checkInOneRoom(ReservationRoom $resRoom, int $roomId, int $userId): void
    {
        $room = Room::lockForUpdate()->findOrFail($roomId);

        if (!$room->isAvailable() && $room->id !== $resRoom->room_id) {
            throw new \DomainException("Room {$room->number} is not available (status: {$room->status}, fo_status: {$room->fo_status}).");
        }

        $resRoom->update([
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'checked_in_by' => $userId,
        ]);

        $room->changeStatus(Room::STATUS_OCCUPIED_CLEAN, $userId, 'Guest checked in');
        $room->update(['fo_status' => 'occupied']);
    }
}
