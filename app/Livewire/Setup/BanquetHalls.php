<?php

namespace App\Livewire\Setup;

use App\Models\Banquet\BanquetHall;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class BanquetHalls extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public string $code = '';
    public string $name = '';
    public ?string $description = null;
    public ?int $area_sqft = null;
    public ?int $theatre_capacity = null;
    public ?int $classroom_capacity = null;
    public ?int $cluster_capacity = null;
    public ?int $banquet_capacity = null;
    public float $hourly_rate = 0;
    public float $half_day_rate = 0;
    public float $full_day_rate = 0;
    public bool $is_active = true;

    public function startCreate(): void
    {
        $this->reset(['editId','code','name','description','area_sqft','theatre_capacity','classroom_capacity','cluster_capacity','banquet_capacity']);
        $this->hourly_rate = 0; $this->half_day_rate = 0; $this->full_day_rate = 0;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $h = BanquetHall::findOrFail($id);
        $this->editId = $id;
        foreach (['code','name','description','area_sqft','theatre_capacity','classroom_capacity','cluster_capacity','banquet_capacity','hourly_rate','half_day_rate','full_day_rate','is_active'] as $f) {
            $this->$f = $h->$f;
        }
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'area_sqft' => 'nullable|integer|min:0',
            'theatre_capacity'   => 'nullable|integer|min:0',
            'classroom_capacity' => 'nullable|integer|min:0',
            'cluster_capacity'   => 'nullable|integer|min:0',
            'banquet_capacity'   => 'nullable|integer|min:0',
            'hourly_rate'   => 'numeric|min:0',
            'half_day_rate' => 'numeric|min:0',
            'full_day_rate' => 'numeric|min:0',
            'is_active' => 'boolean',
        ]);
        $data['property_id'] = app(TenantContext::class)->propertyId();

        if ($this->editId) BanquetHall::findOrFail($this->editId)->update($data);
        else BanquetHall::create($data);
        session()->flash('success', "Hall '{$data['name']}' saved.");
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $h = BanquetHall::findOrFail($id);
        $name = $h->name;
        $h->delete();
        session()->flash('success', "Hall '$name' deleted.");
    }

    public function render()
    {
        $halls = BanquetHall::where('property_id', app(TenantContext::class)->propertyId())->orderBy('name')->get();
        return view('livewire.setup.banquet-halls', compact('halls'));
    }
}
