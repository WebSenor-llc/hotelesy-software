<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">POS tables</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add table</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Dining tables and sections inside each outlet.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="mb-4 flex items-center gap-3">
        <label class="text-xs font-medium text-slate-500">Filter outlet</label>
        <select wire:model.live="filterOutlet" class="px-3 py-1.5 border rounded-lg text-sm">
            <option value="">All outlets</option>
            @foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Outlet</th>
                    <th class="px-4 py-2.5">Section</th>
                    <th class="px-4 py-2.5 text-right">Capacity</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5">Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($tables as $t)
                <tr>
                    <td class="px-5 py-2.5 font-medium">{{ $t->name }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $t->outlet?->name }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $t->section ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $t->capacity }}</td>
                    <td class="px-4 py-2.5 text-xs uppercase">{{ $t->status }}</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $t->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $t->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $t->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $t->id }})" wire:confirm="Delete table?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">No tables.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add table</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} table</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Outlet *</label>
                <select wire:model="outlet_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select…</option>
                    @foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" placeholder="T1, B12" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Section</label><input type="text" wire:model="section" placeholder="Garden, AC, Bar" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Capacity</label><input type="number" wire:model="capacity" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Status</label>
                <select wire:model="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="reserved">Reserved</option>
                    <option value="cleaning">Cleaning</option>
                </select>
            </div>
            <div class="md:col-span-3 flex items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
