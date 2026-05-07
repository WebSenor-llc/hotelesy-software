<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tax rules</h1>
            <p class="text-sm text-slate-500">India GST + state VAT matrix · controls every invoice line item</p>
        </div>
        <button wire:click="startCreate" class="text-sm bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg">+ Add tax rule</button>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    @if($showForm)
    <div class="bg-white rounded-2xl border border-brand-200 p-6 mb-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ $editId ? 'Edit' : 'Create' }} tax rule</h3>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Code *</label>
                <input wire:model="code" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g. ROOM_GST_18">
                @error('code')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Name *</label>
                <input wire:model="name" class="w-full px-3 py-2 border rounded-lg">
                @error('name')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Scope *</label>
                <select wire:model="scope" class="w-full px-3 py-2 border rounded-lg">
                    @foreach($scopeLabels as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">HSN/SAC code</label>
                <input wire:model="hsn_sac_code" class="w-full px-3 py-2 border rounded-lg" placeholder="996311">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Total rate (%) *</label>
                <input wire:model="total_rate" type="number" step="0.001" class="w-full px-3 py-2 border rounded-lg">
                @error('total_rate')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">CGST rate (%)</label>
                <input wire:model="cgst_rate" type="number" step="0.001" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">SGST rate (%)</label>
                <input wire:model="sgst_rate" type="number" step="0.001" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">IGST rate (%)</label>
                <input wire:model="igst_rate" type="number" step="0.001" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Cess rate (%)</label>
                <input wire:model="cess_rate" type="number" step="0.001" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Room tariff min (₹)</label>
                <input wire:model="room_tariff_min" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" placeholder="for room scope only">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Room tariff max (₹)</label>
                <input wire:model="room_tariff_max" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Priority (lower wins)</label>
                <input wire:model="priority" type="number" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div class="md:col-span-2 lg:col-span-3">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                <textarea wire:model="description" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-xs"><input wire:model="itc_available" type="checkbox"> ITC available</label>
                <label class="flex items-center gap-2 text-xs"><input wire:model="is_active" type="checkbox"> Active</label>
            </div>
        </div>
        <div class="flex gap-2 mt-5">
            <button wire:click="save" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded">Save rule</button>
            <button wire:click="cancelForm" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold px-4 py-2 rounded">Cancel</button>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2.5">Code</th>
                    <th class="px-3 py-2.5">Name</th>
                    <th class="px-3 py-2.5">Scope</th>
                    <th class="px-3 py-2.5">HSN/SAC</th>
                    <th class="px-3 py-2.5 text-right">Rate</th>
                    <th class="px-3 py-2.5">CGST/SGST · IGST</th>
                    <th class="px-3 py-2.5">Tariff range</th>
                    <th class="px-3 py-2.5">Active</th>
                    <th class="px-4 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rules as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $r->code }}</td>
                        <td class="px-3 py-2.5">{{ $r->name }}</td>
                        <td class="px-3 py-2.5 text-xs">{{ $scopeLabels[$r->scope] ?? $r->scope }}</td>
                        <td class="px-3 py-2.5 font-mono text-xs">{{ $r->hsn_sac_code ?: '—' }}</td>
                        <td class="px-3 py-2.5 text-right font-mono">{{ rtrim(rtrim(number_format($r->total_rate, 3), '0'), '.') }}%</td>
                        <td class="px-3 py-2.5 text-xs font-mono">{{ rtrim(rtrim(number_format($r->cgst_rate, 3), '0'), '.') }}+{{ rtrim(rtrim(number_format($r->sgst_rate, 3), '0'), '.') }} · {{ rtrim(rtrim(number_format($r->igst_rate, 3), '0'), '.') }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            @if($r->room_tariff_min || $r->room_tariff_max)
                                ₹{{ number_format($r->room_tariff_min ?: 0) }}–{{ $r->room_tariff_max ? '₹'.number_format($r->room_tariff_max) : '∞' }}
                            @else
                                <span class="text-slate-400">any</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <button wire:click="toggleActive({{ $r->id }})"
                                class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded {{ $r->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $r->is_active ? 'On' : 'Off' }}
                            </button>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <button wire:click="startEdit({{ $r->id }})" class="text-xs text-brand-600 hover:underline font-semibold">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">
                        No tax rules configured. <button wire:click="startCreate" class="text-brand-600 font-semibold">Add the first rule →</button>
                        <div class="mt-2 text-xs">Or run: <code class="bg-slate-100 px-1.5 py-0.5 rounded">php artisan db:seed --class=IndiaTaxRulesSeeder</code></div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
