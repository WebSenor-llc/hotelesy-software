<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Menu items</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add item</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Items, prices, taxes, food type. Veg/non-veg/jain/liquor flags drive printing and reporting.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <div class="font-semibold mb-1">Please fix:</div>
            <ul class="list-disc list-inside text-xs">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="text-xs font-medium text-slate-500">Outlet</label>
        <select wire:model.live="filterOutlet" class="px-3 py-1.5 border rounded-lg text-sm">
            <option value="">All outlets</option>
            @foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
        </select>
        <label class="text-xs font-medium text-slate-500">Category</label>
        <select wire:model.live="filterCategory" class="px-3 py-1.5 border rounded-lg text-sm">
            <option value="">All categories</option>
            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5">Code</th>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Category</th>
                    <th class="px-4 py-2.5">Type</th>
                    <th class="px-4 py-2.5 text-right">Price</th>
                    <th class="px-4 py-2.5 text-right">Tax</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($items as $i)
                <tr>
                    <td class="px-5 py-2.5 font-mono text-xs">{{ $i->code }}</td>
                    <td class="px-4 py-2.5 font-medium">{{ $i->name }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $i->category?->name }} <span class="text-slate-400">/ {{ $i->category?->outlet?->name ?? '—' }}</span></td>
                    <td class="px-4 py-2.5 text-xs">
                        @php $colors = ['veg'=>'bg-emerald-100 text-emerald-700','non_veg'=>'bg-rose-100 text-rose-700','egg'=>'bg-amber-100 text-amber-700','jain'=>'bg-orange-100 text-orange-700','beverage'=>'bg-sky-100 text-sky-700','liquor'=>'bg-purple-100 text-purple-700']; @endphp
                        <span class="px-1.5 py-0.5 rounded text-[10px] {{ $colors[$i->food_type] ?? 'bg-slate-100' }}">{{ str_replace('_',' ', $i->food_type) }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-right">₹{{ number_format($i->price,2) }}</td>
                    <td class="px-4 py-2.5 text-right text-xs">{{ rtrim(rtrim($i->tax_percent, '0'), '.') }}%</td>
                    <td class="px-4 py-2.5">
                        @if(!$i->available)<span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Unavailable</span>
                        @else<span class="text-[10px] px-2 py-0.5 rounded-full {{ $i->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100' }}">{{ $i->is_active?'Active':'Inactive' }}</span>@endif
                    </td>
                    <td class="px-4 py-2.5 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $i->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $i->id }})" wire:confirm="Delete item?" class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No menu items.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add item</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} menu item</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Category *</label>
                <select wire:model="category_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select…</option>
                    @foreach($allCategories as $c)<option value="{{ $c->id }}">{{ $c->outlet?->name ?? 'Global' }} — {{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Description</label><textarea wire:model="description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div><label class="block text-xs font-medium mb-1">Price (₹) *</label><input type="number" step="0.01" wire:model="price" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Cost (₹)</label><input type="number" step="0.01" wire:model="cost" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Tax %</label><input type="number" step="0.01" wire:model="tax_percent" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Food type *</label>
                <select wire:model="food_type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="veg">Veg</option>
                    <option value="non_veg">Non-veg</option>
                    <option value="egg">Egg</option>
                    <option value="jain">Jain</option>
                    <option value="beverage">Beverage</option>
                    <option value="liquor">Liquor</option>
                </select>
            </div>
            <div class="md:col-span-3 flex items-center gap-4 flex-wrap pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_taxable" class="rounded">Taxable</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="available" class="rounded">Available</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
