<?php

namespace Database\Seeders;

use App\Models\TaxRule;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the India GST tax rule matrix as TENANT/PROPERTY-LESS defaults
 * (property_id = null = applies to every property unless overridden).
 *
 * Rates effective FY 2025-26. Update or add property-specific rules as
 * the GST council changes them.
 */
class IndiaTaxRulesSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantContext::class)->bypass(function () {
            $rules = [
                // Room — split by tariff
                ['ROOM_GST_12','Room GST 12% (≤ ₹7,500/night)', TaxRule::SCOPE_ROOM,'996311',12,6,6,12, null,7500.00, true,'GST on accommodation when per-night tariff is ≤ ₹7,500.', 100],
                ['ROOM_GST_18','Room GST 18% (> ₹7,500/night)', TaxRule::SCOPE_ROOM,'996311',18,9,9,18, 7500.01,null,true,'GST on accommodation when per-night tariff exceeds ₹7,500.', 100],

                // F&B
                ['FNB_5_NO_ITC','F&B 5% (no ITC) — declared tariff < ₹7,500', TaxRule::SCOPE_FNB_NO_ITC,'996331',5,2.5,2.5,5,null,null,false,'Standalone restaurants & restaurants in hotels with declared tariff < ₹7,500.', 100],
                ['FNB_18_WITH_ITC','F&B 18% (with ITC) — declared tariff ≥ ₹7,500', TaxRule::SCOPE_FNB_WITH_ITC,'996331',18,9,9,18,null,null,true,'Restaurants in hotels with declared tariff ≥ ₹7,500/night.', 100],

                // Banquet / catering
                ['BANQUET_18','Banquet & outdoor catering 18%', TaxRule::SCOPE_BANQUET,'996334',18,9,9,18,null,null,true,'Banquet halls, outdoor catering, MICE.', 100],

                // Other services (spa, laundry, parking, transport, business centre)
                ['SERVICE_18','Hotel services 18%', TaxRule::SCOPE_SERVICE,'9963',18,9,9,18,null,null,true,'Spa, laundry, parking, transport, business centre, ironing, etc.', 100],

                // Liquor — outside GST. State VAT placeholder; override at property level.
                ['LIQUOR_VAT_25','Liquor (VAT placeholder 25%)', TaxRule::SCOPE_LIQUOR, null,25,0,25,0,null,null,false,'Alcohol is outside GST. Override per state with the correct VAT rate.', 100],

                // Tobacco — 28% GST + cess varies. Cess used here is illustrative.
                ['TOBACCO_28','Tobacco 28% + cess', TaxRule::SCOPE_TOBACCO,'2402',28,14,14,28,null,null,true,'GST on tobacco. Cess varies by product — adjust per item.', 100],
            ];

            foreach ($rules as $r) {
                [
                    $code, $name, $scope, $hsn, $total,
                    $cgst, $sgst, $igst,
                    $minTariff, $maxTariff,
                    $itc, $desc, $priority,
                ] = $r;

                $cessRate = ($code === 'TOBACCO_28') ? 31 : 0;

                TaxRule::updateOrCreate(
                    ['property_id' => null, 'code' => $code],
                    [
                        'tenant_id'         => null,
                        'name'              => $name,
                        'scope'             => $scope,
                        'hsn_sac_code'      => $hsn,
                        'total_rate'        => $total,
                        'cgst_rate'         => $cgst,
                        'sgst_rate'         => $sgst,
                        'igst_rate'         => $igst,
                        'cess_rate'         => $cessRate,
                        'room_tariff_min'   => $minTariff,
                        'room_tariff_max'   => $maxTariff,
                        'itc_available'     => $itc,
                        'description'       => $desc,
                        'effective_from'    => Carbon::create(2025, 4, 1),
                        'effective_to'      => null,
                        'is_active'         => true,
                        'priority'          => $priority,
                    ]
                );
            }

            $this->command?->info('India GST tax rules seeded: ' . count($rules) . ' rules (room x2, F&B x2, banquet, service, liquor, tobacco).');
        });
    }
}
