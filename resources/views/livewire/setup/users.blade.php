<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Users & roles</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add user</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Staff accounts. 14 roles per the spec — Owner, GM, FOM, Reservation Agent, Cashier, etc.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr><th class="px-5 py-2.5 font-semibold">Name</th><th class="px-4 py-2.5 font-semibold">Email</th><th class="px-4 py-2.5 font-semibold">Role</th><th class="px-4 py-2.5 font-semibold">Department</th><th class="px-4 py-2.5 font-semibold">Cash drawer</th><th class="px-4 py-2.5 font-semibold">Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $u)
                    <tr>
                        <td class="px-5 py-2.5">
                            <div class="font-medium">{{ $u->name }}</div>
                            <div class="text-xs text-slate-500">{{ $u->employee_code ?: '—' }}</div>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ $u->email }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $u->roles->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $u->department ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $u->can_handle_cash ? '₹'.number_format($u->cash_drawer_limit ?? 0, 0).' limit' : '—' }}</td>
                        <td class="px-4 py-2.5"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="px-4 py-2.5 text-right space-x-2">
                            <button type="button" wire:click="startEdit({{ $u->id }})" class="text-xs text-brand-600">Edit</button>
                            <button type="button" wire:click="toggleActive({{ $u->id }})" class="text-xs text-slate-600">{{ $u->is_active ? 'Disable' : 'Enable' }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5"><button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add user</button></div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} user</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Email *</label><input type="email" wire:model="email" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Phone</label><input type="tel" wire:model="phone" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Password {{ $editId ? '(leave blank to keep)' : '*' }}</label><input type="password" wire:model="password" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Employee code</label><input type="text" wire:model="employee_code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Designation</label><input type="text" wire:model="designation" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Department</label>
                <select wire:model="department" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select…</option>
                    <option>Front Office</option><option>F&B</option><option>Housekeeping</option><option>Kitchen</option>
                    <option>Banquet</option><option>Accounts</option><option>Sales</option><option>HR</option><option>Maintenance</option><option>Spa</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Role</label>
                <select wire:model="role" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">No role</option>
                    @foreach($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Default property</label>
                <select wire:model="default_property_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">None</option>
                    @foreach($properties as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-3 flex items-center gap-4 flex-wrap pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="can_handle_cash" class="rounded">Cash handling</label>
                <div><label class="block text-xs font-medium mb-1">Cash drawer limit</label><input type="number" step="0.01" wire:model="cash_drawer_limit" class="px-3 py-1.5 border rounded text-sm w-32"></div>
                <div class="ml-auto"><button class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
