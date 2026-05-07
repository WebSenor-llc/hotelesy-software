<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">Properties</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ New property</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">All properties under your tenant. Switch into a property from the property selector.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    @if($showForm)
        <div class="bg-white rounded-xl border border-brand-200 ring-2 ring-brand-100 p-5 mb-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Edit property' : 'New property' }}</h2>
                <button type="button" wire:click="cancelForm" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Name *</label>
                    <input type="text" wire:model="name" class="w-full px-3 py-2 border rounded text-sm" placeholder="Miraj Lake Palace">
                    @error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Code *</label>
                    <input type="text" wire:model="code" class="w-full px-3 py-2 border rounded text-sm font-mono" placeholder="MLPU">
                    @error('code')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">City</label>
                    <input type="text" wire:model="city" class="w-full px-3 py-2 border rounded text-sm" placeholder="Bangalore">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Country (ISO-2) *</label>
                    <input type="text" wire:model="country" maxlength="2" class="w-full px-3 py-2 border rounded text-sm uppercase">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">GSTIN</label>
                    <input type="text" wire:model="gst_number" class="w-full px-3 py-2 border rounded text-sm font-mono" placeholder="29ABCDE1234F1Z5">
                    @error('gst_number')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Currency *</label>
                    <input type="text" wire:model="currency" maxlength="3" class="w-full px-3 py-2 border rounded text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1">Timezone *</label>
                    <input type="text" wire:model="timezone" class="w-full px-3 py-2 border rounded text-sm" placeholder="Asia/Kolkata">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t">
                <button type="button" wire:click="cancelForm" class="px-4 py-2 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save property</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr><th class="px-5 py-2">Code</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">City</th><th class="px-4 py-2">GSTIN</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($properties as $p)
                    <tr>
                        <td class="px-5 py-2 font-mono text-xs">{{ $p->code }}</td>
                        <td class="px-4 py-2 font-semibold">{{ $p->name }}</td>
                        <td class="px-4 py-2 text-xs">{{ $p->city }}</td>
                        <td class="px-4 py-2 font-mono text-[11px]">{{ $p->gst_number }}</td>
                        <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $p->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">{{ $p->status }}</span></td>
                        <td class="px-4 py-2 text-right">
                            <button type="button" wire:click="startEdit({{ $p->id }})" class="text-xs text-brand-600 hover:underline">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No properties yet. Click <strong>+ New property</strong> to add the first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
