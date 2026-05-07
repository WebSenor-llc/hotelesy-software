<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">Promotions, coupons & vouchers</h1>
        <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">+ Create promotion</button>
    </div>
    <p class="text-sm text-slate-600 mb-6">Discount codes, free-night offers, package upgrades, complimentary add-ons. Apply at booking, OTA push, or manually at check-in.</p>
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <div class="font-semibold mb-1">Please fix these issues:</div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr><th class="px-5 py-2.5">Code</th><th class="px-4 py-2.5">Name</th><th class="px-4 py-2.5">Type</th><th class="px-4 py-2.5 text-right">Value</th><th class="px-4 py-2.5">Validity</th><th class="px-4 py-2.5 text-right">Used</th><th class="px-4 py-2.5">Channels</th><th class="px-4 py-2.5">Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($promotions as $p)
                    <tr>
                        <td class="px-5 py-2.5 font-mono font-bold text-brand-700">{{ $p->code }}</td>
                        <td class="px-4 py-2.5">{{ $p->name }}<div class="text-xs text-slate-500">{{ $p->description }}</div></td>
                        <td class="px-4 py-2.5 text-xs">{{ str_replace('_',' ',$p->type) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">
                            @if($p->type === 'percentage'){{ rtrim(rtrim($p->value, '0'), '.') }}%
                            @elseif($p->type === 'flat_amount')₹{{ number_format($p->value, 0) }}
                            @elseif($p->type === 'free_night'){{ (int) $p->value }} night(s)
                            @else {{ $p->value }} @endif
                        </td>
                        <td class="px-4 py-2.5 text-xs">{{ $p->valid_from?->format('d M') ?? '—' }} – {{ $p->valid_to?->format('d M Y') ?? '∞' }}</td>
                        <td class="px-4 py-2.5 text-right text-xs">{{ $p->used_count }}{{ $p->max_uses ? ' / '.$p->max_uses : '' }}</td>
                        <td class="px-4 py-2.5 text-xs">
                            @if($p->available_direct)<span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded mr-1">Direct</span>@endif
                            @if($p->available_ota)<span class="px-1.5 py-0.5 bg-sky-100 text-sky-700 rounded mr-1">OTA</span>@endif
                            @if($p->available_corporate)<span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded mr-1">Corp</span>@endif
                        </td>
                        <td class="px-4 py-2.5"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $p->isCurrentlyValid() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $p->isCurrentlyValid() ? 'Live' : 'Inactive' }}</span></td>
                        <td class="px-4 py-2.5 text-right space-x-2">
                            <button type="button" wire:click="startEdit({{ $p->id }})" class="text-xs text-brand-600">Edit</button>
                            @if((int) ($p->used_count ?? 0) > 0)
                                <button type="button"
                                    wire:click="delete({{ $p->id }})"
                                    wire:confirm="Promotion {{ $p->code }} has been redeemed {{ (int) $p->used_count }} time(s). Deactivate to preserve history?"
                                    class="text-xs text-amber-600">Deactivate</button>
                            @else
                                <button type="button"
                                    wire:click="delete({{ $p->id }})"
                                    wire:confirm="Delete promotion {{ $p->code }}?"
                                    class="text-xs text-rose-600">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">No promotions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5"><button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Create promotion</button></div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4 ring-2 ring-brand-200">
            <div class="md:col-span-3 flex items-center justify-between"><h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} promotion</h2><button type="button" wire:click="cancelForm" class="text-sm text-slate-500">Cancel</button></div>
            <div>
                <label class="block text-xs font-medium mb-1">Code *</label>
                <input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm font-mono" placeholder="SUMMER2026">
                @error('code')<div class="text-xs text-rose-600 mt-0.5">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium mb-1">Name *</label>
                <input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm">
                @error('name')<div class="text-xs text-rose-600 mt-0.5">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Description</label><textarea wire:model="description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>

            <div><label class="block text-xs font-medium mb-1">Type *</label>
                <select wire:model="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="percentage">% off</option><option value="flat_amount">Flat ₹ off</option>
                    <option value="free_night">Free night(s)</option><option value="package_upgrade">Package upgrade</option>
                    <option value="complimentary_addon">Complimentary add-on</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Value *</label><input type="number" step="0.01" wire:model="value" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="15"></div>
            <div><label class="block text-xs font-medium mb-1">Applies to *</label>
                <select wire:model="applies_to" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="all">All bookings</option><option value="room_types">Specific room types</option>
                    <option value="rate_plans">Specific rate plans</option><option value="corporate">Corporate only</option>
                </select>
            </div>

            <div class="md:col-span-3 pt-3 border-t mt-2"><h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Validity & limits</h3></div>
            <div><label class="block text-xs font-medium mb-1">Valid from</label><input type="date" wire:model="valid_from" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Valid to</label><input type="date" wire:model="valid_to" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Advance booking days</label><input type="number" wire:model="advance_days" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Min nights</label><input type="number" wire:model="min_nights" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Min amount (₹)</label><input type="number" step="0.01" wire:model="min_amount" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div></div>
            <div><label class="block text-xs font-medium mb-1">Max total uses</label><input type="number" wire:model="max_uses" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Unlimited"></div>
            <div><label class="block text-xs font-medium mb-1">Max per guest</label><input type="number" wire:model="max_per_guest" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Unlimited"></div>

            <div class="md:col-span-3 pt-3 border-t mt-2 flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="available_direct" class="rounded">Direct booking</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="available_ota" class="rounded">Push to OTAs</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="available_corporate" class="rounded">Corporate</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_stackable" class="rounded">Stackable</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_public" class="rounded">Show on booking engine</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
