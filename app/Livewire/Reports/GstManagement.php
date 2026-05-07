<?php

namespace App\Livewire\Reports;

use App\Models\FolioCharge;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class GstManagement extends Component
{
    public string $startDate;
    public string $endDate;
    public string $preset = 'month';

    public function mount(): void
    {
        $this->applyPreset('month');
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $today = today();
        switch ($preset) {
            case 'today':
                $this->startDate = $today->toDateString();
                $this->endDate   = $today->toDateString();
                break;
            case 'yesterday':
                $this->startDate = $today->copy()->subDay()->toDateString();
                $this->endDate   = $today->copy()->subDay()->toDateString();
                break;
            case 'last_month':
                $this->startDate = $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
                $this->endDate   = $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
                break;
            case 'custom':
                // keep whatever the user has set
                if (empty($this->startDate)) {
                    $this->startDate = $today->copy()->startOfMonth()->toDateString();
                }
                if (empty($this->endDate)) {
                    $this->endDate = $today->toDateString();
                }
                break;
            case 'month':
            default:
                $this->startDate = $today->copy()->startOfMonth()->toDateString();
                $this->endDate   = $today->toDateString();
                $this->preset    = 'month';
                break;
        }
    }

    public function updatedStartDate(): void { $this->preset = 'custom'; }
    public function updatedEndDate(): void   { $this->preset = 'custom'; }

    /**
     * Build the dataset shared between render() and exportCsv().
     */
    protected function buildReport(): array
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $property   = $ctx->property();

        $start = Carbon::parse($this->startDate);
        $end   = Carbon::parse($this->endDate);

        $charges = FolioCharge::where('property_id', $propertyId)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_voided', false)
            ->where('category', '!=', 'tax')      // exclude pure tax-only line items
            ->where('category', '!=', 'discount')
            ->where('category', '!=', 'transfer')
            ->where('category', '!=', 'adjustment')
            ->with(['folio.reservation', 'folio.company'])
            ->orderBy('business_date')
            ->get();

        // GSTR-1 summary by tax slab
        $bySlab = [];
        foreach ($charges as $c) {
            $rate = is_array($c->tax_breakdown) && isset($c->tax_breakdown['rate'])
                ? (float) $c->tax_breakdown['rate']
                : ($c->amount > 0 ? round(((float) $c->tax_amount / (float) $c->amount) * 100, 0) : 0);
            $key = (string) $rate;
            if (!isset($bySlab[$key])) {
                $bySlab[$key] = [
                    'rate'    => $rate,
                    'taxable' => 0,
                    'tax'     => 0,
                    'cgst'    => 0,
                    'sgst'    => 0,
                    'igst'    => 0,
                    'count'   => 0,
                ];
            }
            $taxable = (float) $c->amount;
            $tax     = (float) $c->tax_amount;
            $bySlab[$key]['taxable'] += $taxable;
            $bySlab[$key]['tax']     += $tax;
            // Intra-state assumption: equal CGST + SGST split
            $bySlab[$key]['cgst']    += $tax / 2;
            $bySlab[$key]['sgst']    += $tax / 2;
            $bySlab[$key]['count']++;
        }
        ksort($bySlab);

        // HSN summary — group by category + slab (proxy for HSN code)
        $hsnMap = [
            'room'           => ['hsn' => '996311', 'desc' => 'Room accommodation'],
            'food'           => ['hsn' => '996331', 'desc' => 'Food / restaurant'],
            'beverage'       => ['hsn' => '996331', 'desc' => 'Beverage / restaurant'],
            'mini_bar'       => ['hsn' => '996331', 'desc' => 'Mini-bar consumption'],
            'laundry'        => ['hsn' => '999719', 'desc' => 'Laundry services'],
            'spa'            => ['hsn' => '999722', 'desc' => 'Spa / wellness'],
            'telephone'      => ['hsn' => '998412', 'desc' => 'Telephone'],
            'service_charge' => ['hsn' => '999799', 'desc' => 'Service charge'],
            'extra_bed'      => ['hsn' => '996311', 'desc' => 'Extra bed'],
            'package'        => ['hsn' => '996311', 'desc' => 'Package'],
            'damage'         => ['hsn' => '999799', 'desc' => 'Damage / misc'],
            'misc'           => ['hsn' => '999799', 'desc' => 'Miscellaneous'],
        ];

        $hsn = [];
        foreach ($charges as $c) {
            $rate = is_array($c->tax_breakdown) && isset($c->tax_breakdown['rate'])
                ? (float) $c->tax_breakdown['rate']
                : ($c->amount > 0 ? round(((float) $c->tax_amount / (float) $c->amount) * 100, 0) : 0);
            $meta = $hsnMap[$c->category] ?? ['hsn' => '999799', 'desc' => ucfirst(str_replace('_', ' ', $c->category))];
            $key = $meta['hsn'].'|'.$rate;
            if (!isset($hsn[$key])) {
                $hsn[$key] = [
                    'hsn'     => $meta['hsn'],
                    'desc'    => $meta['desc'],
                    'rate'    => $rate,
                    'qty'     => 0,
                    'taxable' => 0,
                    'cgst'    => 0,
                    'sgst'    => 0,
                    'igst'    => 0,
                    'tax'     => 0,
                ];
            }
            $hsn[$key]['qty']     += (float) $c->quantity;
            $hsn[$key]['taxable'] += (float) $c->amount;
            $hsn[$key]['cgst']    += (float) $c->tax_amount / 2;
            $hsn[$key]['sgst']    += (float) $c->tax_amount / 2;
            $hsn[$key]['tax']     += (float) $c->tax_amount;
        }
        ksort($hsn);

        // B2B vs B2C split
        $b2bCount = 0; $b2bTaxable = 0; $b2bTax = 0;
        $b2cCount = 0; $b2cTaxable = 0; $b2cTax = 0;
        foreach ($charges as $c) {
            $isB2B = $c->folio?->company_id || $c->folio?->reservation?->company_id || !empty($c->folio?->billing_gst);
            if ($isB2B) {
                $b2bCount++;
                $b2bTaxable += (float) $c->amount;
                $b2bTax     += (float) $c->tax_amount;
            } else {
                $b2cCount++;
                $b2cTaxable += (float) $c->amount;
                $b2cTax     += (float) $c->tax_amount;
            }
        }

        $totals = [
            'count'       => $charges->count(),
            'taxable'     => array_sum(array_column($bySlab, 'taxable')),
            'tax'         => array_sum(array_column($bySlab, 'tax')),
            'cgst'        => array_sum(array_column($bySlab, 'cgst')),
            'sgst'        => array_sum(array_column($bySlab, 'sgst')),
            'igst'        => array_sum(array_column($bySlab, 'igst')),
        ];
        $totals['invoice_value'] = $totals['taxable'] + $totals['tax'];
        // Output tax payable (intra-state CGST+SGST = total tax for this property)
        $totals['output_tax_payable'] = $totals['tax'];

        return compact(
            'charges', 'bySlab', 'hsn', 'totals',
            'b2bCount', 'b2bTaxable', 'b2bTax',
            'b2cCount', 'b2cTaxable', 'b2cTax',
            'property', 'start', 'end'
        );
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->buildReport();
        $filename = sprintf('gst-report-%s_to_%s.csv', $this->startDate, $this->endDate);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['GST Management Report']);
            fputcsv($out, ['Property', $data['property']?->name ?? '']);
            fputcsv($out, ['GSTIN',    $data['property']?->gst_number ?? '']);
            fputcsv($out, ['Period',   $data['start']->format('d M Y').' to '.$data['end']->format('d M Y')]);
            fputcsv($out, []);

            // Summary
            fputcsv($out, ['Summary by tax slab']);
            fputcsv($out, ['Rate %', 'Line items', 'Taxable value', 'CGST', 'SGST', 'Total tax', 'Invoice value']);
            foreach ($data['bySlab'] as $slab) {
                fputcsv($out, [
                    $slab['rate'],
                    $slab['count'],
                    number_format($slab['taxable'], 2, '.', ''),
                    number_format($slab['cgst'], 2, '.', ''),
                    number_format($slab['sgst'], 2, '.', ''),
                    number_format($slab['tax'], 2, '.', ''),
                    number_format($slab['taxable'] + $slab['tax'], 2, '.', ''),
                ]);
            }
            fputcsv($out, []);

            // HSN summary
            fputcsv($out, ['HSN summary']);
            fputcsv($out, ['HSN', 'Description', 'Rate %', 'Qty', 'Taxable value', 'CGST', 'SGST', 'Total tax']);
            foreach ($data['hsn'] as $row) {
                fputcsv($out, [
                    $row['hsn'],
                    $row['desc'],
                    $row['rate'],
                    number_format($row['qty'], 2, '.', ''),
                    number_format($row['taxable'], 2, '.', ''),
                    number_format($row['cgst'], 2, '.', ''),
                    number_format($row['sgst'], 2, '.', ''),
                    number_format($row['tax'], 2, '.', ''),
                ]);
            }
            fputcsv($out, []);

            // B2B / B2C
            fputcsv($out, ['B2B vs B2C']);
            fputcsv($out, ['Type', 'Line items', 'Taxable', 'Tax']);
            fputcsv($out, ['B2B', $data['b2bCount'], number_format($data['b2bTaxable'], 2, '.', ''), number_format($data['b2bTax'], 2, '.', '')]);
            fputcsv($out, ['B2C', $data['b2cCount'], number_format($data['b2cTaxable'], 2, '.', ''), number_format($data['b2cTax'], 2, '.', '')]);
            fputcsv($out, []);

            // Totals
            fputcsv($out, ['Totals']);
            fputcsv($out, ['Taxable value',     number_format($data['totals']['taxable'], 2, '.', '')]);
            fputcsv($out, ['CGST',              number_format($data['totals']['cgst'], 2, '.', '')]);
            fputcsv($out, ['SGST',              number_format($data['totals']['sgst'], 2, '.', '')]);
            fputcsv($out, ['Total tax',         number_format($data['totals']['tax'], 2, '.', '')]);
            fputcsv($out, ['Invoice value',     number_format($data['totals']['invoice_value'], 2, '.', '')]);
            fputcsv($out, ['Output tax payable',number_format($data['totals']['output_tax_payable'], 2, '.', '')]);
            fputcsv($out, []);

            // Line items
            fputcsv($out, ['Line items']);
            fputcsv($out, ['Date', 'Folio #', 'Invoice GSTIN', 'Category', 'Description', 'Qty', 'Taxable', 'Rate %', 'CGST', 'SGST', 'Tax', 'Total']);
            foreach ($data['charges'] as $c) {
                $rate = is_array($c->tax_breakdown) && isset($c->tax_breakdown['rate'])
                    ? (float) $c->tax_breakdown['rate']
                    : ($c->amount > 0 ? round(((float) $c->tax_amount / (float) $c->amount) * 100, 0) : 0);
                fputcsv($out, [
                    Carbon::parse($c->business_date)->toDateString(),
                    $c->folio?->folio_number,
                    $c->folio?->billing_gst,
                    $c->category,
                    $c->description,
                    number_format((float) $c->quantity, 2, '.', ''),
                    number_format((float) $c->amount, 2, '.', ''),
                    $rate,
                    number_format((float) $c->tax_amount / 2, 2, '.', ''),
                    number_format((float) $c->tax_amount / 2, 2, '.', ''),
                    number_format((float) $c->tax_amount, 2, '.', ''),
                    number_format((float) $c->net_amount, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.gst-management', $this->buildReport());
    }
}
