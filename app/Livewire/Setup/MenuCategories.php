<?php

namespace App\Livewire\Setup;

use App\Models\POS\MenuCategory;
use App\Models\POS\Outlet;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class MenuCategories extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public ?int $outlet_id = null;
    public string $name = '';
    public ?string $kot_printer = null;
    public bool $is_liquor = false;
    public int $display_order = 0;
    public bool $is_active = true;

    /** Filter by outlet on the list view. */
    public ?int $filterOutlet = null;

    public function startCreate(): void
    {
        $this->reset(['editId','outlet_id','name','kot_printer']);
        $this->is_liquor = false;
        $this->display_order = 0;
        $this->is_active = true;
        if ($this->filterOutlet) $this->outlet_id = $this->filterOutlet;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $c = MenuCategory::findOrFail($id);
        $this->editId = $id;
        foreach (['outlet_id','name','kot_printer','is_liquor','display_order','is_active'] as $f) {
            $this->$f = $c->$f;
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
            'outlet_id'     => 'nullable|exists:pos_outlets,id',
            'name'          => 'required|string|max:100',
            'kot_printer'   => 'nullable|string|max:100',
            'is_liquor'     => 'boolean',
            'display_order' => 'integer|min:0|max:9999',
            'is_active'     => 'boolean',
        ]);

        $data['property_id'] = app(TenantContext::class)->propertyId();

        if ($this->editId) {
            MenuCategory::findOrFail($this->editId)->update($data);
            session()->flash('success', "Category '{$data['name']}' updated.");
        } else {
            MenuCategory::create($data);
            session()->flash('success', "Category '{$data['name']}' created.");
        }
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $c = MenuCategory::findOrFail($id);
        $name = $c->name;
        $c->delete();
        session()->flash('success', "Category '$name' deleted.");
    }

    public function render()
    {
        $propertyId = app(TenantContext::class)->propertyId();

        $query = MenuCategory::where('property_id', $propertyId);
        if ($this->filterOutlet) $query->where('outlet_id', $this->filterOutlet);
        $categories = $query->with('outlet')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $outlets = Outlet::where('property_id', $propertyId)->orderBy('name')->get();

        return view('livewire.setup.menu-categories', compact('categories','outlets'));
    }
}
