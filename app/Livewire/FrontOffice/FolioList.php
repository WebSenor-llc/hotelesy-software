<?php

namespace App\Livewire\FrontOffice;

use App\Models\Folio;
use App\Models\TaxInvoice;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class FolioList extends Component
{
    use WithPagination;

    public string $statusFilter = 'open'; // open | settled | closed | all
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = today()->copy()->subDays(30)->toDateString();
        $this->dateTo   = today()->toDateString();
    }

    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingSearch(): void { $this->resetPage(); }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $q = Folio::where('property_id', $propertyId)
            ->with(['reservation','guest']);

        if ($this->statusFilter !== 'all') {
            $q->where('status', $this->statusFilter);
        }

        if ($this->dateFrom) $q->whereDate('created_at', '>=', $this->dateFrom);
        if ($this->dateTo)   $q->whereDate('created_at', '<=', $this->dateTo);

        if ($s = trim($this->search)) {
            $q->where(function ($x) use ($s) {
                $x->where('folio_number', 'like', "%{$s}%")
                  ->orWhere('billing_name', 'like', "%{$s}%")
                  ->orWhere('billing_gst', 'like', "%{$s}%")
                  ->orWhereHas('reservation', fn ($r) => $r->where('reservation_number', 'like', "%{$s}%")
                                                          ->orWhere('guest_name', 'like', "%{$s}%"));
            });
        }

        $folios = $q->orderByDesc('created_at')->paginate(25);

        // Stats
        $base = Folio::where('property_id', $propertyId);
        $stats = [
            'open'     => (clone $base)->where('status', 'open')->count(),
            'closed'   => (clone $base)->where('status', 'closed')->count(),
            'settled'  => (clone $base)->where('status', 'settled')->count(),
            'open_balance_total' => (float) (clone $base)->where('status', 'open')->sum('balance'),
        ];

        // Map folio_id → invoice_id (for the "View invoice" link)
        $invoiceMap = TaxInvoice::where('source_type', \App\Models\Folio::class)
            ->whereIn('source_id', $folios->pluck('id'))
            ->pluck('id', 'source_id');

        return view('livewire.front-office.folio-list', [
            'folios' => $folios,
            'stats' => $stats,
            'invoiceMap' => $invoiceMap,
        ]);
    }
}
