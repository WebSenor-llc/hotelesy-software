<?php

namespace App\Livewire\Super;

use App\Models\License;
use App\Models\Room;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class TenantDetail extends Component
{
    public Tenant $tenant;

    public bool $showForm = false;
    public string $formMode = 'issue'; // issue | extend | suspend | cancel
    public ?int $planId = null;
    public string $billingCycle = 'monthly';
    public int $extendDays = 7;
    public string $reason = '';
    public ?string $newKey = null;

    public function mount($tenant): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        // Route-model binding may pass a Tenant instance OR an id. Always reload
        // via TenantContext::bypass() so the global TenantScope doesn't filter it out.
        $tenantId = $tenant instanceof Tenant ? $tenant->id : (int) $tenant;

        $this->tenant = app(TenantContext::class)->bypass(
            fn () => Tenant::with(['license.plan'])->findOrFail($tenantId)
        );
    }

    public function startIssue(): void
    {
        $this->formMode = 'issue';
        $defaultPlan = SubscriptionPlan::where('is_active', true)->orderBy('price_monthly')->first();
        $this->planId = optional($defaultPlan)->id;
        // If the cheapest active plan is a trial, default the cycle to trial
        $this->billingCycle = ($defaultPlan && $defaultPlan->isTrial()) ? 'trial' : 'monthly';
        $this->reason = '';
        $this->newKey = null;
        $this->showForm = true;
    }

    public function updatedPlanId($value): void
    {
        // When a trial plan is selected, auto-switch billing cycle to trial.
        $plan = SubscriptionPlan::find($value);
        if ($plan && $plan->isTrial()) {
            $this->billingCycle = 'trial';
        } elseif ($this->billingCycle === 'trial') {
            $this->billingCycle = 'monthly';
        }
    }

    public function startExtend(int $days = 7): void
    {
        $this->formMode = 'extend';
        $this->extendDays = $days;
        $this->reason = '';
        $this->showForm = true;
    }

    public function startSuspend(): void
    {
        $this->formMode = 'suspend';
        $this->reason = '';
        $this->showForm = true;
    }

    public function startCancel(): void
    {
        $this->formMode = 'cancel';
        $this->reason = '';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->reason = '';
    }

    public function reactivate(): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);
        app(TenantContext::class)->bypass(function () {
            $license = $this->tenant->license()->first();
            if (!$license) return;
            $license->update([
                'status'           => License::STATUS_ACTIVE,
                'suspended_at'     => null,
                'suspended_reason' => null,
                'cancelled_at'     => null,
            ]);
        });
        $this->refreshTenant();
        session()->flash('success', 'License reactivated.');
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        $superAdminId = auth()->id();

        app(TenantContext::class)->bypass(function () use ($superAdminId) {
            DB::transaction(function () use ($superAdminId) {
                $license = $this->tenant->license()->first();

                if ($this->formMode === 'issue') {
                    $this->validate([
                        'planId'       => 'required|exists:subscription_plans,id',
                        'billingCycle' => 'required|in:monthly,yearly,lifetime,trial',
                    ]);
                    $plan = SubscriptionPlan::findOrFail($this->planId);

                    // If plan is a trial plan OR billing cycle is "trial",
                    // use the plan's configured trial_days and STATUS_TRIAL.
                    $isTrial = $plan->isTrial() || $this->billingCycle === 'trial';

                    if ($isTrial) {
                        $duration = max(1, (int) ($plan->trial_days ?: 14));
                        $status = License::STATUS_TRIAL;
                        $effectiveCycle = 'trial';
                    } else {
                        $duration = match ($this->billingCycle) {
                            'monthly'  => 30,
                            'yearly'   => 365,
                            'lifetime' => 365 * 50,
                            default    => 30,
                        };
                        $status = License::STATUS_ACTIVE;
                        $effectiveCycle = $this->billingCycle;
                    }

                    $key = License::generateKey();
                    $payload = [
                        'subscription_plan_id' => $plan->id,
                        'license_key'          => $key,
                        'license_key_hash'     => License::hashKey($key),
                        'status'               => $status,
                        'starts_at'            => now(),
                        'expires_at'           => now()->copy()->addDays($duration),
                        'billing_cycle'        => $effectiveCycle,
                        'last_validated_at'    => now(),
                        'issued_by'            => $superAdminId,
                        'issued_at'            => now(),
                        'suspended_at'         => null,
                        'suspended_reason'     => null,
                        'cancelled_at'         => null,
                        'notes'                => $this->reason ?: null,
                    ];
                    if ($license) {
                        $license->update($payload);
                    } else {
                        License::create(['tenant_id' => $this->tenant->id] + $payload);
                    }
                    $this->newKey = $key;
                    return;
                }

                if (!$license) {
                    throw new \RuntimeException('No license to operate on.');
                }

                if ($this->formMode === 'extend') {
                    $this->validate(['extendDays' => 'required|integer|min:1|max:365']);
                    $base = $license->expires_at && $license->expires_at->isFuture()
                        ? $license->expires_at->copy()
                        : now();
                    $license->update([
                        'expires_at' => $base->addDays($this->extendDays),
                        'status'     => $license->status === License::STATUS_EXPIRED
                            ? License::STATUS_ACTIVE
                            : $license->status,
                        'notes'      => trim(($license->notes ? $license->notes . "\n" : '') . "Extended {$this->extendDays}d: {$this->reason}"),
                    ]);
                } elseif ($this->formMode === 'suspend') {
                    $this->validate(['reason' => 'required|string|max:500']);
                    $license->update([
                        'status'           => License::STATUS_SUSPENDED,
                        'suspended_at'     => now(),
                        'suspended_reason' => $this->reason,
                    ]);
                } elseif ($this->formMode === 'cancel') {
                    $this->validate(['reason' => 'required|string|max:500']);
                    $license->update([
                        'status'        => License::STATUS_CANCELLED,
                        'cancelled_at'  => now(),
                        'notes'         => trim(($license->notes ? $license->notes . "\n" : '') . "Cancelled: {$this->reason}"),
                    ]);
                }
            });
        });

        $this->refreshTenant();

        if ($this->formMode !== 'issue') {
            $this->showForm = false;
        }
        session()->flash('success', 'License updated.');
    }

    private function refreshTenant(): void
    {
        $id = $this->tenant->id;
        $this->tenant = app(TenantContext::class)->bypass(
            fn () => Tenant::with(['license.plan'])->findOrFail($id)
        );
    }

    public function render()
    {
        return app(TenantContext::class)->bypass(function () {
            $plans = SubscriptionPlan::where('is_active', true)->orderBy('price_monthly')->get();
            $properties = $this->tenant->properties()->withCount('rooms')->get();
            $usersCount = User::where('tenant_id', $this->tenant->id)->where('is_super_admin', false)->count();
            $roomsCount = Room::where('tenant_id', $this->tenant->id)->count();

            $licenseHistory = License::withTrashed()
                ->where('tenant_id', $this->tenant->id)
                ->with('plan', 'issuedBy')
                ->orderByDesc('issued_at')
                ->get();

            return view('livewire.super.tenant-detail', [
                'plans'          => $plans,
                'properties'     => $properties,
                'usersCount'     => $usersCount,
                'roomsCount'     => $roomsCount,
                'licenseHistory' => $licenseHistory,
            ]);
        });
    }
}
