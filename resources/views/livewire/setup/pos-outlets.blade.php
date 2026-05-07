<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">POS outlets</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add outlet</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Restaurants, bars, cafés, room service, banquet POS, pool, spa.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Code</th>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Type</th>
                    <th class="px-4 py-2.5">Hours</th>
                    <th class="px-4 py-2.5 text-right">Service charge</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($outlets as $o)
                <tr>
                    <td class="px-5 py-2.5 font-mono text-xs">{{ $o->code }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $o->name }}</td>
                    <td class="px-4 py-2.5 text-xs uppercase">{{ str_replace('_',' ', $o->type) }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $o->open_time ? \Illuminate\Support\Str::substr($o->open_time, 0, 5) : '—' }} – {{ $o->close_time ? \Illuminate\Support\Str::substr($o->close_time, 0, 5) : '—' }}</td>
                    <td class="px-4 py-2.5 text-right">{{ rtrim(rtrim($o->service_charge_percent, '0'), '.') }}%</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $o->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $o->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $o->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $o->id }})" wire:confirm="Delete outlet?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">No outlets configured.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add outlet</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} outlet</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Type *</label>
                <select wire:model="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="restaurant">Restaurant</option>
                    <option value="bar">Bar</option>
                    <option value="cafe">Café</option>
                    <option value="room_service">Room service</option>
                    <option value="banquet">Banquet</option>
                    <option value="pool">Pool</option>
                    <option value="spa">Spa</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Open time</label><input type="time" wire:model="open_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Close time</label><input type="time" wire:model="close_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Service charge %</label><input type="number" step="0.01" wire:model="service_charge_percent" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3 flex items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
