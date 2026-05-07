<?php

namespace App\Livewire\Invoices;

use App\Models\TaxInvoice;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class InvoicesList extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public string $fyFilter = '';
    public string $search = '';
    public string $b2bFilter = 'all'; // all | b2b | b2c

    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFyFilter(): void { $this->resetPage(); }
    public function updatingB2bFilter(): void { $this->resetPage(); }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $tenantId = $ctx->tenantId();

        $q = $ctx->bypass(function () use ($tenantId) {
            $builder = TaxInvoice::query();
            if (auth()->user()?->is_super_admin) {
                // super admin sees everything
            } elseif ($tenantId) {
                $builder->where('tenant_id', $tenantId);
            }
            return $builder;
        });

        if ($this->statusFilter !== 'all') {
            $q->where('status', $this->statusFilter);
        }
        if ($this->fyFilter) {
            $q->where('financial_year', $this->fyFilter);
        }
        if ($this->b2bFilter === 'b2b') {
            $q->whereNotNull('recipient_gstin');
        } elseif ($this->b2bFilter === 'b2c') {
            $q->whereNull('recipient_gstin');
        }
        if ($s = trim($this->search)) {
            $q->where(function ($x) use ($s) {
                $x->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('recipient_name', 'like', "%{$s}%")
                  ->orWhere('recipient_gstin', 'like', "%{$s}%");
            });
        }

        $invoices = $q->orderByDesc('invoice_date')->orderByDesc('id')->paginate(50);

        $stats = $ctx->bypass(function () use ($tenantId) {
            $base = TaxInvoice::query();
            if (! auth()->user()?->is_super_admin && $tenantId) $base->where('tenant_id', $tenantId);

            return [
                'count'  => (clone $base)->count(),
                'issued' => (clone $base)->where('status', 'issued')->count(),
                'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
                'b2b'    => (clone $base)->whereNotNull('recipient_gstin')->count(),
                'total_value' => (float) (clone $base)->where('status', 'issued')->sum('grand_total'),
                'cgst'   => (float) (clone $base)->where('status', 'issued')->sum('cgst_total'),
                'sgst'   => (float) (clone $base)->where('status', 'issued')->sum('sgst_total'),
                'igst'   => (float) (clone $base)->where('status', 'issued')->sum('igst_total'),
            ];
        });

        $financialYears = $ctx->bypass(fn () => TaxInvoice::query()
            ->when(! auth()->user()?->is_super_admin && $tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->select('financial_year')->distinct()->orderByDesc('financial_year')->pluck('financial_year'));

        return view('livewire.invoices.list', [
            'invoices' => $invoices,
            'stats'    => $stats,
            'financialYears' => $financialYears,
        ]);
    }
}
