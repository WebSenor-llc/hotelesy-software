<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Companies & corporate clients</h1>
        <div class="flex items-center gap-3">
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add company</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">City-ledger / corporate accounts. GST, PAN, credit limit, payment terms, corporate discount.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <div class="font-semibold mb-1">Please fix these issues:</div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search by name, code, GST, email…" class="w-full md:w-96 px-3 py-2 border rounded-lg text-sm">
    </div>

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Code</th>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">GSTIN</th>
                    <th class="px-4 py-2.5">Contact</th>
                    <th class="px-4 py-2.5">City</th>
                    <th class="px-4 py-2.5 text-right">Credit limit</th>
                    <th class="px-4 py-2.5 text-right">Outstanding</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($companies as $c)
                <tr>
                    <td class="px-5 py-2.5 font-mono text-xs">{{ $c->code }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $c->name }}
                        @if($c->has_corporate_rate)<span class="ml-1 text-[10px] px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded">CORP {{ rtrim(rtrim($c->corporate_discount_percent, '0'), '.') }}%</span>@endif
                    </td>
                    <td class="px-4 py-2.5 text-xs font-mono">{{ $c->gst_number ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $c->contact_person ?? '—' }}<div class="text-[11px] text-slate-500">{{ $c->contact_email ?? $c->contact_phone }}</div></td>
                    <td class="px-4 py-2.5 text-xs">{{ $c->city ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ $c->is_credit_account ? '₹'.number_format($c->credit_limit,0) : '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">₹{{ number_format($c->current_outstanding,0) }}</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $c->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $c->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $c->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Delete company?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">No companies.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} company</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Legal name</label><input type="text" wire:model="legal_name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">GSTIN</label><input type="text" wire:model="gst_number" class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></div>
            <div><label class="block text-xs font-medium mb-1">PAN</label><input type="text" wire:model="pan_number" class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></div>
            <div></div>
            <div><label class="block text-xs font-medium mb-1">Contact person</label><input type="text" wire:model="contact_person" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Email</label><input type="email" wire:model="contact_email" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Phone</label><input type="text" wire:model="contact_phone" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Billing address</label><textarea wire:model="billing_address" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div><label class="block text-xs font-medium mb-1">City</label><input type="text" wire:model="city" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">State</label><input type="text" wire:model="state" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Postal code</label><input type="text" wire:model="postal_code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Country (ISO-2)</label><input type="text" wire:model="country" maxlength="2" class="w-full px-3 py-2 border rounded-lg text-sm uppercase"></div>
            <div><label class="block text-xs font-medium mb-1">Credit limit (₹)</label><input type="number" step="0.01" wire:model="credit_limit" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Credit days</label><input type="number" wire:model="credit_days" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Corporate discount %</label><input type="number" step="0.01" wire:model="corporate_discount_percent" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Notes</label><textarea wire:model="notes" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="md:col-span-3 flex items-center gap-4 flex-wrap pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_credit_account" class="rounded">City ledger / credit account</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="has_corporate_rate" class="rounded">Has corporate rate</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
