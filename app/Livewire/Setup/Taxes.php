<?php

namespace App\Livewire\Setup;

use App\Models\Tax;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Taxes extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;
    public string $code = ''; public string $name = '';
    public string $type = 'gst'; public float $rate = 0;
    public bool $is_inclusive = false; public bool $is_compoundable = false;
    public ?float $threshold_min = null; public ?float $threshold_max = null;
    public bool $applies_to_room = true; public bool $applies_to_food = false;
    public bool $applies_to_other = false; public bool $is_active = true;

    public function startCreate(): void {
        $this->reset();
        $this->type='gst'; $this->is_active=true; $this->applies_to_room=true;
        $this->showForm = true;
    }
    public function startEdit(int $id): void {
        $t = Tax::findOrFail($id); $this->editId = $id;
        foreach (['code','name','type','rate','is_inclusive','is_compoundable','threshold_min','threshold_max','applies_to_room','applies_to_food','applies_to_other','is_active'] as $f) $this->$f = $t->$f;
        $this->showForm = true;
    }
    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }
    public function save(): void {
        $data = $this->validate([
            'code'=>'required|string|max:20','name'=>'required|string|max:255',
            'type'=>'required|in:gst,cgst,sgst,igst,service_charge,luxury_tax,other',
            'rate'=>'required|numeric|min:0|max:100',
            'threshold_min'=>'nullable|numeric|min:0','threshold_max'=>'nullable|numeric|min:0',
            'is_inclusive'=>'boolean','is_compoundable'=>'boolean',
            'applies_to_room'=>'boolean','applies_to_food'=>'boolean','applies_to_other'=>'boolean','is_active'=>'boolean',
        ]);
        $data['property_id'] = app(TenantContext::class)->propertyId();
        if ($this->editId) Tax::findOrFail($this->editId)->update($data);
        else Tax::create($data);
        session()->flash('success','Tax saved.'); $this->reset(['editId']);
        $this->showForm = false;
    }
    public function delete(int $id): void { Tax::findOrFail($id)->delete(); session()->flash('success','Tax deleted.'); }

    public function render() {
        $taxes = Tax::where('property_id', app(TenantContext::class)->propertyId())->orderBy('rate')->get();
        return view('livewire.setup.taxes', compact('taxes'));
    }
}
