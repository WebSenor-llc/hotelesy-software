<?php

namespace App\Livewire\Super;

use App\Models\License;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class TenantsList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';

    public function updating($field): void
    {
        if (in_array($field, ['search', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->statusFilter = 'all';
    }

    public function render()
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        return app(TenantContext::class)->bypass(function () {
            $query = Tenant::with(['license.plan'])
                ->withCount('properties')
                ->orderBy('name');

            if ($this->search !== '') {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                      ->orWhere('slug', 'like', $term)
                      ->orWhere('owner_email', 'like', $term);
                });
            }

            if ($this->statusFilter !== 'all') {
                $query->whereHas('license', function ($q) {
                    $q->where('status', $this->statusFilter);
                });
            }

            $tenants = $query->paginate(25);

            // Per-tenant rooms + users counts (efficient batch query).
            $tenantIds = $tenants->pluck('id');
            $roomCounts = Room::whereIn('tenant_id', $tenantIds)
                ->groupBy('tenant_id')
                ->selectRaw('tenant_id, COUNT(*) as c')
                ->pluck('c', 'tenant_id');
            $userCounts = User::whereIn('tenant_id', $tenantIds)
                ->where('is_super_admin', false)
                ->groupBy('tenant_id')
                ->selectRaw('tenant_id, COUNT(*) as c')
                ->pluck('c', 'tenant_id');

            return view('livewire.super.tenants-list', [
                'tenants'    => $tenants,
                'roomCounts' => $roomCounts,
                'userCounts' => $userCounts,
                'statuses'   => ['all', 'trial', 'active', 'past_due', 'suspended', 'expired', 'cancelled'],
            ]);
        });
    }
}
