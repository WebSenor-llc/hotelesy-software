<?php

namespace App\Livewire\Store;

use App\Models\Store\StoreCategory;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Categories extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public string $name = '';
    public string $type = 'raw_material';
    public bool $is_active = true;

    public function startCreate(): void
    {
        $this->reset(['editId','name']);
        $this->type = 'raw_material';
        $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $c = StoreCategory::findOrFail($id);
        $this->editId = $id;
        foreach (['name','type','is_active'] as $f) {
            $this->$f = $c->$f;
        }
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:raw_material,beverage,liquor,housekeeping,engineering,stationery,other',
            'is_active' => 'boolean',
        ]);
        $data['property_id'] = app(TenantContext::class)->propertyId();

        if ($this->editId) StoreCategory::findOrFail($this->editId)->update($data);
        else StoreCategory::create($data);
        session()->flash('success', "Category '{$data['name']}' saved.");
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $c = StoreCategory::findOrFail($id);
        $name = $c->name;
        $c->delete();
        session()->flash('success', "Category '$name' deleted.");
    }

    public function render()
    {
        $categories = StoreCategory::where('property_id', app(TenantContext::class)->propertyId())
            ->orderBy('type')->orderBy('name')->get();
        return view('livewire.store.categories', compact('categories'));
    }
}
