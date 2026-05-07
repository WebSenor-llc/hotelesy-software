<?php

namespace App\Services\Accounts;

use App\Models\Accounts\ChartAccount;
use App\Models\Accounts\Voucher;
use App\Models\Accounts\VoucherEntry;
use App\Models\Accounts\VoucherType;
use App\Models\Property;
use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * VoucherService — posts double-entry vouchers (the heart of any accounting
 * system).
 *
 * Every voucher must satisfy: SUM(debit) === SUM(credit). Otherwise it cannot
 * be posted (will remain in 'draft').
 *
 * Voucher types map to common transactions:
 *   - receipt: cash received (Dr cash, Cr revenue/customer)
 *   - payment: cash out (Dr expense/vendor, Cr cash)
 *   - journal: arbitrary adjustment
 *   - contra: cash↔bank transfer
 *   - sales: invoice posted (Dr customer, Cr revenue + tax payable)
 *   - purchase: vendor invoice (Dr expense + ITC, Cr vendor)
 *
 * Side effects of posting:
 *   - Creates voucher header + entries
 *   - Recomputes account balances (lazy, via ChartAccount::currentBalance)
 *
 * Reversal: a posted voucher is reversed by creating an opposite-sign voucher
 * that references the original. Original is marked 'reversed' but never deleted
 * (immutable audit trail).
 *
 * Auto-posting from PMS:
 *   - Reservation check-out → ReservationToVoucherListener fires this with
 *     the folio totals to post a sales voucher.
 *   - Payment recorded → fires a receipt voucher.
 *   - GRN posted → fires a purchase voucher.
 */
class VoucherService
{
    public function __construct(private readonly NumberGeneratorService $numbers) {}

    /**
     * Create + post a voucher in one operation.
     *
     * @param  array  $entries  [['account_id' => 1, 'side' => 'debit', 'amount' => 1000, 'narration' => '...'], ...]
     */
    public function post(
        Property $property,
        VoucherType $type,
        Carbon $voucherDate,
        array $entries,
        array $options = []
    ): Voucher {
        if (count($entries) < 2) {
            throw new \InvalidArgumentException('Voucher must have at least 2 entries (one debit, one credit).');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($entries as $e) {
            if (! in_array($e['side'], ['debit', 'credit'], true)) {
                throw new \InvalidArgumentException("Invalid side: {$e['side']}");
            }
            if (! isset($e['account_id'], $e['amount'])) {
                throw new \InvalidArgumentException('Each entry needs account_id and amount.');
            }
            if ((float) $e['amount'] <= 0) {
                throw new \InvalidArgumentException('Entry amount must be positive.');
            }
            if ($e['side'] === 'debit') $totalDebit += (float) $e['amount'];
            else $totalCredit += (float) $e['amount'];
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \DomainException(
                "Voucher is unbalanced: Dr {$totalDebit} != Cr {$totalCredit}"
            );
        }

        return DB::transaction(function () use ($property, $type, $voucherDate, $entries, $options, $totalDebit, $totalCredit) {
            $voucher = Voucher::create([
                'property_id' => $property->id,
                'voucher_type_id' => $type->id,
                'voucher_number' => $this->numbers->generate($property, 'voucher', $type->prefix ?: $type->code),
                'voucher_date' => $voucherDate->toDateString(),
                'business_date' => ($options['business_date'] ?? $voucherDate)->toDateString(),
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'narration' => $options['narration'] ?? null,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'status' => Voucher::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            foreach ($entries as $e) {
                VoucherEntry::create([
                    'voucher_id' => $voucher->id,
                    'account_id' => $e['account_id'],
                    'side' => $e['side'],
                    'amount' => round((float) $e['amount'], 2),
                    'narration' => $e['narration'] ?? null,
                ]);
            }

            return $voucher->fresh('entries.account');
        });
    }

    /**
     * Reverse a posted voucher by creating an opposite-sign voucher that
     * references it. Original retains 'reversed' status.
     */
    public function reverse(Voucher $voucher, ?string $reason = null): Voucher
    {
        if ($voucher->status !== Voucher::STATUS_POSTED) {
            throw new \DomainException("Only posted vouchers can be reversed.");
        }

        return DB::transaction(function () use ($voucher, $reason) {
            $reversal = Voucher::create([
                'property_id' => $voucher->property_id,
                'voucher_type_id' => $voucher->voucher_type_id,
                'voucher_number' => $this->numbers->generate(
                    $voucher->property ?? \App\Models\Property::find($voucher->property_id),
                    'voucher_reverse',
                    'REV/' . ($voucher->voucher_type->prefix ?? 'V')
                ),
                'voucher_date' => now()->toDateString(),
                'business_date' => now()->toDateString(),
                'reference_type' => 'voucher',
                'reference_id' => $voucher->id,
                'reverses_voucher_id' => $voucher->id,
                'narration' => 'Reversal of ' . $voucher->voucher_number . ($reason ? ': ' . $reason : ''),
                'total_debit' => $voucher->total_credit,
                'total_credit' => $voucher->total_debit,
                'status' => Voucher::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            // Mirror entries with sides swapped
            foreach ($voucher->entries as $entry) {
                VoucherEntry::create([
                    'voucher_id' => $reversal->id,
                    'account_id' => $entry->account_id,
                    'side' => $entry->side === 'debit' ? 'credit' : 'debit',
                    'amount' => $entry->amount,
                    'narration' => 'Reversal: ' . ($entry->narration ?? ''),
                ]);
            }

            $voucher->update([
                'status' => Voucher::STATUS_REVERSED,
                'reversed_by' => auth()->id(),
                'reversed_at' => now(),
            ]);

            return $reversal;
        });
    }

    /**
     * Trial Balance — list all accounts with their balance as of date.
     */
    public function trialBalance(Property $property, ?Carbon $asOf = null): array
    {
        $accounts = ChartAccount::where('property_id', $property->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDr = 0;
        $totalCr = 0;
        foreach ($accounts as $a) {
            $bal = $a->currentBalance($asOf);
            // Convention: positive balance shown on natural side
            $isDebitNatural = in_array($a->type, [ChartAccount::TYPE_ASSET, ChartAccount::TYPE_EXPENSE], true);
            $rows[] = [
                'account_code' => $a->code,
                'account_name' => $a->name,
                'type' => $a->type,
                'debit' => $isDebitNatural ? max(0, $bal) : 0,
                'credit' => ! $isDebitNatural ? max(0, $bal) : 0,
            ];
            if ($isDebitNatural) $totalDr += max(0, $bal);
            else $totalCr += max(0, $bal);
        }

        return [
            'rows' => $rows,
            'total_debit' => round($totalDr, 2),
            'total_credit' => round($totalCr, 2),
            'balanced' => abs($totalDr - $totalCr) < 0.01,
        ];
    }
}
