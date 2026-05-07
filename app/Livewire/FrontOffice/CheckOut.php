<?php

namespace App\Livewire\FrontOffice;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class CheckOut extends Component
{
    public ?int $selectedReservationId = null;
    public string $paymentMode = 'cash';
    public float $paymentAmount = 0;
    public string $paymentReference = '';
    public bool $allowOpenBalance = false; // safety toggle to allow check-out with balance still due

    public function selectReservation(int $id): void
    {
        $this->selectedReservationId = $id;
        $reservation = Reservation::find($id);
        // Auto-apply LCO so the displayed balance is real BEFORE the cashier types
        if ($reservation) {
            app(\App\Services\Billing\EciLcoService::class)->applyLateCheckOut($reservation);
            $reservation->refresh();
        }
        $this->paymentAmount = $reservation ? max(0, (float) $reservation->balance_amount) : 0;
        $this->allowOpenBalance = false;
        $this->paymentReference = '';
        $this->resetErrorBag();
    }

    public function payFullBalance(): void
    {
        if (! $this->selectedReservationId) return;
        $folio = Folio::where('reservation_id', $this->selectedReservationId)
            ->where('status', Folio::STATUS_OPEN)->first();
        if ($folio) {
            $this->paymentAmount = max(0, (float) $folio->balance);
        }
    }

    public function checkOut()
    {
        $this->validate([
            'selectedReservationId' => 'required|exists:reservations,id',
            'paymentMode'           => 'required|in:cash,card,upi,bank_transfer,company_credit',
            'paymentAmount'         => 'required|numeric|min:0',
        ]);

        $ctx = app(TenantContext::class);
        $reservation = Reservation::where('property_id', $ctx->propertyId())->findOrFail($this->selectedReservationId);

        // Auto-apply late-check-out fee BEFORE collecting payment so the
        // posted charge is included in the balance the cashier sees.
        $lcoResult = app(\App\Services\Billing\EciLcoService::class)
            ->applyLateCheckOut($reservation);

        $folio = Folio::where('reservation_id', $reservation->id)->where('status', Folio::STATUS_OPEN)->first();
        $reservation->refresh();

        // Compute remaining balance AFTER this payment is applied.
        $currentBalance = $folio ? (float) $folio->balance : (float) $reservation->balance_amount;
        $remainingAfter = round($currentBalance - (float) $this->paymentAmount, 2);

        // Block check-out if balance would still be due, unless the cashier
        // has explicitly approved a city-ledger / partial-pay check-out.
        if ($remainingAfter > 0.01 && ! $this->allowOpenBalance && $this->paymentMode !== 'company_credit') {
            session()->flash('error', "₹" . number_format($remainingAfter, 2) . " is still due. Either collect the full balance, switch to City-ledger / Credit, or tick \"Allow check-out with balance\" to proceed.");
            return;
        }
        if ($remainingAfter < -0.01) {
            // Overpayment — flag a friendly note but allow it (refund logic later)
            session()->flash('warning', "Overpayment of ₹" . number_format(abs($remainingAfter), 2) . " noted. Please return change to guest or process refund.");
        }

        if ($this->paymentAmount > 0 && $folio) {
            Payment::create([
                'tenant_id'      => $reservation->tenant_id,
                'property_id'    => $reservation->property_id,
                'folio_id'       => $folio->id,
                'reservation_id' => $reservation->id,
                'receipt_number' => 'PAY-'.strtoupper(substr(md5(uniqid()), 0, 6)),
                'payment_date'   => today(),
                'business_date'  => today(),
                'amount'         => $this->paymentAmount,
                'currency'       => 'INR',
                'mode'           => $this->paymentMode,
                'transaction_reference' => $this->paymentReference ?: null,
                'received_by'    => auth()->id(),
                'status'         => 'completed',
            ]);
            $folio->update([
                'total_payments' => $folio->total_payments + $this->paymentAmount,
                'balance'        => $folio->balance - $this->paymentAmount,
            ]);
            $reservation->update([
                'paid_amount'    => $reservation->paid_amount + $this->paymentAmount,
                'balance_amount' => max(0, $reservation->balance_amount - $this->paymentAmount),
            ]);
        }

        // Free the room
        $rRoom = $reservation->rooms()->first();
        if ($rRoom?->room_id) {
            Room::where('id', $rRoom->room_id)->update([
                'status'    => 'vacant_dirty',
                'fo_status' => 'vacant',
            ]);
        }
        $rRoom?->update([
            'status'         => 'checked_out',
            'checked_out_at' => now(),
            'checked_out_by' => auth()->id(),
        ]);

        // Close folio + checkout reservation
        if ($folio && $folio->balance <= 0) {
            $folio->update(['status' => Folio::STATUS_SETTLED, 'closed_at' => now(), 'closed_by' => auth()->id()]);
        }
        $reservation->update([
            'status' => Reservation::STATUS_CHECKED_OUT,
            'status_changed_at' => now(),
            'status_changed_by' => auth()->id(),
        ]);

        $finalBalance = $folio ? max(0, (float) $folio->fresh()->balance) : 0;
        $guestName = $reservation->guest_name;
        $resNum    = $reservation->reservation_number;

        $this->reset(['selectedReservationId','paymentAmount','paymentReference','allowOpenBalance']);
        $this->paymentMode = 'cash';

        $parts = ["✓ {$guestName} ({$resNum}) checked out."];
        if ($this->paymentAmount > 0 || $remainingAfter < 0) {
            // (paymentAmount was reset above — use the original captured amount via $remainingAfter math)
        }
        if (in_array($lcoResult['kind'] ?? 'none', ['half_day','full_day']) && ($lcoResult['amount'] ?? 0) > 0) {
            $parts[] = "LCO fee ₹" . number_format($lcoResult['amount'], 2) . " ({$lcoResult['kind']}) was added";
        }
        if ($finalBalance > 0.01) {
            $parts[] = "Open balance ₹" . number_format($finalBalance, 2) . " moved to city ledger";
        } else {
            $parts[] = "Folio settled in full";
        }
        session()->flash('success', implode(' · ', $parts));
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $departures = Reservation::where('property_id', $propertyId)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->orderBy('departure_date')
            ->get();

        $selected = $this->selectedReservationId ? Reservation::with('rooms')->find($this->selectedReservationId) : null;
        $folio = $selected ? Folio::where('reservation_id', $selected->id)->first() : null;

        return view('livewire.front-office.check-out', compact('departures','selected','folio'));
    }
}
