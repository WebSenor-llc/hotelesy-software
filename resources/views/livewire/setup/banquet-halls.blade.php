<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Banquet halls</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add hall</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Halls and meeting rooms with capacity profiles, hourly/half/full-day rates.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Code</th>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5 text-right">Area</th>
                    <th class="px-4 py-2.5 text-right">Theatre</th>
                    <th class="px-4 py-2.5 text-right">Classroom</th>
                    <th class="px-4 py-2.5 text-right">Cluster</th>
                    <th class="px-4 py-2.5 text-right">Banquet</th>
                    <th class="px-4 py-2.5 text-right">Hourly</th>
                    <th class="px-4 py-2.5 text-right">Half-day</th>
                    <th class="px-4 py-2.5 text-right">Full-day</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($halls as $h)
                <tr>
                    <td class="px-5 py-2.5 font-mono text-xs">{{ $h->code }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $h->name }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ $h->area_sqft ? $h->area_sqft.' sqft' : '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ $h->theatre_capacity ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ $h->classroom_capacity ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ $h->cluster_capacity ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ $h->banquet_capacity ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right">₹{{ number_format($h->hourly_rate, 0) }}</td>
                    <td class="px-4 py-2.5 text-right">₹{{ number_format($h->half_day_rate, 0) }}</td>
                    <td class="px-4 py-2.5 text-right">₹{{ number_format($h->full_day_rate, 0) }}</td>
                    <td class="px-4 py-2.5"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $h->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $h->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $h->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $h->id }})" wire:confirm="Delete hall?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" class="px-5 py-12 text-center text-sm text-slate-500">No halls configured.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add hall</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} banquet hall</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Description</label><textarea wire:model="description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div><label class="block text-xs font-medium mb-1">Area (sqft)</label><input type="number" wire:model="area_sqft" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Theatre capacity</label><input type="number" wire:model="theatre_capacity" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Classroom capacity</label><input type="number" wire:model="classroom_capacity" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Cluster capacity</label><input type="number" wire:model="cluster_capacity" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Banquet capacity</label><input type="number" wire:model="banquet_capacity" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div></div>
            <div><label class="block text-xs font-medium mb-1">Hourly rate (₹)</label><input type="number" step="0.01" wire:model="hourly_rate" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Half-day rate (₹)</label><input type="number" step="0.01" wire:model="half_day_rate" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Full-day rate (₹)</label><input type="number" step="0.01" wire:model="full_day_rate" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3 flex items-center gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
