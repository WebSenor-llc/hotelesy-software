<?php

namespace App\Livewire\Setup;

use App\Models\Banquet\BanquetPackage;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class BanquetPackages extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public string $name = '';
    public ?string $description = null;
    public float $per_pax_rate = 0;
    public int $min_pax = 50;
    public string $inclusions_text = '';
    public bool $is_active = true;

    public function startCreate(): void
    {
        $this->reset(['editId','name','description','inclusions_text']);
        $this->per_pax_rate = 0; $this->min_pax = 50;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $p = BanquetPackage::findOrFail($id);
        $this->editId = $id;
        $this->name = $p->name;
        $this->description = $p->description;
        $this->per_pax_rate = (float) $p->per_pax_rate;
        $this->min_pax = (int) $p->min_pax;
        $this->is_active = (bool) $p->is_active;
        $this->inclusions_text = is_array($p->inclusions)
            ? implode("\n", $p->inclusions)
            : (string) $p->inclusions;
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'per_pax_rate' => 'required|numeric|min:0',
            'min_pax' => 'integer|min:1',
            'inclusions_text' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $inclusions = array_values(array_filter(
            array_map('trim', preg_split('/\r?\n/', (string) $this->inclusions_text)),
            fn($l) => $l !== ''
        ));

        $payload = [
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'per_pax_rate' => $data['per_pax_rate'],
            'min_pax'      => $data['min_pax'] ?? 50,
            'inclusions'   => $inclusions,
            'is_active'    => $data['is_active'] ?? true,
            'property_id'  => app(TenantContext::class)->propertyId(),
        ];

        if ($this->editId) BanquetPackage::findOrFail($this->editId)->update($payload);
        else BanquetPackage::create($payload);
        session()->flash('success', "Package '{$payload['name']}' saved.");
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $p = BanquetPackage::findOrFail($id);
        $name = $p->name;
        $p->delete();
        session()->flash('success', "Package '$name' deleted.");
    }

    public function render()
    {
        $packages = BanquetPackage::where('property_id', app(TenantContext::class)->propertyId())
            ->orderBy('per_pax_rate')->get();
        return view('livewire.setup.banquet-packages', compact('packages'));
    }
}
