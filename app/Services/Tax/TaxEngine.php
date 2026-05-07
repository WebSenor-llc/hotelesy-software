<?php

namespace App\Services\Tax;

use App\Models\Property;
use App\Models\TaxRule;
use Illuminate\Support\Carbon;

/**
 * TaxEngine — single source of truth for India GST computation across
 * Room / F&B / Banquet / Service / Liquor / Tobacco supplies.
 *
 * Rules of thumb (India FY 2025-26+):
 *  - Room ≤ ₹7,500/night/room        → GST 12% (HSN 996311)
 *  - Room > ₹7,500/night/room        → GST 18% (HSN 996311)
 *  - F&B (declared room tariff < ₹7,500) → 5% no-ITC (HSN 9963)
 *  - F&B (declared room tariff ≥ ₹7,500) → 18% with-ITC (HSN 9963)
 *  - Banquet / outdoor catering        → 18% with-ITC (HSN 9963)
 *  - Other services (spa, laundry, parking) → 18% (HSN 9963/996331)
 *  - Liquor → outside GST, charge state VAT (rate stored on rule)
 *
 * Place-of-supply logic:
 *  - Supplier state == recipient state → CGST + SGST (intra-state)
 *  - Supplier state != recipient state → IGST (inter-state)
 *  - For accommodation, place of supply = location of property always.
 *
 * Lookup priority:
 *  1. Property-specific active rule with matching scope and conditions
 *  2. Tenant-default rule
 *  3. Fall back to hardcoded India defaults if nothing seeded.
 */
class TaxEngine
{
    /**
     * Resolve the applicable tax rule for a given line context.
     *
     * @param array{
     *   scope: string,
     *   property_id?: int|null,
     *   line_amount?: float,
     *   per_unit_rate?: float,
     *   item_is_alcohol?: bool,
     *   item_is_tobacco?: bool,
     *   override_hsn?: string|null,
     * } $ctx
     */
    public function resolveRule(array $ctx): ?TaxRule
    {
        $scope = $ctx['scope'] ?? TaxRule::SCOPE_OTHER;

        // Auto-route alcohol & tobacco
        if (! empty($ctx['item_is_alcohol'])) $scope = TaxRule::SCOPE_LIQUOR;
        if (! empty($ctx['item_is_tobacco'])) $scope = TaxRule::SCOPE_TOBACCO;

        // Auto-pick F&B variant by hotel declared tariff
        if ($scope === 'fnb') {
            $property = $ctx['property_id'] ? Property::find($ctx['property_id']) : null;
            $declared = (float) ($property?->declared_max_room_tariff ?? 0);
            $scope = $declared >= 7500 ? TaxRule::SCOPE_FNB_WITH_ITC : TaxRule::SCOPE_FNB_NO_ITC;
        }

        // For room scope, narrow by per-night rate threshold
        $perUnit = (float) ($ctx['per_unit_rate'] ?? $ctx['line_amount'] ?? 0);

        $today = Carbon::today();

        $query = TaxRule::query()
            ->where('scope', $scope)
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            });

        if (! empty($ctx['property_id'])) {
            $query->where(function ($q) use ($ctx) {
                $q->where('property_id', $ctx['property_id'])->orWhereNull('property_id');
            });
        }

        if ($scope === TaxRule::SCOPE_ROOM && $perUnit > 0) {
            $query->where(function ($q) use ($perUnit) {
                $q->where(function ($q) use ($perUnit) {
                    $q->whereNull('room_tariff_min')->orWhere('room_tariff_min', '<=', $perUnit);
                })->where(function ($q) use ($perUnit) {
                    $q->whereNull('room_tariff_max')->orWhere('room_tariff_max', '>=', $perUnit);
                });
            });
        }

        return $query->orderBy('priority')->orderByDesc('property_id')->first()
            ?? $this->fallbackRule($scope, $perUnit);
    }

    /**
     * Compute tax breakdown for a single line.
     *
     * @return array{
     *   rule_id: int|null,
     *   hsn_sac_code: string|null,
     *   taxable_amount: float,
     *   cgst_rate: float, cgst_amount: float,
     *   sgst_rate: float, sgst_amount: float,
     *   igst_rate: float, igst_amount: float,
     *   cess_rate: float, cess_amount: float,
     *   total_tax: float,
     *   total_amount: float,
     * }
     */
    public function computeLine(array $ctx, float $taxableAmount, bool $isInterState): array
    {
        $rule = $this->resolveRule(array_merge($ctx, ['line_amount' => $taxableAmount]));

        $cgstR = $sgstR = $igstR = $cessR = 0.0;
        if ($rule) {
            $cessR = (float) $rule->cess_rate;
            if ($isInterState) {
                $igstR = (float) $rule->total_rate;
            } else {
                $cgstR = (float) ($rule->cgst_rate ?: $rule->total_rate / 2);
                $sgstR = (float) ($rule->sgst_rate ?: $rule->total_rate / 2);
            }
        }

        $cgst = round($taxableAmount * $cgstR / 100, 2);
        $sgst = round($taxableAmount * $sgstR / 100, 2);
        $igst = round($taxableAmount * $igstR / 100, 2);
        $cess = round($taxableAmount * $cessR / 100, 2);
        $totalTax = $cgst + $sgst + $igst + $cess;

        return [
            'rule_id'        => $rule?->id,
            'hsn_sac_code'   => $rule?->hsn_sac_code ?? ($ctx['override_hsn'] ?? null),
            'taxable_amount' => round($taxableAmount, 2),
            'cgst_rate'      => $cgstR,
            'cgst_amount'    => $cgst,
            'sgst_rate'      => $sgstR,
            'sgst_amount'    => $sgst,
            'igst_rate'      => $igstR,
            'igst_amount'    => $igst,
            'cess_rate'      => $cessR,
            'cess_amount'    => $cess,
            'total_tax'      => round($totalTax, 2),
            'total_amount'   => round($taxableAmount + $totalTax, 2),
        ];
    }

    /**
     * Hard-coded India defaults — used when nothing's seeded.
     */
    private function fallbackRule(string $scope, float $perUnit): TaxRule
    {
        $r = new TaxRule();
        $r->scope = $scope;
        $r->is_active = true;
        $r->itc_available = true;
        switch ($scope) {
            case TaxRule::SCOPE_ROOM:
                $r->total_rate = $perUnit > 7500 ? 18 : 12;
                $r->cgst_rate = $r->total_rate / 2;
                $r->sgst_rate = $r->total_rate / 2;
                $r->igst_rate = $r->total_rate;
                $r->hsn_sac_code = '996311';
                break;
            case TaxRule::SCOPE_FNB_NO_ITC:
                $r->total_rate = 5;
                $r->cgst_rate = 2.5; $r->sgst_rate = 2.5; $r->igst_rate = 5;
                $r->itc_available = false;
                $r->hsn_sac_code = '996331';
                break;
            case TaxRule::SCOPE_FNB_WITH_ITC:
                $r->total_rate = 18;
                $r->cgst_rate = 9; $r->sgst_rate = 9; $r->igst_rate = 18;
                $r->hsn_sac_code = '996331';
                break;
            case TaxRule::SCOPE_BANQUET:
                $r->total_rate = 18;
                $r->cgst_rate = 9; $r->sgst_rate = 9; $r->igst_rate = 18;
                $r->hsn_sac_code = '996334';
                break;
            case TaxRule::SCOPE_LIQUOR:
                // Outside GST — placeholder VAT 25% (state-specific)
                $r->total_rate = 25; $r->cgst_rate = 0; $r->sgst_rate = 25; $r->igst_rate = 0;
                $r->hsn_sac_code = null;
                break;
            case TaxRule::SCOPE_TOBACCO:
                $r->total_rate = 28;
                $r->cgst_rate = 14; $r->sgst_rate = 14; $r->igst_rate = 28;
                $r->cess_rate = 31; // illustrative
                $r->hsn_sac_code = '2402';
                break;
            default:
                $r->total_rate = 18;
                $r->cgst_rate = 9; $r->sgst_rate = 9; $r->igst_rate = 18;
                $r->hsn_sac_code = '9963';
        }
        return $r;
    }

    /**
     * Determine if a supply is inter-state given supplier and recipient state codes.
     */
    public static function isInterState(?string $supplierStateCode, ?string $recipientStateCode): bool
    {
        if (! $supplierStateCode || ! $recipientStateCode) return false;
        return strtoupper(trim($supplierStateCode)) !== strtoupper(trim($recipientStateCode));
    }

    /**
     * Indian GST state codes lookup.
     */
    public static function indianStateCodes(): array
    {
        return [
            '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
            '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana',
            '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
            '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
            '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
            '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam',
            '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha',
            '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
            '25' => 'Daman & Diu', '26' => 'Dadra & Nagar Haveli',
            '27' => 'Maharashtra', '28' => 'Andhra Pradesh (old)', '29' => 'Karnataka',
            '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala',
            '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman & Nicobar',
            '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
            '97' => 'Other Territory', '99' => 'Centre Jurisdiction',
        ];
    }

    /**
     * Indian financial year of a date — e.g. 2026-04-05 → "2026-27".
     */
    public static function financialYear(?Carbon $date = null): string
    {
        $d = $date ?: Carbon::now();
        $start = $d->month >= 4 ? $d->year : $d->year - 1;
        return $start . '-' . str_pad((string)(($start + 1) % 100), 2, '0', STR_PAD_LEFT);
    }
}
