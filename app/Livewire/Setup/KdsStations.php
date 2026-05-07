<?php

namespace App\Livewire\Setup;

use App\Models\KDS\KdsStation;
use App\Models\POS\Outlet;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class KdsStations extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public ?int $outlet_id = null;
    public string $code = '';
    public string $name = '';
    public string $type = 'hot_kitchen';
    public ?string $printer_ip = null;
    public int $default_prep_minutes = 15;
    public bool $is_active = true;

    public function startCreate(): void
    {
        $this->reset(['editId','outlet_id','code','name','printer_ip']);
        $this->type = 'hot_kitchen';
        $this->default_prep_minutes = 15;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $s = KdsStation::findOrFail($id);
        $this->editId = $id;
        foreach (['outlet_id','code','name','type','printer_ip','default_prep_minutes','is_active'] as $f) {
            $this->$f = $s->$f;
        }
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'outlet_id' => 'required|exists:pos_outlets,id',
            'code'      => 'required|string|max:30',
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:hot_kitchen,cold_kitchen,bar,tandoor,pizza,grill,pickup_window,expo,other',
            'printer_ip' => 'nullable|string|max:255',
            'default_prep_minutes' => 'integer|min:1|max:300',
            'is_active' => 'boolean',
        ]);
        $data['property_id'] = app(TenantContext::class)->propertyId();

        if ($this->editId) KdsStation::findOrFail($this->editId)->update($data);
        else KdsStation::create($data);
        session()->flash('success', "Station '{$data['name']}' saved.");
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $s = KdsStation::findOrFail($id);
        $name = $s->name;
        $s->delete();
        session()->flash('success', "Station '$name' deleted.");
    }

    public function render()
    {
        $propertyId = app(TenantContext::class)->propertyId();
        $stations = KdsStation::where('property_id', $propertyId)->with('outlet')->orderBy('name')->get();
        $outlets = Outlet::where('property_id', $propertyId)->orderBy('name')->get();
        return view('livewire.setup.kds-stations', compact('stations','outlets'));
    }
}
