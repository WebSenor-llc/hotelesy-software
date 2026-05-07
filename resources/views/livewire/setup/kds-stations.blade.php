<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">KDS stations</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add station</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Kitchen display stations — tandoor, hot kitchen, cold, bar, grill, pickup window. Items route here from POS.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Code</th>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Outlet</th>
                    <th class="px-4 py-2.5">Type</th>
                    <th class="px-4 py-2.5">Printer IP</th>
                    <th class="px-4 py-2.5 text-right">Prep min</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($stations as $s)
                <tr>
                    <td class="px-5 py-2.5 font-mono text-xs">{{ $s->code }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $s->name }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $s->outlet?->name }}</td>
                    <td class="px-4 py-2.5 text-xs uppercase">{{ str_replace('_',' ', $s->type) }}</td>
                    <td class="px-4 py-2.5 text-xs font-mono">{{ $s->printer_ip ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ $s->default_prep_minutes }}</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $s->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $s->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $s->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $s->id }})" wire:confirm="Delete station?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No stations.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add station</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} KDS station</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Outlet *</label>
                <select wire:model="outlet_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select…</option>
                    @foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Type *</label>
                <select wire:model="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="hot_kitchen">Hot kitchen</option>
                    <option value="cold_kitchen">Cold kitchen</option>
                    <option value="tandoor">Tandoor</option>
                    <option value="bar">Bar</option>
                    <option value="pizza">Pizza</option>
                    <option value="grill">Grill</option>
                    <option value="pickup_window">Pickup window</option>
                    <option value="expo">Expo</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Printer IP</label><input type="text" wire:model="printer_ip" placeholder="192.168.1.50" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Default prep (min)</label><input type="number" wire:model="default_prep_minutes" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3 flex items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
