<?php

namespace App\Livewire\Promotions;

use App\Models\Promotion;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class PromotionsList extends Component
{
    public bool $showForm = false;
    public ?int $editId = null;

    // All nullable to survive DB null + reset() round-trips cleanly
    public ?string $code = '';
    public ?string $name = '';
    public ?string $description = '';
    public string $type = 'percentage';
    public ?float $value = 0;
    public string $applies_to = 'all';
    public ?string $valid_from = '';
    public ?string $valid_to = '';
    public ?int $min_nights = 1;
    public ?float $min_amount = 0;
    public ?int $max_uses = null;
    public ?int $max_per_guest = null;
    public ?int $advance_days = 0;
    public bool $available_direct = true;
    public bool $available_ota = false;
    public bool $available_corporate = true;
    public bool $is_stackable = false;
    public bool $is_active = true;
    public bool $is_public = true;

    private function blankForm(): void
    {
        $this->editId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->type = 'percentage';
        $this->value = 0;
        $this->applies_to = 'all';
        $this->valid_from = today()->toDateString();
        $this->valid_to = today()->copy()->addMonths(3)->toDateString();
        $this->min_nights = 1;
        $this->min_amount = 0;
        $this->max_uses = null;
        $this->max_per_guest = null;
        $this->advance_days = 0;
        $this->available_direct = true;
        $this->available_ota = false;
        $this->available_corporate = true;
        $this->is_stackable = false;
        $this->is_active = true;
        $this->is_public = true;
        $this->resetErrorBag();
    }

    public function startCreate(): void
    {
        $this->blankForm();
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $p = app(TenantContext::class)->bypass(fn () => Promotion::findOrFail($id));
        $this->editId = $id;
        $this->code = $p->code ?? '';
        $this->name = $p->name ?? '';
        $this->description = $p->description ?? '';
        $this->type = $p->type ?? 'percentage';
        $this->value = $p->value !== null ? (float) $p->value : 0;
        $this->applies_to = $p->applies_to ?? 'all';
        $this->min_nights = $p->min_nights !== null ? (int) $p->min_nights : 1;
        $this->min_amount = $p->min_amount !== null ? (float) $p->min_amount : 0;
        $this->max_uses = $p->max_uses !== null ? (int) $p->max_uses : null;
        $this->max_per_guest = $p->max_per_guest !== null ? (int) $p->max_per_guest : null;
        $this->advance_days = $p->advance_days !== null ? (int) $p->advance_days : 0;
        $this->available_direct = (bool) $p->available_direct;
        $this->available_ota = (bool) $p->available_ota;
        $this->available_corporate = (bool) $p->available_corporate;
        $this->is_stackable = (bool) $p->is_stackable;
        $this->is_active = (bool) $p->is_active;
        $this->is_public = (bool) $p->is_public;
        $this->valid_from = $p->valid_from?->toDateString() ?? '';
        $this->valid_to   = $p->valid_to?->toDateString() ?? '';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editId = null;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $data = $this->validate([
            'code'        => 'required|string|max:30',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type'        => 'required|in:percentage,flat_amount,free_night,package_upgrade,complimentary_addon',
            'value'       => 'required|numeric|min:0',
            'applies_to'  => 'required|in:all,room_types,rate_plans,corporate',
            'valid_from'  => 'nullable|date',
            'valid_to'    => 'nullable|date|after_or_equal:valid_from',
            'min_nights'  => 'nullable|integer|min:0',
            'min_amount'  => 'nullable|numeric|min:0',
            'max_uses'    => 'nullable|integer|min:0',
            'max_per_guest'=> 'nullable|integer|min:0',
            'advance_days'=> 'nullable|integer|min:0',
            'available_direct'   => 'boolean',
            'available_ota'      => 'boolean',
            'available_corporate'=> 'boolean',
            'is_stackable'=> 'boolean',
            'is_active'   => 'boolean',
            'is_public'   => 'boolean',
        ]);

        $ctx = app(TenantContext::class);

        // Coerce nullable fields & defaults
        $data['property_id'] = $ctx->propertyId();
        $data['tenant_id']   = $ctx->tenantId();
        $data['code']        = strtoupper(trim($data['code']));
        $data['description'] = $data['description'] ?? null;
        $data['valid_from']  = !empty($data['valid_from']) ? $data['valid_from'] : null;
        $data['valid_to']    = !empty($data['valid_to'])   ? $data['valid_to']   : null;
        $data['min_nights']  = (int) ($data['min_nights']  ?? 0);
        $data['min_amount']  = (float) ($data['min_amount']  ?? 0);
        $data['advance_days']= (int) ($data['advance_days'] ?? 0);

        // Uniqueness check for new promotions: (property_id, code)
        $exists = Promotion::where('property_id', $data['property_id'])
            ->where('code', $data['code'])
            ->when($this->editId, fn ($q) => $q->where('id', '!=', $this->editId))
            ->exists();
        if ($exists) {
            $this->addError('code', "A promotion with code '{$data['code']}' already exists for this property.");
            return;
        }

        try {
            if ($this->editId) {
                Promotion::findOrFail($this->editId)->update($data);
                session()->flash('success', "Promotion {$data['code']} updated.");
            } else {
                $data['created_by'] = auth()->id();
                $data['used_count'] = 0;
                Promotion::create($data);
                session()->flash('success', "Promotion {$data['code']} created.");
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to save: ' . $e->getMessage());
            return;
        }

        $this->showForm = false;
        $this->editId = null;
    }
    public function delete(int $id): void
    {
        $p = Promotion::findOrFail($id);
        if ((int) ($p->used_count ?? 0) > 0) {
            $code = $p->code;
            $count = (int) $p->used_count;
            $p->update(['is_active' => false]);
            session()->flash('success', "Promotion {$code} has {$count} redemptions — deactivated instead of deleted to preserve history.");
            return;
        }
        $p->delete();
        session()->flash('success', 'Deleted.');
    }

    public function render()
    {
        $promotions = Promotion::where('property_id', app(TenantContext::class)->propertyId())
            ->orderByDesc('is_active')->orderBy('valid_to')->get();
        return view('livewire.promotions.list', compact('promotions'));
    }
}
