<div>
    <div class="flex items-center justify-between mb-1">
        <div>
            <h1 class="text-2xl font-bold">Taxes</h1>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add tax</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">GST slabs, service charge, luxury tax. Threshold logic for hotel GST: ≤7500 → 12%, &gt;7500 → 18%.</p>
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr><th class="px-5 py-2.5">Code</th><th class="px-4 py-2.5">Name</th><th class="px-4 py-2.5">Type</th><th class="px-4 py-2.5 text-right">Rate</th><th class="px-4 py-2.5">Threshold</th><th class="px-4 py-2.5">Applies to</th><th class="px-4 py-2.5">Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($taxes as $t)
                <tr>
                    <td class="px-5 py-2.5 font-mono text-xs">{{ $t->code }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $t->name }}</td>
                    <td class="px-4 py-2.5 text-xs uppercase">{{ $t->type }}</td>
                    <td class="px-4 py-2.5 text-right font-bold">{{ rtrim(rtrim($t->rate, '0'), '.') }}%</td>
                    <td class="px-4 py-2.5 text-xs">{{ $t->threshold_min ? '₹'.number_format($t->threshold_min,0) : '0' }} – {{ $t->threshold_max ? '₹'.number_format($t->threshold_max,0) : '∞' }}</td>
                    <td class="px-4 py-2.5 text-xs">@foreach(['room','food','other'] as $a)@php $k='applies_to_'.$a;@endphp @if($t->$k)<span class="px-1.5 py-0.5 bg-slate-100 rounded mr-1">{{ $a }}</span>@endif @endforeach</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $t->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $t->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $t->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $t->id }})" wire:confirm="Delete?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No taxes configured.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5"><button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add tax</button></div>
    </div>
    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} tax</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Type *</label>
                <select wire:model="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="gst">GST</option><option value="cgst">CGST</option><option value="sgst">SGST</option><option value="igst">IGST</option><option value="service_charge">Service charge</option><option value="luxury_tax">Luxury tax</option><option value="other">Other</option>
                </select></div>
            <div><label class="block text-xs font-medium mb-1">Rate (%) *</label><input type="number" step="0.001" wire:model="rate" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Threshold min (₹)</label><input type="number" step="0.01" wire:model="threshold_min" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Threshold max (₹)</label><input type="number" step="0.01" wire:model="threshold_max" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3 flex flex-wrap items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="applies_to_room" class="rounded">Room</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="applies_to_food" class="rounded">F&B</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="applies_to_other" class="rounded">Other</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_inclusive" class="rounded">Inclusive</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_compoundable" class="rounded">Compoundable</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
