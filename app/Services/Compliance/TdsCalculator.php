<?php

namespace App\Services\Compliance;

use App\Models\Company;
use App\Models\Folio;

/**
 * Section 194-I (rent on hotel accommodation): 2% TDS once threshold crossed.
 * Section 194-J (professional / banquet & conference services): 10% TDS.
 *
 * Threshold check (single ₹30k or annual ₹1L) is the responsibility
 * of the company-side accounting workflow — here we compute the
 * deductible amount given a folio + company context.
 */
class TdsCalculator
{
    public const SECTION_194I = '194I';
    public const SECTION_194J = '194J';

    public const RATE_194I = 2.0;
    public const RATE_194J = 10.0;

    public const SINGLE_TXN_THRESHOLD = 30000.0;
    public const ANNUAL_THRESHOLD = 100000.0;

    /**
     * Returns ['rate'=>float,'amount'=>float,'section'=>string,'rent_base'=>float,'service_base'=>float].
     * rent_base = room + package charges; service_base = banquet/conference/professional.
     */
    public function calculateForFolio(Folio $folio, ?Company $company = null): array
    {
        $company = $company ?? $folio->company;
        if (!$company || !$company->tds_applicable) {
            return [
                'rate' => 0.0, 'amount' => 0.0,
                'section' => null,
                'rent_base' => 0.0, 'service_base' => 0.0,
            ];
        }

        $folio->loadMissing('charges');
        $rentBase = 0.0;
        $serviceBase = 0.0;

        foreach ($folio->charges()->where('is_voided', false)->get() as $c) {
            $category = strtolower((string) ($c->category ?? ''));
            $description = strtolower((string) ($c->description ?? ''));
            $base = (float) ($c->amount ?? $c->net_amount ?? 0);

            $isService = str_contains($category, 'banquet')
                || str_contains($category, 'conference')
                || str_contains($description, 'banquet')
                || str_contains($description, 'conference');

            if ($isService) {
                $serviceBase += $base;
            } else {
                // room, package, tariff and unspecified hotel-stay charges → §194I rent
                $rentBase += $base;
            }
        }

        $tds194I = round($rentBase * self::RATE_194I / 100, 2);
        $tds194J = round($serviceBase * self::RATE_194J / 100, 2);
        $total = $tds194I + $tds194J;

        // Pick the predominant section for reporting; if both, use 194I as primary tag
        // and keep both bases in the returned struct.
        $section = $serviceBase > 0 && $rentBase === 0.0 ? self::SECTION_194J : self::SECTION_194I;

        return [
            'rate' => $serviceBase > 0 && $rentBase === 0.0 ? self::RATE_194J : self::RATE_194I,
            'amount' => $total,
            'amount_194i' => $tds194I,
            'amount_194j' => $tds194J,
            'section' => $section,
            'rent_base' => round($rentBase, 2),
            'service_base' => round($serviceBase, 2),
        ];
    }
}
