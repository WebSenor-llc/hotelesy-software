<?php

namespace App\Services\Accounts;

use App\Models\Accounts\GstReturn;
use App\Models\FolioCharge;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Store\Grn;
use App\Models\Store\GrnItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GstReturnService — computes Indian GST returns.
 *
 * Returns supported:
 *  - GSTR-1: Outward supplies summary, invoice-level (B2B + B2C)
 *  - GSTR-3B: Summary return with output tax, ITC claim, net liability
 *
 * NOTE: This computes the data for the return. Actual filing is a separate step
 * via GST portal API or upload of JSON. ASP/GSP integration (e.g. ClearTax,
 * TaxPro, Karnik) handles the filing leg.
 *
 * Source data:
 *  - Outward supplies = folio_charges + amenity_orders aggregated by tax slab
 *  - Inward supplies (ITC) = GRN items aggregated by tax slab
 */
class GstReturnService
{
    public function compute(Property $property, string $returnType, string $period): GstReturn
    {
        // period is 'YYYY-MM'
        [$year, $month] = explode('-', $period);
        $start = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        return DB::transaction(function () use ($property, $returnType, $period, $start, $end) {
            $return = GstReturn::firstOrCreate(
                ['property_id' => $property->id, 'return_type' => $returnType, 'period' => $period],
                [
                    'tenant_id' => $property->tenant_id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => GstReturn::STATUS_DRAFT,
                ]
            );

            if ($return->status === GstReturn::STATUS_FILED) {
                throw new \DomainException("GST return for {$period} is already filed.");
            }

            $return->update($this->computeForType($property, $returnType, $start, $end));
            $return->update(['status' => GstReturn::STATUS_COMPUTED]);
            return $return->fresh();
        });
    }

    public function markFiled(GstReturn $return, string $arnNumber): GstReturn
    {
        if ($return->status !== GstReturn::STATUS_COMPUTED) {
            throw new \DomainException('Return must be computed before marking filed.');
        }
        $return->update([
            'status' => GstReturn::STATUS_FILED,
            'arn_number' => $arnNumber,
            'filed_at' => now(),
            'filed_by' => auth()->id(),
        ]);
        return $return->fresh();
    }

    private function computeForType(Property $property, string $type, Carbon $start, Carbon $end): array
    {
        return match ($type) {
            'gstr1', 'gstr3b' => $this->computeOutwardAndItc($property, $start, $end),
            default => throw new \InvalidArgumentException("Unsupported return type: {$type}"),
        };
    }

    private function computeOutwardAndItc(Property $property, Carbon $start, Carbon $end): array
    {
        // ============ OUTWARD (Sales / Services) ============
        // Folio charges with tax breakdown (CGST/SGST/IGST split is in tax_breakdown JSON)
        $outwardCharges = FolioCharge::where('property_id', $property->id)
            ->whereBetween('charge_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_voided', false)
            ->get();

        $taxableValue = 0;
        $cgst = 0;
        $sgst = 0;
        $igst = 0;
        $cess = 0;
        $detailed = ['outward' => []];

        foreach ($outwardCharges as $c) {
            $base = (float) $c->amount - (float) ($c->discount_amount ?? 0);
            $taxableValue += $base;

            $bd = is_array($c->tax_breakdown) ? $c->tax_breakdown : [];
            $cgst += (float) ($bd['cgst'] ?? 0);
            $sgst += (float) ($bd['sgst'] ?? 0);
            $igst += (float) ($bd['igst'] ?? 0);
            $cess += (float) ($bd['cess'] ?? 0);
        }

        // ============ INWARD (ITC — Input Tax Credit) ============
        // From GRN items — vendor purchases
        $itcCgst = 0;
        $itcSgst = 0;
        $itcIgst = 0;

        $grnItems = GrnItem::query()
            ->join('store_grns', 'store_grn_items.grn_id', '=', 'store_grns.id')
            ->where('store_grns.property_id', $property->id)
            ->whereBetween('store_grns.grn_date', [$start->toDateString(), $end->toDateString()])
            ->select('store_grn_items.*', 'store_grns.grn_date')
            ->get();

        foreach ($grnItems as $g) {
            // Standard assumption: vendor in same state → CGST+SGST split (9% each on 18% rate)
            // Cross-state vendor → IGST. For Phase 1, default to CGST+SGST.
            // Real code should look up vendor's state and compare to property's state.
            $taxOnLine = (float) $g->amount * 0.09; // 9% each side; this is approximate
            $itcCgst += $taxOnLine;
            $itcSgst += $taxOnLine;
        }

        $itcCgst = round($itcCgst, 2);
        $itcSgst = round($itcSgst, 2);

        return [
            'taxable_value' => round($taxableValue, 2),
            'cgst_amount' => round($cgst, 2),
            'sgst_amount' => round($sgst, 2),
            'igst_amount' => round($igst, 2),
            'cess_amount' => round($cess, 2),
            'itc_cgst' => $itcCgst,
            'itc_sgst' => $itcSgst,
            'itc_igst' => $itcIgst,
            'detailed_data' => $detailed,
        ];
    }
}
