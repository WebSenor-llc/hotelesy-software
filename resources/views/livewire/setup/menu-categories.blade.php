<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Menu categories</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add category</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Starters, mains, desserts, beverages, liquor — group menu items per outlet.</p>

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
                    <th class="px-5 py-2.5">Order</th>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Outlet</th>
                    <th class="px-4 py-2.5">KOT printer</th>
                    <th class="px-4 py-2.5">Flags</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($categories as $c)
                <tr>
                    <td class="px-5 py-2.5 text-xs">{{ $c->display_order }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $c->name }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $c->outlet?->name ?? '— global —' }}</td>
                    <td class="px-4 py-2.5 text-xs font-mono">{{ $c->kot_printer ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-xs">@if($c->is_liquor)<span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Liquor</span>@endif</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $c->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $c->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $c->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Delete category?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">No categories.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add category</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} category</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Outlet</label>
                <select wire:model="outlet_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">— Global —</option>
                    @foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">KOT printer</label><input type="text" wire:model="kot_printer" placeholder="kitchen-1" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Display order</label><input type="number" wire:model="display_order" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3 flex items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_liquor" class="rounded">Liquor (excise tracked)</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
