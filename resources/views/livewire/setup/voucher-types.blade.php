<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Voucher types</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('accounts.index') }}" class="text-sm text-slate-600">← Accounts</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add voucher type</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Receipt, payment, journal, contra, sales, purchase, credit/debit note. Each type has its own prefix and number series.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Code</th>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Type</th>
                    <th class="px-4 py-2.5">Prefix</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($voucherTypes as $vt)
                <tr>
                    <td class="px-5 py-2.5 font-mono text-xs">{{ $vt->code }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $vt->name }}</td>
                    <td class="px-4 py-2.5 text-xs uppercase">{{ str_replace('_',' ', $vt->type) }}</td>
                    <td class="px-4 py-2.5 text-xs font-mono">{{ $vt->prefix ?? '—' }}</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $vt->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $vt->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $vt->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $vt->id }})" wire:confirm="Delete voucher type?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No voucher types configured.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add voucher type</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} voucher type</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Type *</label>
                <select wire:model="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="receipt">Receipt</option>
                    <option value="payment">Payment</option>
                    <option value="journal">Journal</option>
                    <option value="contra">Contra</option>
                    <option value="sales">Sales</option>
                    <option value="purchase">Purchase</option>
                    <option value="credit_note">Credit note</option>
                    <option value="debit_note">Debit note</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Prefix</label><input type="text" wire:model="prefix" placeholder="JV, RV, PV" class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></div>
            <div class="md:col-span-3 flex items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
