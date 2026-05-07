<?php

namespace App\Livewire\Compliance;

use App\Models\Compliance\EInvoice;
use App\Models\Folio;
use App\Services\Compliance\EInvoiceService;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class EInvoices extends Component
{
    public string $fromDate;
    public string $toDate;
    public string $statusFilter = '';

    public ?int $cancellingId = null;
    public string $cancelReason = '';

    public function mount(): void
    {
        $this->fromDate = today()->subDays(30)->toDateString();
        $this->toDate = today()->toDateString();
    }

    public function generateForFolio(int $folioId): void
    {
        $ctx = app(TenantContext::class);
        $folio = Folio::where('property_id', $ctx->propertyId())->findOrFail($folioId);

        $svc = app(EInvoiceService::class);
        if (!$svc->shouldGenerate($folio)) {
            session()->flash('error', 'E-invoice not required (property turnover < ₹5cr or no buyer GSTIN).');
            return;
        }

        $row = $svc->generate($folio);
        if ($row) {
            session()->flash('success', "E-invoice generated. IRN: " . substr($row->irn, 0, 16) . '…');
        } else {
            session()->flash('error', 'Failed to generate e-invoice.');
        }
    }

    public function startCancel(int $id): void
    {
        $this->cancellingId = $id;
        $this->cancelReason = '';
    }

    public function cancelCancel(): void
    {
        $this->cancellingId = null;
    }

    public function confirmCancel(): void
    {
        $ctx = app(TenantContext::class);
        $row = EInvoice::where('property_id', $ctx->propertyId())->findOrFail($this->cancellingId);

        $ok = app(EInvoiceService::class)->cancel($row, $this->cancelReason);
        if ($ok) {
            session()->flash('success', 'E-invoice cancelled (IRN voided).');
        } else {
            session()->flash('error', 'Cannot cancel: outside 24h window or already cancelled.');
        }

        $this->cancellingId = null;
        $this->cancelReason = '';
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();

        $from = Carbon::parse($this->fromDate)->startOfDay();
        $to = Carbon::parse($this->toDate)->endOfDay();

        $rows = EInvoice::query()
            ->with(['folio.company', 'folio.reservation'])
            ->where('property_id', $property->id)
            ->whereBetween('created_at', [$from, $to])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('generated_at')
            ->get();

        // Folios eligible but not yet generated:
        //   - has a company,
        //   - that company has a GSTIN,
        //   - property is in the >= 5cr turnover bucket,
        //   - no IRN already generated.
        $eligibleFolios = collect();
        if ($property->einvoice_required) {
            $eligibleFolios = Folio::query()
                ->with(['company', 'reservation'])
                ->where('property_id', $property->id)
                ->whereNotNull('company_id')
                ->whereHas('company', fn ($q) => $q->whereNotNull('gst_number'))
                ->whereBetween('created_at', [$from, $to])
                ->whereNotIn('id', $rows->pluck('folio_id'))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        return view('livewire.compliance.e-invoices', [
            'rows' => $rows,
            'eligibleFolios' => $eligibleFolios,
            'property' => $property,
        ]);
    }
}
