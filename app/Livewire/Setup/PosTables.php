<?php

namespace App\Livewire\Setup;

use App\Models\POS\Outlet;
use App\Models\POS\PosTable;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class PosTables extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public ?int $outlet_id = null;
    public string $name = '';
    public ?string $section = null;
    public int $capacity = 2;
    public string $status = 'available';
    public bool $is_active = true;

    public ?int $filterOutlet = null;

    public function startCreate(): void
    {
        $this->reset(['editId','name','section']);
        $this->outlet_id = $this->filterOutlet;
        $this->capacity = 2; $this->status = 'available'; $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $t = PosTable::findOrFail($id);
        $this->editId = $id;
        foreach (['outlet_id','name','section','capacity','status','is_active'] as $f) {
            $this->$f = $t->$f;
        }
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'outlet_id' => 'required|exists:pos_outlets,id',
            'name'      => 'required|string|max:50',
            'section'   => 'nullable|string|max:50',
            'capacity'  => 'integer|min:1|max:50',
            'status'    => 'required|in:available,occupied,reserved,cleaning',
            'is_active' => 'boolean',
        ]);
        $data['property_id'] = app(TenantContext::class)->propertyId();

        if ($this->editId) PosTable::findOrFail($this->editId)->update($data);
        else PosTable::create($data);
        session()->flash('success', "Table '{$data['name']}' saved.");
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $t = PosTable::findOrFail($id);
        $name = $t->name;
        $t->delete();
        session()->flash('success', "Table '$name' deleted.");
    }

    public function render()
    {
        $propertyId = app(TenantContext::class)->propertyId();

        $query = PosTable::where('property_id', $propertyId)->with('outlet');
        if ($this->filterOutlet) $query->where('outlet_id', $this->filterOutlet);
        $tables = $query->orderBy('section')->orderBy('name')->get();

        $outlets = Outlet::where('property_id', $propertyId)->orderBy('name')->get();

        return view('livewire.setup.pos-tables', compact('tables','outlets'));
    }
}
