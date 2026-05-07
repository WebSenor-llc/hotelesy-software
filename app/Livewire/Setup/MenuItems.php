<?php

namespace App\Livewire\Setup;

use App\Models\POS\MenuCategory;
use App\Models\POS\MenuItem;
use App\Models\POS\Outlet;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class MenuItems extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public ?int $category_id = null;
    public string $code = '';
    public string $name = '';
    public ?string $description = null;
    public float $price = 0;
    public float $cost = 0;
    public float $tax_percent = 5;
    public string $food_type = 'veg';
    public bool $is_taxable = true;
    public bool $available = true;
    public bool $is_active = true;

    /** Filters for list view. */
    public ?int $filterOutlet = null;
    public ?int $filterCategory = null;

    public function startCreate(): void
    {
        $this->reset(['editId','code','name','description']);
        $this->category_id = $this->filterCategory;
        $this->price = 0; $this->cost = 0; $this->tax_percent = 5;
        $this->food_type = 'veg';
        $this->is_taxable = true; $this->available = true; $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $i = app(TenantContext::class)->bypass(fn () => MenuItem::findOrFail($id));
        $this->editId = $id;

        // Coerce DB nulls to safe defaults so typed Livewire properties don't TypeError silently
        $this->category_id = $i->category_id;
        $this->code        = (string) ($i->code ?? '');
        $this->name        = (string) ($i->name ?? '');
        $this->description = $i->description;            // ?string → safe to assign null
        $this->price       = (float) ($i->price ?? 0);
        $this->cost        = (float) ($i->cost ?? 0);
        $this->tax_percent = (float) ($i->tax_percent ?? 5);
        $this->food_type   = (string) ($i->food_type ?? 'veg');
        $this->is_taxable  = (bool) ($i->is_taxable ?? true);
        $this->available   = (bool) ($i->available ?? true);
        $this->is_active   = (bool) ($i->is_active ?? true);

        $this->resetErrorBag();
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
            'category_id' => 'required|exists:pos_menu_categories,id',
            'code'        => 'required|string|max:30',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price'       => 'required|numeric|min:0',
            'cost'        => 'numeric|min:0',
            'tax_percent' => 'numeric|min:0|max:100',
            'food_type'   => 'required|in:veg,non_veg,egg,jain,beverage,liquor',
            'is_taxable'  => 'boolean',
            'available'   => 'boolean',
            'is_active'   => 'boolean',
        ]);

        $ctx = app(TenantContext::class);
        $data['property_id'] = $ctx->propertyId();
        $data['tenant_id']   = $ctx->tenantId();
        $data['code']        = strtoupper(trim($data['code']));

        try {
            if ($this->editId) {
                $ctx->bypass(fn () => MenuItem::findOrFail($this->editId)->update($data));
                session()->flash('success', "Item '{$data['name']}' updated.");
            } else {
                MenuItem::create($data);
                session()->flash('success', "Item '{$data['name']}' created.");
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
        $i = MenuItem::findOrFail($id);
        $name = $i->name;
        $i->delete();
        session()->flash('success', "Item '$name' deleted.");
    }

    public function updatedFilterOutlet(): void { $this->filterCategory = null; }

    public function render()
    {
        $propertyId = app(TenantContext::class)->propertyId();

        $query = MenuItem::where('property_id', $propertyId)->with('category.outlet');
        if ($this->filterCategory) {
            $query->where('category_id', $this->filterCategory);
        } elseif ($this->filterOutlet) {
            $catIds = MenuCategory::where('property_id', $propertyId)
                ->where('outlet_id', $this->filterOutlet)->pluck('id');
            $query->whereIn('category_id', $catIds);
        }
        $items = $query->orderBy('name')->get();

        $outlets = Outlet::where('property_id', $propertyId)->orderBy('name')->get();
        $categoriesQuery = MenuCategory::where('property_id', $propertyId);
        if ($this->filterOutlet) $categoriesQuery->where('outlet_id', $this->filterOutlet);
        $categories = $categoriesQuery->orderBy('name')->get();
        $allCategories = MenuCategory::where('property_id', $propertyId)->with('outlet')->orderBy('name')->get();

        return view('livewire.setup.menu-items', compact('items','outlets','categories','allCategories'));
    }
}
