<?php

namespace App\Services\Billing;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Services\TaxCalculationService;
use Illuminate\Support\Facades\DB;

/**
 * Post charges to folios. Every charge is tax-aware.
 *
 * Categories:
 *   - room (posted by night audit usually)
 *   - food, beverage (from POS)
 *   - laundry, mini_bar, telephone, spa, misc
 *   - damage, extra_bed, package, discount
 *   - service_charge (auto-calc), tax (auto-calc)
 *   - transfer (folio-to-folio movement)
 *   - adjustment (manager-only)
 */
class FolioService
{
    public function __construct(private TaxCalculationService $tax) {}

    public function postCharge(Folio $folio, array $data): FolioCharge
    {
        return DB::transaction(function () use ($folio, $data) {
            $amount = (float) ($data['amount'] ?? ((float) $data['quantity'] * (float) $data['rate']));
            $discount = (float) ($data['discount_amount'] ?? 0);
            $taxableAmount = max(0, $amount - $discount);

            $taxBreakdown = ($data['skip_tax'] ?? false)
                ? ['cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total' => 0]
                : $this->tax->compute(
                    $folio->property ?? $folio->reservation?->property ?? \App\Models\Property::find($folio->property_id),
                    $taxableAmount,
                    $this->categoryToTaxClass($data['category'] ?? 'misc')
                );

            $taxAmount = (float) ($taxBreakdown['total'] ?? 0);
            $netAmount = $taxableAmount + $taxAmount;

            $charge = FolioCharge::create([
                'property_id' => $folio->property_id,
                'folio_id' => $folio->id,
                'charge_date' => $data['charge_date'] ?? now()->toDateString(),
                'charge_time' => $data['charge_time'] ?? now()->toTimeString(),
                'business_date' => $data['business_date']
                    ?? $folio->reservation?->property?->businessDate()?->toDateString()
                    ?? now()->toDateString(),
                'category' => $data['category'] ?? 'misc',
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'rate' => $data['rate'] ?? $amount,
                'amount' => $amount,
                'discount_amount' => $discount,
                'tax_amount' => $taxAmount,
                'net_amount' => $netAmount,
                'tax_breakdown' => $taxBreakdown,
                'posted_by' => auth()->id(),
                'device_id' => request()->header('X-Device-Id'),
            ]);

            $folio->recomputeTotals();
            return $charge;
        });
    }

    public function voidCharge(FolioCharge $charge, string $reason, ?int $userId = null): FolioCharge
    {
        if ($charge->is_voided) {
            throw new \DomainException('Charge is already voided.');
        }

        return DB::transaction(function () use ($charge, $reason, $userId) {
            $charge->update([
                'is_voided' => true,
                'voided_at' => now(),
                'voided_by' => $userId ?? auth()->id(),
                'void_reason' => $reason,
            ]);

            $charge->folio->recomputeTotals();
            return $charge->fresh();
        });
    }

    /**
     * Transfer a charge from one folio to another (e.g. guest folio -> company folio).
     */
    public function transferCharge(FolioCharge $charge, Folio $targetFolio, ?string $reason = null): FolioCharge
    {
        if ($charge->folio_id === $targetFolio->id) {
            throw new \DomainException('Source and target folios are identical.');
        }

        return DB::transaction(function () use ($charge, $targetFolio, $reason) {
            $oldFolio = $charge->folio;

            $charge->update(['folio_id' => $targetFolio->id]);

            $oldFolio->recomputeTotals();
            $targetFolio->recomputeTotals();

            return $charge->fresh();
        });
    }

    private function categoryToTaxClass(string $category): string
    {
        return match ($category) {
            'room', 'extra_bed', 'package' => 'room',
            'food', 'beverage' => 'food',
            default => 'other',
        };
    }
}
