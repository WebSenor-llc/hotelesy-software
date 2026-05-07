<?php

namespace App\Livewire\Setup;

use App\Models\Accounts\VoucherType;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class VoucherTypes extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public string $code = '';
    public string $name = '';
    public string $type = 'journal';
    public ?string $prefix = null;
    public bool $is_active = true;

    public function startCreate(): void
    {
        $this->reset(['editId','code','name','prefix']);
        $this->type = 'journal';
        $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $vt = VoucherType::findOrFail($id);
        $this->editId = $id;
        foreach (['code','name','type','prefix','is_active'] as $f) {
            $this->$f = $vt->$f;
        }
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'type' => 'required|in:receipt,payment,journal,contra,sales,purchase,credit_note,debit_note',
            'prefix' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);
        $data['property_id'] = app(TenantContext::class)->propertyId();

        if ($this->editId) VoucherType::findOrFail($this->editId)->update($data);
        else VoucherType::create($data);
        session()->flash('success', "Voucher type '{$data['name']}' saved.");
        $this->showForm = false;
        $this->reset(['editId']);
    }

    public function delete(int $id): void
    {
        $vt = VoucherType::findOrFail($id);
        $name = $vt->name;
        $vt->delete();
        session()->flash('success', "Voucher type '$name' deleted.");
    }

    public function render()
    {
        $voucherTypes = VoucherType::where('property_id', app(TenantContext::class)->propertyId())
            ->orderBy('type')->orderBy('code')->get();
        return view('livewire.setup.voucher-types', compact('voucherTypes'));
    }
}
