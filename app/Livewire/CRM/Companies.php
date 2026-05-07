<?php

namespace App\Livewire\CRM;

use App\Models\Company;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Companies extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;

    public string $code = '';
    public string $name = '';
    public ?string $legal_name = null;
    public ?string $gst_number = null;
    public ?string $pan_number = null;
    public ?string $billing_address = null;
    public ?string $city = null;
    public ?string $state = null;
    public string $country = 'IN';
    public ?string $postal_code = null;
    public ?string $contact_person = null;
    public ?string $contact_email = null;
    public ?string $contact_phone = null;
    public bool $is_credit_account = false;
    public float $credit_limit = 0;
    public int $credit_days = 0;
    public bool $has_corporate_rate = false;
    public float $corporate_discount_percent = 0;
    public bool $is_active = true;
    public ?string $notes = null;

    public string $search = '';

    public function startCreate(): void
    {
        $this->reset(['editId','code','name','legal_name','gst_number','pan_number',
            'billing_address','city','state','postal_code',
            'contact_person','contact_email','contact_phone','notes']);
        $this->country = 'IN';
        $this->is_credit_account = false; $this->credit_limit = 0; $this->credit_days = 0;
        $this->has_corporate_rate = false; $this->corporate_discount_percent = 0;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $c = app(TenantContext::class)->bypass(fn () => Company::findOrFail($id));
        $this->editId = $id;

        // Coerce DB nulls to safe defaults so typed Livewire properties don't TypeError.
        $this->code            = (string) ($c->code ?? '');
        $this->name            = (string) ($c->name ?? '');
        $this->legal_name      = $c->legal_name;
        $this->gst_number      = $c->gst_number;
        $this->pan_number      = $c->pan_number;
        $this->billing_address = $c->billing_address;
        $this->city            = $c->city;
        $this->state           = $c->state;
        $this->country         = (string) ($c->country ?? 'IN');
        $this->postal_code     = $c->postal_code;
        $this->contact_person  = $c->contact_person;
        $this->contact_email   = $c->contact_email;
        $this->contact_phone   = $c->contact_phone;
        $this->is_credit_account = (bool) $c->is_credit_account;
        $this->credit_limit    = (float) ($c->credit_limit ?? 0);
        $this->credit_days     = (int) ($c->credit_days ?? 0);
        $this->has_corporate_rate = (bool) $c->has_corporate_rate;
        $this->corporate_discount_percent = (float) ($c->corporate_discount_percent ?? 0);
        $this->is_active       = (bool) ($c->is_active ?? true);
        $this->notes           = $c->notes;

        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'gst_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'required|string|size:2',
            'postal_code' => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'is_credit_account' => 'boolean',
            'credit_limit' => 'numeric|min:0',
            'credit_days' => 'integer|min:0|max:365',
            'has_corporate_rate' => 'boolean',
            'corporate_discount_percent' => 'numeric|min:0|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:5000',
        ]);

        $ctx = app(TenantContext::class);
        $data['code'] = strtoupper(trim($data['code']));

        try {
            if ($this->editId) {
                $ctx->bypass(fn () => Company::findOrFail($this->editId)->update($data));
            } else {
                $data['tenant_id'] = $ctx->tenantId();
                Company::create($data);
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to save company: ' . $e->getMessage());
            return;
        }

        session()->flash('success', "Company '{$data['name']}' saved.");
        $this->showForm = false;
        $this->editId = null;
    }

    public function delete(int $id): void
    {
        $c = Company::findOrFail($id);
        $name = $c->name;
        $c->delete();
        session()->flash('success', "Company '$name' deleted.");
    }

    public function render()
    {
        $query = Company::query();
        if ($this->search !== '') {
            $s = '%'.$this->search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('code', 'like', $s)
                  ->orWhere('gst_number', 'like', $s)
                  ->orWhere('contact_email', 'like', $s);
            });
        }
        $companies = $query->orderBy('name')->get();
        return view('livewire.crm.companies', compact('companies'));
    }
}
