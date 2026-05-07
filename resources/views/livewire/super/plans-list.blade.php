<div>
    <div class="flex items-baseline justify-between mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Subscription plans</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('super.dashboard') }}" class="text-sm text-slate-600">&larr; Super dashboard</a>
            <button wire:click="startCreate" class="text-xs px-3 py-1.5 rounded bg-brand-600 hover:bg-brand-700 text-white">+ New plan</button>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    @if($showForm)
    <div class="bg-white rounded-xl border p-5 mb-6">
        <h2 class="font-semibold text-slate-900 mb-3">{{ $editingId ? 'Edit plan' : 'New plan' }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Code</label>
                <input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg font-mono">
                @error('code')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Name</label>
                <input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg">
                @error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Monthly price</label>
                <input type="number" step="0.01" wire:model="price_monthly" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Yearly price</label>
                <input type="number" step="0.01" wire:model="price_yearly" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Currency</label>
                <input type="text" wire:model="billing_currency" maxlength="3" class="w-full px-3 py-2 border rounded-lg uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Trial days</label>
                <input type="number" wire:model="trial_days" min="0" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Max properties</label>
                <input type="number" wire:model="max_properties" min="1" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Max rooms</label>
                <input type="number" wire:model="max_rooms" min="1" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Max users</label>
                <input type="number" wire:model="max_users" min="1" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="is_active" class="rounded">
                    Active
                </label>
            </div>
        </div>

        <div class="mt-4">
            <div class="text-xs font-semibold text-slate-700 mb-2">Features</div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f_channel_manager" class="rounded"> Channel manager</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f_pos" class="rounded"> POS</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f_banquet" class="rounded"> Banquet</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f_compliance" class="rounded"> Compliance</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f_revenue" class="rounded"> Revenue mgmt</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f_reviews" class="rounded"> Reviews</label>
            </div>
        </div>

        <div class="flex items-center gap-2 mt-5">
            <button wire:click="save" class="px-4 py-2 rounded bg-brand-600 hover:bg-brand-700 text-white text-sm">Save plan</button>
            <button wire:click="cancelForm" type="button" class="px-4 py-2 rounded text-slate-600 hover:bg-slate-100 text-sm">Cancel</button>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2">Plan</th>
                    <th class="px-4 py-2 text-right">Monthly</th>
                    <th class="px-4 py-2 text-right">Yearly</th>
                    <th class="px-4 py-2 text-right">Properties</th>
                    <th class="px-4 py-2 text-right">Rooms</th>
                    <th class="px-4 py-2 text-right">Users</th>
                    <th class="px-4 py-2 text-right">Trial</th>
                    <th class="px-4 py-2">Active</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($plans as $p)
                    <tr wire:key="plan-{{ $p->id }}">
                        <td class="px-5 py-2">
                            <div class="font-semibold">{{ $p->name }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ $p->code }}</div>
                        </td>
                        <td class="px-4 py-2 text-xs text-right">{{ $p->billing_currency }} {{ number_format($p->price_monthly, 0) }}</td>
                        <td class="px-4 py-2 text-xs text-right">{{ $p->billing_currency }} {{ number_format($p->price_yearly, 0) }}</td>
                        <td class="px-4 py-2 text-xs text-right">{{ $p->max_properties }}</td>
                        <td class="px-4 py-2 text-xs text-right">{{ $p->max_rooms }}</td>
                        <td class="px-4 py-2 text-xs text-right">{{ $p->max_users }}</td>
                        <td class="px-4 py-2 text-xs text-right">{{ $p->trial_days }}d</td>
                        <td class="px-4 py-2">
                            @if($p->is_active)
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">Yes</span>
                            @else
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-slate-200 text-slate-600">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="startEdit({{ $p->id }})" class="text-xs text-brand-600 hover:underline">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">No plans yet. Click <strong>+ New plan</strong>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
