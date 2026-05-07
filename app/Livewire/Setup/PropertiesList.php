<?php

namespace App\Livewire\Setup;

use App\Models\Property;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class PropertiesList extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $code = '';
    public string $city = '';
    public string $country = 'IN';
    public string $gst_number = '';
    public string $currency = 'INR';
    public string $timezone = 'Asia/Kolkata';

    public function startCreate(): void
    {
        $this->reset(['editingId','name','code','city','gst_number']);
        $this->country = 'IN';
        $this->currency = 'INR';
        $this->timezone = 'Asia/Kolkata';
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $p = Property::findOrFail($id);
        $ctx = app(TenantContext::class);
        abort_unless($p->tenant_id === $ctx->tenantId() || auth()->user()?->is_super_admin, 403);

        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->code = $p->code;
        $this->city = (string) $p->city;
        $this->country = $p->country ?: 'IN';
        $this->gst_number = (string) $p->gst_number;
        $this->currency = $p->currency ?: 'INR';
        $this->timezone = $p->timezone ?: 'Asia/Kolkata';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->reset(['editingId','name','code','city','gst_number']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'required|string|size:2',
            'gst_number' => ['nullable','string','max:20','regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:64',
        ], [
            'gst_number.regex' => 'GSTIN must be a valid 15-character GSTIN.',
        ]);

        $ctx = app(TenantContext::class);
        $payload = $data + [
            'tenant_id' => $ctx->tenantId(),
            'status'    => 'setup',
        ];

        if ($this->editingId) {
            Property::findOrFail($this->editingId)->update($payload);
            session()->flash('success', "Property {$payload['name']} updated.");
        } else {
            Property::create($payload);
            session()->flash('success', "Property {$payload['name']} created.");
        }

        $this->showForm = false;
        $this->reset(['editingId','name','code','city','gst_number']);
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $properties = $ctx->tenant()
            ? $ctx->tenant()->properties()->orderBy('name')->get()
            : collect();

        return view('livewire.setup.properties-list', compact('properties'));
    }
}
