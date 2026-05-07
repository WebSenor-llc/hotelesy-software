<?php

namespace App\Livewire\Setup;

use App\Models\POS\Outlet;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class PosOutlets extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public string $code = '';
    public string $name = '';
    public string $type = 'restaurant';
    public ?string $open_time = null;
    public ?string $close_time = null;
    public float $service_charge_percent = 0;
    public bool $is_active = true;

    public function startCreate(): void
    {
        $this->reset(['editId','code','name','open_time','close_time']);
        $this->type = 'restaurant';
        $this->service_charge_percent = 0;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $o = Outlet::findOrFail($id);
        $this->editId = $id;
        foreach (['code','name','type','open_time','close_time','service_charge_percent','is_active'] as $f) {
            $this->$f = $o->$f;
        }
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'code'   => 'required|string|max:20',
            'name'   => 'required|string|max:255',
            'type'   => 'required|in:restaurant,bar,cafe,room_service,banquet,pool,spa,other',
            'open_time'  => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'service_charge_percent' => 'numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $data['property_id'] = app(TenantContext::class)->propertyId();

        if ($this->editId) {
            Outlet::findOrFail($this->editId)->update($data);
            session()->flash('success', "Outlet '{$data['name']}' updated.");
        } else {
            Outlet::create($data);
            session()->flash('success', "Outlet '{$data['name']}' created.");
        }
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $o = Outlet::findOrFail($id);
        $name = $o->name;
        $o->delete();
        session()->flash('success', "Outlet '$name' deleted.");
    }

    public function render()
    {
        $outlets = Outlet::where('property_id', app(TenantContext::class)->propertyId())
            ->orderBy('name')->get();
        return view('livewire.setup.pos-outlets', compact('outlets'));
    }
}
