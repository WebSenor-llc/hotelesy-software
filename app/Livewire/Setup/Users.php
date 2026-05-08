<?php

namespace App\Livewire\Setup;

use App\Models\Property;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app-shell')]
class Users extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $employee_code = '';
    public string $department = '';
    public string $designation = '';
    public ?int $default_property_id = null;
    public bool $is_active = true;
    public bool $can_handle_cash = false;
    public ?float $cash_drawer_limit = null;
    public string $role = '';

    public function startCreate(): void
    {
        $this->reset(); $this->is_active = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $u = User::findOrFail($id);
        $this->editId = $id;
        foreach (['name','email','phone','employee_code','department','designation','default_property_id','is_active','can_handle_cash','cash_drawer_limit'] as $f) {
            $this->$f = $u->$f ?? '';
        }
        $this->role = $u->roles()->pluck('name')->first() ?? '';
        $this->password = '';
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId','password']); }

    public function save(): void
    {
        $ctx = app(TenantContext::class);
        $tenantId = $ctx->tenantId();

        // Tenant-scoped email uniqueness — the DB has a (tenant_id, email)
        // composite unique key, so we need to scope the validator to that
        // tenant AND ignore the current user when editing. This catches
        // duplicates BEFORE hitting the DB so the user gets a friendly
        // inline message instead of a 500.
        $emailUnique = \Illuminate\Validation\Rule::unique('users', 'email')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId))
            ->ignore($this->editId);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', $emailUnique],
            'phone' => 'nullable|string|max:30',
            'employee_code' => 'nullable|string|max:30',
            'department' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:100',
            'default_property_id' => 'nullable|exists:properties,id',
            'is_active' => 'boolean',
            'can_handle_cash' => 'boolean',
            'cash_drawer_limit' => 'nullable|numeric|min:0',
            'role' => 'nullable|string|max:100',
        ];
        if (!$this->editId) $rules['password'] = 'required|min:6';
        elseif ($this->password) $rules['password'] = 'min:6';

        $data = $this->validate($rules, [
            'email.unique' => 'A user with this email already exists in this tenant. Use a different email or edit the existing user.',
        ]);

        $payload = collect($data)->except(['role'])->toArray();
        $payload['tenant_id'] = $tenantId;
        // cash_drawer_limit column is NOT NULL in the DB. When the user isn't a
        // cash handler the form leaves it null — coerce to 0 so insert succeeds.
        $payload['cash_drawer_limit'] = (float) ($payload['cash_drawer_limit'] ?? 0);
        if (isset($payload['password']) && $payload['password']) {
            $payload['password'] = Hash::make($payload['password']);
        } else {
            unset($payload['password']);
        }

        try {
            $user = $this->editId
                ? tap(User::findOrFail($this->editId))->update($payload)
                : User::create($payload);

            if ($this->role) {
                try { $user->syncRoles([$this->role]); } catch (\Throwable $e) {}
            }

            session()->flash('success', 'User saved.');
            $this->reset(['editId','password']);
            $this->showForm = false;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Defensive — should be caught by the validator above, but DB-level
            // catch ensures any other unique key (employee_code, phone, etc.)
            // surfaces as a flash error instead of a 500.
            session()->flash('error', 'This user conflicts with an existing record (duplicate email, employee code, or phone). Please change the duplicated field.');
        } catch (\Throwable $e) {
            \Log::error('User save failed: ' . $e->getMessage());
            session()->flash('error', 'Could not save user: ' . $e->getMessage());
        }
    }

    public function toggleActive(int $id): void
    {
        $u = User::findOrFail($id);
        $u->update(['is_active' => !$u->is_active]);
        session()->flash('success', "{$u->name} " . ($u->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $users = User::where('tenant_id', $ctx->tenantId())->with('roles')->orderBy('name')->get();
        $properties = $ctx->tenant()?->properties()->where('status', 'active')->get() ?? collect();
        $roles = Role::where('guard_name','web')->orderBy('name')->pluck('name');
        return view('livewire.setup.users', compact('users','properties','roles'));
    }
}
