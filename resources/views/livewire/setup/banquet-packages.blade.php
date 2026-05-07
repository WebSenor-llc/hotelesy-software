<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Banquet packages</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add package</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Wedding, conference, birthday packages with per-pax pricing and inclusions.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Name</th>
                    <th class="px-4 py-2.5 text-right">Per pax</th>
                    <th class="px-4 py-2.5 text-right">Min pax</th>
                    <th class="px-4 py-2.5">Inclusions</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($packages as $p)
                <tr>
                    <td class="px-5 py-2.5 font-medium">{{ $p->name }}
                        @if($p->description)<div class="text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($p->description, 80) }}</div>@endif
                    </td>
                    <td class="px-4 py-2.5 text-right">₹{{ number_format($p->per_pax_rate, 0) }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $p->min_pax }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ is_array($p->inclusions) ? count($p->inclusions).' items' : '—' }}</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $p->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $p->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $p->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $p->id }})" wire:confirm="Delete package?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No packages configured.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add package</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} banquet package</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Description</label><textarea wire:model="description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div><label class="block text-xs font-medium mb-1">Per-pax rate (₹) *</label><input type="number" step="0.01" wire:model="per_pax_rate" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Min pax</label><input type="number" wire:model="min_pax" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Inclusions (one per line)</label>
                <textarea wire:model="inclusions_text" rows="5" placeholder="3-course meal&#10;Welcome drinks&#10;Stage decor&#10;Sound system" class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></textarea>
            </div>
            <div class="md:col-span-3 flex items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
