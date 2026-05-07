<?php

namespace App\Livewire\Super;

use App\Models\SubscriptionPlan;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class PlansList extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public float $price_monthly = 0;
    public float $price_yearly = 0;
    public string $billing_currency = 'INR';
    public int $max_properties = 1;
    public int $max_rooms = 50;
    public int $max_users = 5;
    public int $trial_days = 0;
    public bool $is_active = true;

    public bool $f_channel_manager = false;
    public bool $f_pos = true;
    public bool $f_banquet = false;
    public bool $f_compliance = true;
    public bool $f_revenue = false;
    public bool $f_reviews = true;

    public function startCreate(): void
    {
        $this->reset();
        $this->billing_currency = 'INR';
        $this->max_properties = 1;
        $this->max_rooms = 50;
        $this->max_users = 5;
        $this->is_active = true;
        $this->f_pos = true;
        $this->f_compliance = true;
        $this->f_reviews = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);
        $p = SubscriptionPlan::findOrFail($id);
        $this->editingId = $p->id;
        $this->code = $p->code;
        $this->name = $p->name;
        $this->price_monthly = (float) $p->price_monthly;
        $this->price_yearly = (float) $p->price_yearly;
        $this->billing_currency = $p->billing_currency;
        $this->max_properties = (int) $p->max_properties;
        $this->max_rooms = (int) $p->max_rooms;
        $this->max_users = (int) $p->max_users;
        $this->trial_days = (int) $p->trial_days;
        $this->is_active = (bool) $p->is_active;
        $features = $p->features ?? [];
        $this->f_channel_manager = (bool) ($features['channel_manager'] ?? false);
        $this->f_pos             = (bool) ($features['pos'] ?? false);
        $this->f_banquet         = (bool) ($features['banquet'] ?? false);
        $this->f_compliance      = (bool) ($features['compliance'] ?? false);
        $this->f_revenue         = (bool) ($features['revenue'] ?? false);
        $this->f_reviews         = (bool) ($features['reviews'] ?? false);
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->reset(['editingId']);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        $rules = [
            'code' => 'required|string|max:50|unique:subscription_plans,code' . ($this->editingId ? ',' . $this->editingId : ''),
            'name' => 'required|string|max:120',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'billing_currency' => 'required|string|size:3',
            'max_properties' => 'required|integer|min:1',
            'max_rooms' => 'required|integer|min:1',
            'max_users' => 'required|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];

        $this->validate($rules);

        $payload = [
            'code' => $this->code,
            'name' => $this->name,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'billing_currency' => strtoupper($this->billing_currency),
            'max_properties' => $this->max_properties,
            'max_rooms' => $this->max_rooms,
            'max_users' => $this->max_users,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'features' => [
                'channel_manager' => $this->f_channel_manager,
                'pos'             => $this->f_pos,
                'banquet'         => $this->f_banquet,
                'compliance'      => $this->f_compliance,
                'revenue'         => $this->f_revenue,
                'reviews'         => $this->f_reviews,
            ],
        ];

        if ($this->editingId) {
            SubscriptionPlan::findOrFail($this->editingId)->update($payload);
            session()->flash('success', "Plan {$payload['name']} updated.");
        } else {
            SubscriptionPlan::create($payload);
            session()->flash('success', "Plan {$payload['name']} created.");
        }

        $this->showForm = false;
        $this->reset(['editingId']);
    }

    public function render()
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        $plans = app(TenantContext::class)->bypass(
            fn () => SubscriptionPlan::orderBy('price_monthly')->get()
        );

        return view('livewire.super.plans-list', compact('plans'));
    }
}
