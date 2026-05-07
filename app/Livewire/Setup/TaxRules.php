<?php

namespace App\Livewire\Setup;

use App\Models\TaxRule;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class TaxRules extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public string $code = '';
    public string $name = '';
    public string $scope = TaxRule::SCOPE_SERVICE;
    public ?string $hsn_sac_code = null;
    public float $total_rate = 18;
    public ?float $cgst_rate = null;
    public ?float $sgst_rate = null;
    public ?float $igst_rate = null;
    public float $cess_rate = 0;
    public ?float $room_tariff_min = null;
    public ?float $room_tariff_max = null;
    public bool $itc_available = true;
    public ?string $description = '';
    public bool $is_active = true;
    public int $priority = 100;

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:60',
            'name' => 'required|string|max:160',
            'scope' => 'required|in:room,fnb_no_itc,fnb_with_itc,banquet,service,liquor,tobacco,other',
            'hsn_sac_code' => 'nullable|string|max:12',
            'total_rate' => 'required|numeric|min:0|max:100',
            'cgst_rate' => 'nullable|numeric|min:0|max:50',
            'sgst_rate' => 'nullable|numeric|min:0|max:50',
            'igst_rate' => 'nullable|numeric|min:0|max:50',
            'cess_rate' => 'nullable|numeric|min:0|max:100',
            'room_tariff_min' => 'nullable|numeric|min:0',
            'room_tariff_max' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'priority' => 'required|integer|min:1|max:9999',
        ];
    }

    public function startCreate(): void
    {
        $this->reset(['editId','code','name','hsn_sac_code','room_tariff_min','room_tariff_max','description']);
        $this->scope = TaxRule::SCOPE_SERVICE;
        $this->total_rate = 18;
        $this->cgst_rate = 9; $this->sgst_rate = 9; $this->igst_rate = 18;
        $this->cess_rate = 0; $this->itc_available = true; $this->is_active = true; $this->priority = 100;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $rule = app(TenantContext::class)->bypass(fn () => TaxRule::findOrFail($id));
        $this->editId = $id;
        foreach (['code','name','scope','hsn_sac_code','total_rate','cgst_rate','sgst_rate','igst_rate','cess_rate','room_tariff_min','room_tariff_max','itc_available','description','is_active','priority'] as $f) {
            $this->{$f} = $rule->{$f};
        }
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editId = null;
    }

    public function save(): void
    {
        $this->validate();
        $tenantId = auth()->user()->tenant_id;
        $payload = [
            'tenant_id' => $tenantId,
            'property_id' => app(TenantContext::class)->propertyId(),
            'code' => $this->code,
            'name' => $this->name,
            'scope' => $this->scope,
            'hsn_sac_code' => $this->hsn_sac_code,
            'total_rate' => $this->total_rate,
            'cgst_rate' => $this->cgst_rate ?? $this->total_rate / 2,
            'sgst_rate' => $this->sgst_rate ?? $this->total_rate / 2,
            'igst_rate' => $this->igst_rate ?? $this->total_rate,
            'cess_rate' => $this->cess_rate ?? 0,
            'room_tariff_min' => $this->room_tariff_min,
            'room_tariff_max' => $this->room_tariff_max,
            'itc_available' => $this->itc_available,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'priority' => $this->priority,
        ];
        app(TenantContext::class)->bypass(function () use ($payload) {
            if ($this->editId) {
                TaxRule::findOrFail($this->editId)->update($payload);
            } else {
                TaxRule::create($payload);
            }
        });
        session()->flash('success', $this->editId ? 'Tax rule updated.' : 'Tax rule created.');
        $this->showForm = false;
        $this->editId = null;
    }

    public function toggleActive(int $id): void
    {
        app(TenantContext::class)->bypass(function () use ($id) {
            $r = TaxRule::findOrFail($id);
            $r->update(['is_active' => ! $r->is_active]);
        });
    }

    public function render()
    {
        $rules = app(TenantContext::class)->bypass(function () {
            return TaxRule::orderBy('scope')->orderBy('priority')->orderBy('total_rate')->get();
        });
        return view('livewire.setup.tax-rules', [
            'rules' => $rules,
            'scopeLabels' => [
                TaxRule::SCOPE_ROOM         => 'Room — accommodation',
                TaxRule::SCOPE_FNB_NO_ITC   => 'F&B 5% (no ITC)',
                TaxRule::SCOPE_FNB_WITH_ITC => 'F&B 18% (with ITC)',
                TaxRule::SCOPE_BANQUET      => 'Banquet / catering',
                TaxRule::SCOPE_SERVICE      => 'Other service (spa/laundry/etc)',
                TaxRule::SCOPE_LIQUOR       => 'Liquor (state VAT)',
                TaxRule::SCOPE_TOBACCO      => 'Tobacco (28% + cess)',
                TaxRule::SCOPE_OTHER        => 'Other',
            ],
        ]);
    }
}
