<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">Amenities & services</h1>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="startOrder" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded text-sm font-semibold">+ Sell to guest</button>
            <button type="button" wire:click="startCreateAmenity" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-1.5 rounded text-sm font-semibold">+ Add amenity</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Spa, transport, tours, laundry, in-room services. Sell to guests — charges post to room folio.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Spa</div><div class="text-2xl font-bold">{{ $stats['spa'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Utility</div><div class="text-2xl font-bold">{{ $stats['utility'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Transport</div><div class="text-2xl font-bold">{{ $stats['transport'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Tours</div><div class="text-2xl font-bold">{{ $stats['tour'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Orders today</div><div class="text-2xl font-bold text-amber-700">{{ $stats['today_orders'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Revenue today</div><div class="text-2xl font-bold">₹{{ number_format($stats['today_revenue'], 0) }}</div></div>
    </div>

    {{-- Amenity create/edit form --}}
    @if($showAmenityForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-brand-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">{{ $editingAmenityId ? 'Edit amenity' : 'Add new amenity' }}</h2>
                <button type="button" wire:click="cancelAmenityForm" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded text-sm uppercase" placeholder="SPA-60"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded text-sm" placeholder="60-min Ayurvedic massage"></div>
                <div><label class="block text-xs font-medium mb-1">Category</label>
                    <select wire:model="category" class="w-full px-3 py-2 border rounded text-sm">
                        @foreach(['transport','meal','spa','tour','experience','merchandise','utility','other'] as $c)
                            <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Pricing type</label>
                    <select wire:model="pricingType" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="flat">Flat</option><option value="per_person">Per person</option>
                        <option value="per_stay">Per stay</option><option value="per_night">Per night</option>
                        <option value="per_person_per_night">Per person per night</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Price (₹) *</label><input type="number" step="0.01" wire:model="price" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Tax % (GST)</label><input type="number" step="0.01" wire:model="taxPercent" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Description</label><input type="text" wire:model="description" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div class="flex items-center"><label class="flex items-center gap-2 mt-5"><input type="checkbox" wire:model="isActive">Active</label></div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="cancelAmenityForm" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="saveAmenity" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded text-sm font-semibold">{{ $editingAmenityId ? 'Update' : 'Save amenity' }}</button>
            </div>
        </div>
    @endif

    {{-- Sell-to-guest form --}}
    @if($showOrderForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-emerald-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">Sell amenity to guest</h2>
                <button type="button" wire:click="cancelOrder" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div><label class="block text-xs font-medium mb-1">Amenity *</label>
                    <select wire:model.live="orderAmenityId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">Select…</option>
                        @foreach($amenities->where('is_active', true) as $a)
                            <option value="{{ $a->id }}">{{ $a->name }} — ₹{{ number_format($a->price, 0) }} ({{ $a->category }})</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Guest (in-house)</label>
                    <select wire:model="orderReservationId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">Walk-in (no folio)</option>
                        @foreach($checkedInReservations as $r)
                            <option value="{{ $r->id }}">{{ $r->guest_name }} · Room {{ $r->rooms?->first()?->room?->number ?? '—' }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Quantity</label><input type="number" min="1" wire:model="orderQty" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Service date</label><input type="date" wire:model="serviceDate" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Service time</label><input type="time" wire:model="serviceTime" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Notes</label><input type="text" wire:model="orderNotes" placeholder="Anything special" class="w-full px-3 py-2 border rounded text-sm"></div>
            </div>
            @if($orderingAmenity)
                <div class="bg-slate-50 rounded p-3 mb-4 text-sm">
                    <div class="flex justify-between"><span>{{ $orderingAmenity->name }} × {{ $orderQty }}</span><span class="font-medium">₹{{ number_format($orderingAmenity->price * $orderQty, 2) }}</span></div>
                    <div class="flex justify-between text-xs text-slate-500"><span>+ tax {{ $orderingAmenity->tax_percent }}%</span><span>₹{{ number_format($orderingAmenity->price * $orderQty * ($orderingAmenity->tax_percent/100), 2) }}</span></div>
                    <div class="flex justify-between font-bold border-t pt-2 mt-2"><span>Total</span><span>₹{{ number_format($orderingAmenity->price * $orderQty * (1 + $orderingAmenity->tax_percent/100), 2) }}</span></div>
                </div>
            @endif
            <label class="flex items-center gap-2 mb-4 text-sm"><input type="checkbox" wire:model="chargeToFolio">Charge to room folio (if guest is in-house)</label>
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="cancelOrder" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="placeOrder" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded text-sm font-semibold">Place order</button>
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="border-b flex">
            <button type="button" wire:click="setTab('services')" class="px-5 py-3 text-sm font-medium {{ $tab === 'services' ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-600' }}">Services ({{ $amenities->count() }})</button>
            <button type="button" wire:click="setTab('orders')" class="px-5 py-3 text-sm font-medium {{ $tab === 'orders' ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-600' }}">Recent orders ({{ $orders->count() }})</button>
        </div>

        @if($tab === 'services')
            <div class="grid md:grid-cols-3 lg:grid-cols-4 gap-3 p-4">
                @forelse($amenities as $a)
                    <div class="border rounded-lg p-4 hover:shadow-sm transition {{ !$a->is_active ? 'opacity-50' : '' }}">
                        <div class="flex items-start justify-between mb-2">
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-violet-100 text-violet-800">{{ $a->category }}</span>
                            <div class="flex gap-1">
                                <button type="button" wire:click="startEditAmenity({{ $a->id }})" class="text-xs text-slate-500 hover:text-brand-600">Edit</button>
                                @if($a->is_active)<button type="button" wire:click="deleteAmenity({{ $a->id }})" wire:confirm="Deactivate {{ $a->name }}?" class="text-xs text-rose-600 hover:underline">Disable</button>@endif
                            </div>
                        </div>
                        <div class="font-semibold text-sm">{{ $a->name }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">{{ $a->code }}</div>
                        @if($a->description)<div class="text-xs text-slate-600 mt-2 line-clamp-2">{{ $a->description }}</div>@endif
                        <div class="flex items-center justify-between mt-3 pt-2 border-t">
                            <div><div class="text-lg font-bold text-brand-700">₹{{ number_format($a->price, 0) }}</div><div class="text-[10px] text-slate-500">{{ str_replace('_', ' ', $a->pricing_type) }}</div></div>
                            <button type="button" wire:click="startOrder({{ $a->id }})" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded">Sell</button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12 text-sm text-slate-500">No amenities. <button type="button" wire:click="startCreateAmenity" class="text-brand-600 hover:underline">+ Add one</button></div>
                @endforelse
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                    <tr><th class="px-5 py-2">Order #</th><th class="px-4 py-2">Service</th><th class="px-4 py-2">Guest / Room</th><th class="px-4 py-2">Qty</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Date/Time</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $o)
                        <tr>
                            <td class="px-5 py-2 font-mono text-xs">{{ $o->order_number }}</td>
                            <td class="px-4 py-2 font-medium">{{ $o->amenity?->name ?? '—' }}<div class="text-[10px] text-slate-500">{{ $o->amenity?->category }}</div></td>
                            <td class="px-4 py-2">{{ $o->reservation?->guest_name ?? 'Walk-in' }}</td>
                            <td class="px-4 py-2">{{ $o->quantity }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($o->total_amount, 2) }}</td>
                            <td class="px-4 py-2 text-xs">{{ $o->service_date?->format('d M') }} · {{ substr($o->service_time, 0, 5) }}</td>
                            <td class="px-4 py-2">
                                @php $cls = ['pending'=>'bg-slate-100','confirmed'=>'bg-amber-100 text-amber-800','fulfilled'=>'bg-emerald-100 text-emerald-800','cancelled'=>'bg-rose-100 text-rose-800'][$o->status] ?? 'bg-slate-100'; @endphp
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $cls }}">{{ $o->status }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if($o->status === 'confirmed')<button type="button" wire:click="fulfillOrder({{ $o->id }})" class="text-xs text-emerald-700 hover:underline mr-2">Fulfill</button>@endif
                                @if(!in_array($o->status, ['cancelled','fulfilled']))<button type="button" wire:click="cancelAmenityOrder({{ $o->id }})" wire:confirm="Cancel this order?" class="text-xs text-rose-600 hover:underline">Cancel</button>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">No orders yet. <button type="button" wire:click="startOrder" class="text-brand-600 hover:underline">+ Sell something</button></td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
