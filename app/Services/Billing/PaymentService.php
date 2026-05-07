<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Compliance\TdsCalculator;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

/**
 * Records payments against folios or directly against reservations (advance).
 *
 * For card payments, only stores last4 + brand + approval_code.
 * Full PAN never enters the database (PCI baseline).
 */
class PaymentService
{
    public function __construct(private NumberGeneratorService $numbers) {}

    public function record(Folio $folio, array $data): Payment
    {
        return DB::transaction(function () use ($folio, $data) {
            $property = $folio->reservation?->property
                ?? \App\Models\Property::findOrFail($folio->property_id);

            // TDS computation: only when payer is a TDS-applicable company
            $tdsAmount = (float) ($data['tds_amount'] ?? 0);
            $tdsSection = $data['tds_section'] ?? null;
            $companyId = $data['company_id'] ?? $folio->company_id;

            if ($tdsAmount === 0.0 && $companyId && ($data['mode'] ?? null) === Payment::MODE_COMPANY_CREDIT) {
                $company = Company::find($companyId);
                if ($company && $company->tds_applicable) {
                    $tds = app(TdsCalculator::class)->calculateForFolio($folio, $company);
                    $tdsAmount = (float) ($tds['amount'] ?? 0);
                    $tdsSection = $tds['section'] ?? null;
                }
            }

            $payment = Payment::create([
                'property_id' => $folio->property_id,
                'folio_id' => $folio->id,
                'reservation_id' => $folio->reservation_id,
                'receipt_number' => $this->numbers->receiptNumber($property),
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'business_date' => $data['business_date'] ?? $property->businessDate()->toDateString(),
                'mode' => $data['mode'],
                'amount' => $data['amount'],
                'tds_amount' => $tdsAmount,
                'tds_section' => $tdsSection,
                'currency' => $data['currency'] ?? $property->currency,
                'card_last4' => $data['card_last4'] ?? null,
                'card_brand' => $data['card_brand'] ?? null,
                'card_holder_name' => $data['card_holder_name'] ?? null,
                'approval_code' => $data['approval_code'] ?? null,
                'upi_reference' => $data['upi_reference'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'gateway_payment_id' => $data['gateway_payment_id'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'company_id' => $data['company_id'] ?? $folio->company_id,
                'status' => $data['status'] ?? 'completed',
                'notes' => $data['notes'] ?? null,
                'received_by' => auth()->id(),
                'device_id' => request()->header('X-Device-Id'),
            ]);

            $folio->recomputeTotals();

            // Update reservation paid + balance
            if ($reservation = $folio->reservation) {
                $reservation->update([
                    'paid_amount' => $reservation->folios()->sum('total_payments'),
                    'balance_amount' => max(0, $reservation->total_amount - $reservation->folios()->sum('total_payments')),
                ]);
            }

            return $payment;
        });
    }

    /**
     * Issue a refund payment (negative amount).
     * Original payment status updated to 'refunded' if full refund.
     */
    public function refund(Payment $original, float $amount, ?string $reason = null): Payment
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be positive.');
        }
        if ($amount > (float) $original->amount) {
            throw new \InvalidArgumentException('Refund cannot exceed original payment amount.');
        }

        return DB::transaction(function () use ($original, $amount, $reason) {
            $refund = $this->record($original->folio, [
                'mode' => Payment::MODE_CASH === $original->mode ? 'cash' : 'refund',
                'amount' => -$amount,
                'transaction_reference' => "Refund of {$original->receipt_number}",
                'notes' => $reason ?? 'Refund',
            ]);

            if ($amount == (float) $original->amount) {
                $original->update(['status' => 'refunded']);
            }

            return $refund;
        });
    }
}
