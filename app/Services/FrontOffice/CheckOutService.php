<?php

namespace App\Services\FrontOffice;

use App\Models\Folio;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

/**
 * Check-out workflow:
 *   1. Validate reservation status (must be checked_in)
 *   2. Validate all folios have zero balance (or accept "settle later" for company billing)
 *   3. Generate invoice number for each folio
 *   4. Mark folios as settled (or transferred for city ledger)
 *   5. Update reservation_room.status to checked_out + checked_out_at + by
 *   6. Update room.status to vacant_dirty (housekeeping queue)
 *   7. Update reservation.status to checked_out
 */
class CheckOutService
{
    public function __construct(
        private NumberGeneratorService $numbers,
    ) {}

    public function checkOut(Reservation $reservation, ?int $userId = null, bool $allowOpenBalance = false): Reservation
    {
        if (!$reservation->canCheckOut()) {
            throw new \DomainException("Reservation in status '{$reservation->status}' cannot be checked out.");
        }

        return DB::transaction(function () use ($reservation, $userId, $allowOpenBalance) {
            $userId = $userId ?? auth()->id();

            $reservation->load(['rooms.room', 'folios.charges', 'folios.payments']);

            // Recompute and validate folio balances
            foreach ($reservation->folios as $folio) {
                $folio->recomputeTotals();
                $folio->refresh();

                if (!$allowOpenBalance && (float) $folio->balance != 0.0) {
                    throw new \DomainException(
                        "Folio {$folio->folio_number} has balance of {$folio->balance}. " .
                        "Either settle in full or pass allowOpenBalance=true (city ledger)."
                    );
                }

                $folio->update([
                    'invoice_number' => $folio->invoice_number ?? $this->numbers->invoiceNumber($reservation->property),
                    'invoice_generated_at' => $folio->invoice_generated_at ?? now(),
                    'status' => (float) $folio->balance == 0.0 ? Folio::STATUS_SETTLED : Folio::STATUS_TRANSFERRED,
                    'closed_at' => now(),
                    'closed_by' => $userId,
                ]);
            }

            // Free rooms back to housekeeping
            foreach ($reservation->rooms as $resRoom) {
                $resRoom->update([
                    'status' => 'checked_out',
                    'checked_out_at' => now(),
                    'checked_out_by' => $userId,
                ]);

                if ($resRoom->room) {
                    $resRoom->room->changeStatus(
                        Room::STATUS_VACANT_DIRTY,
                        $userId,
                        'Guest checked out'
                    );
                    $resRoom->room->update(['fo_status' => 'vacant']);
                }
            }

            $reservation->update([
                'status' => Reservation::STATUS_CHECKED_OUT,
                'status_changed_at' => now(),
                'status_changed_by' => $userId,
                'paid_amount' => $reservation->folios->sum('total_payments'),
                'balance_amount' => $reservation->folios->sum('balance'),
                'updated_by' => $userId,
            ]);

            return $reservation->fresh();
        });
    }
}
