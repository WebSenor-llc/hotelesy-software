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
    /**
     * Multi-mode (split-tender) payments. Each row: ['mode' => string, 'amount' => float, 'reference' => string].
     * One Payment record is created per non-zero row at check-out, so split-tender
     * payments (e.g. ₹10k UPI + ₹60k cash + ₹20k bank) post correctly to the GL
     * and appear as distinct payment lines on the GST invoice / day-end report.
     */
    public array $payments = [
        ['mode' => 'cash', 'amount' => 0.0, 'reference' => ''],
    ];
    public bool $allowOpenBalance = false; // safety toggle to allow check-out with balance still due

    public function addPaymentRow(): void
    {
        $this->payments[] = ['mode' => 'cash', 'amount' => 0.0, 'reference' => ''];
    }

    public function removePaymentRow(int $index): void
    {
        if (count($this->payments) > 1 && isset($this->payments[$index])) {
            unset($this->payments[$index]);
            $this->payments = array_values($this->payments);
        }
    }

    /**
     * Sum of all payment-row amounts. Used by the live balance preview and
     * the "still due" guard in checkOut().
     */
    public function getTotalPaymentAmountProperty(): float
    {
        return array_sum(array_map(
            fn ($row) => (float) ($row['amount'] ?? 0),
            $this->payments
        ));
    }

    public function selectReservation(int $id): void
    {
        $this->selectedReservationId = $id;
        $reservation = Reservation::find($id);
        // Auto-apply LCO so the displayed balance is real BEFORE the cashier types
        if ($reservation) {
            app(\App\Services\Billing\EciLcoService::class)->applyLateCheckOut($reservation);
            $reservation->refresh();
        }
        $balance = $reservation ? max(0, (float) $reservation->balance_amount) : 0;
        // Reset to a single row pre-filled with the full balance — the cashier
        // can split it across multiple modes by clicking "Add another payment".
        $this->payments = [['mode' => 'cash', 'amount' => $balance, 'reference' => '']];
        $this->allowOpenBalance = false;
        $this->resetErrorBag();
    }

    public function payFullBalance(): void
    {
        if (! $this->selectedReservationId) return;
        $folio = Folio::where('reservation_id', $this->selectedReservationId)
            ->where('status', Folio::STATUS_OPEN)->first();
        if ($folio) {
            $bal = max(0, (float) $folio->balance);
            // Put the full balance on the first row, zero out the rest. This way
            // the cashier can still see the additional rows they added but they
            // contribute 0 to the total.
            if (! empty($this->payments)) {
                $this->payments[0]['amount'] = $bal;
                for ($i = 1; $i < count($this->payments); $i++) {
                    $this->payments[$i]['amount'] = 0.0;
                }
            } else {
                $this->payments = [['mode' => 'cash', 'amount' => $bal, 'reference' => '']];
            }
        }
    }

    public function checkOut()
    {
        $this->validate([
            'selectedReservationId'   => 'required|exists:reservations,id',
            'payments'                => 'required|array|min:1',
            'payments.*.mode'         => 'required|in:cash,card,upi,bank_transfer,company_credit',
            'payments.*.amount'       => 'required|numeric|min:0',
            'payments.*.reference'    => 'nullable|string|max:100',
        ], [
            'payments.*.mode.required'   => 'Each payment row needs a mode.',
            'payments.*.amount.required' => 'Each payment row needs an amount (use 0 to skip).',
        ]);

        // Drop zero-amount rows — the cashier may have added a row and not used it.
        $effectivePayments = array_values(array_filter(
            $this->payments,
            fn ($row) => (float) ($row['amount'] ?? 0) > 0
        ));

        $ctx = app(TenantContext::class);
        $reservation = Reservation::where('property_id', $ctx->propertyId())->findOrFail($this->selectedReservationId);

        // Auto-apply late-check-out fee BEFORE collecting payment so the
        // posted charge is included in the balance the cashier sees.
        $lcoResult = app(\App\Services\Billing\EciLcoService::class)
            ->applyLateCheckOut($reservation);

        $folio = Folio::where('reservation_id', $reservation->id)->where('status', Folio::STATUS_OPEN)->first();
        $reservation->refresh();

        // Compute remaining balance AFTER all payments are applied.
        $currentBalance = $folio ? (float) $folio->balance : (float) $reservation->balance_amount;
        $totalPayment = array_sum(array_map(
            fn ($row) => (float) $row['amount'],
            $effectivePayments
        ));
        $remainingAfter = round($currentBalance - $totalPayment, 2);

        // Block check-out if balance would still be due, unless the cashier has
        // explicitly approved a city-ledger / partial-pay check-out, OR at least
        // one payment row is company_credit (city ledger) which authorises moving
        // the open balance to accounts-receivable.
        $hasCityLedger = collect($effectivePayments)->contains(fn ($row) => ($row['mode'] ?? '') === 'company_credit');
        if ($remainingAfter > 0.01 && ! $this->allowOpenBalance && ! $hasCityLedger) {
            session()->flash('error', "₹" . number_format($remainingAfter, 2) . " is still due. Either collect the full balance, add a City-ledger / Credit row, or tick \"Allow check-out with balance\" to proceed.");
            return;
        }
        if ($remainingAfter < -0.01) {
            // Overpayment — flag a friendly note but allow it (refund logic later)
            session()->flash('warning', "Overpayment of ₹" . number_format(abs($remainingAfter), 2) . " noted. Please return change to guest or process refund.");
        }

        // Create one Payment row per non-zero entry — split-tender posts as
        // distinct ledger lines so the day-end report and GST invoice both
        // show the correct breakdown by mode.
        if (! empty($effectivePayments) && $folio) {
            foreach ($effectivePayments as $row) {
                Payment::create([
                    'tenant_id'      => $reservation->tenant_id,
                    'property_id'    => $reservation->property_id,
                    'folio_id'       => $folio->id,
                    'reservation_id' => $reservation->id,
                    'receipt_number' => 'PAY-'.strtoupper(substr(md5(uniqid()), 0, 6)),
                    'payment_date'   => today(),
                    'business_date'  => today(),
                    'amount'         => (float) $row['amount'],
                    'currency'       => 'INR',
                    'mode'           => $row['mode'],
                    'transaction_reference' => ! empty($row['reference']) ? $row['reference'] : null,
                    'received_by'    => auth()->id(),
                    'status'         => 'completed',
                ]);
            }
            $folio->update([
                'total_payments' => $folio->total_payments + $totalPayment,
                'balance'        => $folio->balance - $totalPayment,
            ]);
            $reservation->update([
                'paid_amount'    => $reservation->paid_amount + $totalPayment,
                'balance_amount' => max(0, $reservation->balance_amount - $totalPayment),
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

        // ============================================================
        // AUTO-ISSUE GST TAX INVOICE — Section 31, Rule 46 of CGST Rules
        // The "time of supply" for accommodation is at supply completion
        // (check-out), so this is the legally correct moment to issue the
        // tax invoice. Idempotent — reuses if one already exists for this folio.
        // ============================================================
        $invoice = null;
        $invoiceError = null;
        if ($folio) {
            try {
                $existing = \App\Models\TaxInvoice::where('source_type', \App\Models\Folio::class)
                    ->where('source_id', $folio->id)
                    ->orderByDesc('id')
                    ->first();
                if ($existing) {
                    $invoice = $existing;
                } else {
                    $invoice = app(\App\Services\Tax\InvoiceBuilder::class)
                        ->buildFromFolio($folio->fresh(), auth()->id());
                }
            } catch (\Throwable $e) {
                // Don't block the checkout if invoice generation fails — log it
                // and surface a warning so the cashier can issue manually later.
                \Log::warning('Auto-issue tax invoice failed at checkout: ' . $e->getMessage());
                $invoiceError = $e->getMessage();
            }
        }

        $finalBalance = $folio ? max(0, (float) $folio->fresh()->balance) : 0;
        $guestName = $reservation->guest_name;
        $resNum    = $reservation->reservation_number;

        $this->reset(['selectedReservationId','allowOpenBalance']);
        $this->payments = [['mode' => 'cash', 'amount' => 0.0, 'reference' => '']];

        $parts = ["✓ {$guestName} ({$resNum}) checked out."];
        if ($totalPayment > 0) {
            // Show split-mode breakdown if more than one mode was used.
            $byMode = [];
            foreach ($effectivePayments as $row) {
                $byMode[$row['mode']] = ($byMode[$row['mode']] ?? 0) + (float) $row['amount'];
            }
            if (count($byMode) > 1) {
                $modeLabels = ['cash'=>'Cash','card'=>'Card','upi'=>'UPI','bank_transfer'=>'Bank','company_credit'=>'Credit'];
                $breakdown = [];
                foreach ($byMode as $mode => $amt) {
                    $breakdown[] = ($modeLabels[$mode] ?? $mode) . ' ₹' . number_format($amt, 0);
                }
                $parts[] = 'Collected ₹' . number_format($totalPayment, 2) . ' (' . implode(' + ', $breakdown) . ')';
            } else {
                $parts[] = 'Collected ₹' . number_format($totalPayment, 2);
            }
        }
        if (in_array($lcoResult['kind'] ?? 'none', ['half_day','full_day']) && ($lcoResult['amount'] ?? 0) > 0) {
            $parts[] = "LCO fee ₹" . number_format($lcoResult['amount'], 2) . " ({$lcoResult['kind']}) was added";
        }
        if ($finalBalance > 0.01) {
            $parts[] = "Open balance ₹" . number_format($finalBalance, 2) . " moved to city ledger";
        } else {
            $parts[] = "Folio settled in full";
        }
        if ($invoice) {
            $parts[] = "GST invoice {$invoice->invoice_number} issued";
            session()->flash('issued_invoice_id', $invoice->id);
            session()->flash('issued_invoice_number', $invoice->invoice_number);
        }
        session()->flash('success', implode(' · ', $parts));
        if ($invoiceError) {
            session()->flash('warning', "Invoice could not be auto-generated: {$invoiceError}. Issue manually from the folio.");
        }
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
