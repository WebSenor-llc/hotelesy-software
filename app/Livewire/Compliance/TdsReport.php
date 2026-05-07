<?php

namespace App\Livewire\Compliance;

use App\Models\Payment;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class TdsReport extends Component
{
    public string $fromDate;
    public string $toDate;
    public ?int $companyId = null;
    public string $sectionFilter = '';

    public function mount(): void
    {
        $this->fromDate = today()->startOfMonth()->toDateString();
        $this->toDate = today()->toDateString();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->loadRows();
        $filename = 'tds-report-' . $this->fromDate . '-to-' . $this->toDate . '.csv';

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Date', 'Company', 'GSTIN', 'PAN', 'Payment receipt',
                'Section', 'Gross amount', 'TDS rate %', 'TDS amount', 'Net paid',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['date'],
                    $r['company'],
                    $r['gstin'],
                    $r['pan'],
                    $r['receipt'],
                    $r['section'],
                    number_format($r['gross'], 2, '.', ''),
                    $r['rate'],
                    number_format($r['tds_amount'], 2, '.', ''),
                    number_format($r['net'], 2, '.', ''),
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function printNow(): void
    {
        $this->dispatch('print-tds-report');
    }

    private function loadRows(): array
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();

        $from = Carbon::parse($this->fromDate)->startOfDay();
        $to = Carbon::parse($this->toDate)->endOfDay();

        $payments = Payment::query()
            ->with('company')
            ->where('property_id', $property->id)
            ->where('tds_amount', '>', 0)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->sectionFilter, fn ($q) => $q->where('tds_section', $this->sectionFilter))
            ->orderBy('payment_date')
            ->get();

        return $payments->map(function (Payment $p) {
            $rate = $p->tds_section === '194J' ? 10.0 : 2.0;
            $gross = (float) $p->amount + (float) $p->tds_amount;
            return [
                'date' => $p->payment_date?->format('d M Y'),
                'company' => $p->company?->name ?? '—',
                'gstin' => $p->company?->gst_number ?? '',
                'pan' => $p->company?->pan_number ?? '',
                'receipt' => $p->receipt_number,
                'section' => $p->tds_section ?? '194I',
                'gross' => $gross,
                'rate' => $rate,
                'tds_amount' => (float) $p->tds_amount,
                'net' => (float) $p->amount,
            ];
        })->all();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $rows = $this->loadRows();

        $totalTds = collect($rows)->sum('tds_amount');
        $totalGross = collect($rows)->sum('gross');

        $companies = \App\Models\Company::where('is_active', true)->orderBy('name')->get();

        return view('livewire.compliance.tds-report', [
            'rows' => $rows,
            'totalTds' => $totalTds,
            'totalGross' => $totalGross,
            'companies' => $companies,
            'property' => $ctx->property(),
        ]);
    }
}
