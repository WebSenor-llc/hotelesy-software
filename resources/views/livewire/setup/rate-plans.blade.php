<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Rate plans</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add rate plan</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">BAR, OTA-specific, corporate, promotional. Restrictions, cancellation policy, channel availability.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5 font-semibold">Code</th>
                    <th class="px-4 py-2.5 font-semibold">Name</th>
                    <th class="px-4 py-2.5 font-semibold">Room type</th>
                    <th class="px-4 py-2.5 font-semibold">Meal plan</th>
                    <th class="px-4 py-2.5 font-semibold">Pricing</th>
                    <th class="px-4 py-2.5 font-semibold text-right">Base rate</th>
                    <th class="px-4 py-2.5 font-semibold">Restrictions</th>
                    <th class="px-4 py-2.5 font-semibold">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($ratePlans as $r)
                    <tr>
                        <td class="px-5 py-2.5 font-mono text-xs">{{ $r->code }}</td>
                        <td class="px-4 py-2.5 font-medium">{{ $r->name }}
                            @if($r->is_corporate)<span class="ml-1 text-[10px] px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded">CORP</span>@endif
                            @if($r->is_promotional)<span class="ml-1 text-[10px] px-1.5 py-0.5 bg-rose-100 text-rose-700 rounded">PROMO</span>@endif
                        </td>
                        <td class="px-4 py-2.5 text-xs">{{ $r->roomType?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $r->meal_plan }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ str_replace('_',' ',$r->pricing_mode) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            ₹{{ number_format($r->base_rate, 0) }}
                            <div class="text-[10px] mt-0.5">
                                <span class="px-1.5 py-0.5 rounded {{ ($r->tax_mode ?? 'exclusive') === 'inclusive' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700' }}">
                                    GST {{ ($r->tax_mode ?? 'exclusive') === 'inclusive' ? 'incl.' : 'excl.' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-xs">Min stay {{ $r->min_stay }}{{ $r->refundable ? '' : ' · NR' }}</td>
                        <td class="px-4 py-2.5"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $r->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="px-4 py-2.5 text-right space-x-2">
                            <button type="button" wire:click="startEdit({{ $r->id }})" class="text-xs text-brand-600">Edit</button>
                            <button type="button" wire:click="delete({{ $r->id }})" wire:confirm="Delete rate plan?" class="text-xs text-rose-600">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">No rate plans.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600">+ Add rate plan</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ $editId ? 'Edit' : 'New' }} rate plan</h2>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-500">Cancel</button>
            </div>
            <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Room type *</label>
                <select wire:model="room_type_id" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select…</option>@foreach($roomTypes as $rt)<option value="{{ $rt->id }}">{{ $rt->name }}</option>@endforeach</select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Meal plan</label>
                <select wire:model="meal_plan" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="EP">EP — Room only</option><option value="CP">CP — + Breakfast</option><option value="MAP">MAP — + 1 meal</option><option value="AP">AP — All meals</option></select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Pricing mode</label>
                <select wire:model="pricing_mode" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="fixed">Fixed rate</option><option value="percentage_of_base">% of base</option><option value="amount_off_base">Amount off base</option><option value="amount_added">Amount added</option></select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Tax mode</label>
                <select wire:model="tax_mode" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="exclusive">Exclusive — GST added on top</option>
                    <option value="inclusive">Inclusive — GST already in price</option>
                </select>
                <p class="text-[10px] text-slate-500 mt-1">Inclusive: ₹1,000 displayed = ₹1,000 charged (tax extracted). Exclusive: ₹1,000 + 12% GST = ₹1,120 charged.</p>
            </div>
            <div><label class="block text-xs font-medium mb-1">Base rate (₹)</label><input type="number" step="0.01" wire:model="base_rate" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Modifier</label><input type="number" step="0.01" wire:model="rate_modifier" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Min stay (nights)</label><input type="number" wire:model="min_stay" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Max stay (0=∞)</label><input type="number" wire:model="max_stay" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Advance booking (days)</label><input type="number" wire:model="advance_booking_days" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Cancellation hours</label><input type="number" wire:model="cancellation_hours" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Cancellation charge %</label><input type="number" step="0.01" wire:model="cancellation_charge_percent" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3 flex items-center gap-4 flex-wrap pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="refundable" class="rounded">Refundable</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_corporate" class="rounded">Corporate</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_promotional" class="rounded">Promotional</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="sell_on_channels" class="rounded">Sell on OTAs</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto"><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button></div>
            </div>
        </form>
    @endif
</div>
