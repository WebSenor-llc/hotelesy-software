<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Tax;
use Illuminate\Support\Collection;

/**
 * Indian hotel GST slabs (FY 2025-26 baseline):
 *   ≤ ₹1000/night        : 0% (exempt)
 *   ₹1001 - ₹7500/night  : 12% (split CGST 6% + SGST 6% intra-state, IGST 12% inter-state)
 *   > ₹7500/night        : 18% (CGST 9% + SGST 9%, or IGST 18%)
 *
 * Threshold is per room per night before discount.
 * Restaurant (F&B) and other services have separate slabs.
 *
 * Service charge (5-10%) is optional and goes BEFORE GST.
 */
class TaxCalculationService
{
    /**
     * Returns ['cgst' => x, 'sgst' => y, 'igst' => 0, 'total' => z]
     * for a given taxable amount and applicable category.
     *
     * @param string $category  'room' | 'food' | 'other'
     * @param bool $interState  true if guest is from outside the property's state -> IGST applies
     */
    public function compute(Property $property, float $taxableAmount, string $category = 'room', bool $interState = false): array
    {
        $taxes = $this->applicableTaxes($property, $taxableAmount, $category);

        $breakdown = ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'service_charge' => 0.0, 'other' => 0.0];

        foreach ($taxes as $tax) {
            $taxAmount = round($taxableAmount * ((float) $tax->rate / 100), 2);

            switch ($tax->type) {
                case 'cgst':
                    $breakdown['cgst'] += $taxAmount;
                    break;
                case 'sgst':
                    $breakdown['sgst'] += $taxAmount;
                    break;
                case 'igst':
                    $breakdown['igst'] += $taxAmount;
                    break;
                case 'service_charge':
                    $breakdown['service_charge'] += $taxAmount;
                    break;
                default:
                    $breakdown['other'] += $taxAmount;
            }
        }

        // For inter-state, replace CGST+SGST with IGST
        if ($interState && ($breakdown['cgst'] + $breakdown['sgst']) > 0) {
            $breakdown['igst'] += $breakdown['cgst'] + $breakdown['sgst'];
            $breakdown['cgst'] = 0;
            $breakdown['sgst'] = 0;
        }

        $breakdown['total'] = round(
            $breakdown['cgst'] + $breakdown['sgst'] + $breakdown['igst']
            + $breakdown['service_charge'] + $breakdown['other'],
            2
        );

        return $breakdown;
    }

    /**
     * Returns the taxes from `taxes` table that apply to this amount and category.
     */
    private function applicableTaxes(Property $property, float $amount, string $category): Collection
    {
        return $property->taxes()
            ->where('is_active', true)
            ->where(function ($q) use ($category) {
                $col = match ($category) {
                    'room' => 'applies_to_room',
                    'food' => 'applies_to_food',
                    default => 'applies_to_other',
                };
                $q->where($col, true);
            })
            ->get()
            ->filter(fn(Tax $t) => $t->appliesToAmount($amount));
    }
}
