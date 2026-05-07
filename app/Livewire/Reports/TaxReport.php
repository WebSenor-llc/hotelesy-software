<?php

namespace App\Livewire\Reports;

use App\Models\FolioCharge;
use App\Models\Property;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class TaxReport extends Component
{
    public string $startDate;
    public string $endDate;

    public function mount(): void
    {
        $this->startDate = today()->copy()->startOfMonth()->toDateString();
        $this->endDate   = today()->toDateString();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $property = $ctx->property();

        $start = Carbon::parse($this->startDate);
        $end   = Carbon::parse($this->endDate);

        // Pull each charge with its tax breakdown json
        $charges = FolioCharge::where('property_id', $propertyId)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_voided', false)
            ->with('folio.reservation')
            ->orderBy('business_date')
            ->get();

        // Bucket by tax slab (rate) — read from tax_breakdown json or compute
        $bySlab = [];
        foreach ($charges as $c) {
            $rate = is_array($c->tax_breakdown) && isset($c->tax_breakdown['rate'])
                ? (float) $c->tax_breakdown['rate']
                : ($c->amount > 0 ? round(($c->tax_amount / $c->amount) * 100, 0) : 0);
            $key = (string) $rate;
            if (!isset($bySlab[$key])) {
                $bySlab[$key] = ['rate' => $rate, 'taxable' => 0, 'tax' => 0, 'cgst' => 0, 'sgst' => 0, 'count' => 0];
            }
            $bySlab[$key]['taxable'] += (float) $c->amount;
            $bySlab[$key]['tax']     += (float) $c->tax_amount;
            $bySlab[$key]['cgst']    += (float) $c->tax_amount / 2; // intra-state assumption
            $bySlab[$key]['sgst']    += (float) $c->tax_amount / 2;
            $bySlab[$key]['count']++;
        }
        ksort($bySlab);

        $totals = [
            'taxable' => array_sum(array_column($bySlab, 'taxable')),
            'tax'     => array_sum(array_column($bySlab, 'tax')),
            'cgst'    => array_sum(array_column($bySlab, 'cgst')),
            'sgst'    => array_sum(array_column($bySlab, 'sgst')),
            'count'   => count($charges),
        ];
        $totals['total'] = $totals['taxable'] + $totals['tax'];

        // B2B vs B2C count (rough — based on whether reservation has company_id)
        $b2bCount = $charges->filter(fn ($c) => $c->folio?->company_id || $c->folio?->reservation?->company_id)->count();

        return view('livewire.reports.tax-report', [
            'charges' => $charges,
            'bySlab'  => $bySlab,
            'totals'  => $totals,
            'property'=> $property,
            'b2bCount'=> $b2bCount,
            'b2cCount'=> $charges->count() - $b2bCount,
            'start'   => $start,
            'end'     => $end,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $start = Carbon::parse($this->startDate);
        $end   = Carbon::parse($this->endDate);

        $charges = FolioCharge::where('property_id', $propertyId)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_voided', false)
            ->with('folio.reservation')
            ->orderBy('business_date')
            ->get();

        $filename = "tax_report_{$this->startDate}_to_{$this->endDate}.csv";
        return response()->streamDownload(function () use ($charges) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date','Folio','Category','Description','Taxable','GST rate %','CGST','SGST','Tax total','Net']);
            foreach ($charges as $c) {
                $rate = is_array($c->tax_breakdown) && isset($c->tax_breakdown['rate'])
                    ? (float) $c->tax_breakdown['rate']
                    : ((float)$c->amount > 0 ? round(((float)$c->tax_amount / (float)$c->amount) * 100, 0) : 0);
                fputcsv($out, [
                    Carbon::parse($c->business_date)->toDateString(),
                    $c->folio?->folio_number,
                    $c->category,
                    $c->description,
                    $c->amount,
                    $rate,
                    round((float)$c->tax_amount / 2, 2),
                    round((float)$c->tax_amount / 2, 2),
                    $c->tax_amount,
                    $c->net_amount,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
