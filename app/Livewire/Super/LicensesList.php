<?php

namespace App\Livewire\Super;

use App\Models\License;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class LicensesList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $planFilter = 'all';

    public function updating($field): void
    {
        if (in_array($field, ['search', 'statusFilter', 'planFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'planFilter']);
        $this->statusFilter = 'all';
        $this->planFilter = 'all';
    }

    public function render()
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        return app(TenantContext::class)->bypass(function () {
            $query = License::with(['tenant', 'plan'])->orderByDesc('issued_at');

            if ($this->search !== '') {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('license_key', 'like', $term)
                      ->orWhereHas('tenant', function ($t) use ($term) {
                          $t->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term)
                            ->orWhere('owner_email', 'like', $term);
                      });
                });
            }

            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }

            if ($this->planFilter !== 'all') {
                $query->whereHas('plan', fn ($q) => $q->where('code', $this->planFilter));
            }

            $licenses = $query->paginate(25);

            $plans = \App\Models\SubscriptionPlan::orderBy('price_monthly')->get();

            return view('livewire.super.licenses-list', [
                'licenses' => $licenses,
                'plans'    => $plans,
                'statuses' => ['all', 'trial', 'active', 'past_due', 'suspended', 'expired', 'cancelled'],
            ]);
        });
    }
}
