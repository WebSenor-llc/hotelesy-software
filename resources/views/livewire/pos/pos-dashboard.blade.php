<div @if(!$billOrder) wire:poll.15s @endif>
    {{-- Print-only styles: when window.print() is invoked, only the bill block is shown. --}}
    <style>
        @media print {
            body * { visibility: hidden !important; }
            #pos-bill-print, #pos-bill-print * { visibility: visible !important; }
            #pos-bill-print { position: absolute; left: 0; top: 0; width: 100%; padding: 0; }
            .pos-bill-noprint { display: none !important; }
            .pos-bill-print-wrapper { box-shadow: none !important; border: none !important; }
            @page { margin: 12mm; }
        }
    </style>

    {{-- Printable bill snapshot — renders at top so window.print() captures it cleanly --}}
    @if($billOrder)
        <div id="pos-bill-print" class="bg-white rounded-xl border p-8 mb-6 ring-2 ring-violet-200 pos-bill-print-wrapper">
            <div class="flex items-start justify-between mb-5 pos-bill-noprint">
                <div>
                    <span class="inline-block text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-violet-100 text-violet-800 font-semibold">{{ $billOrder->status === 'settled' ? 'Settled bill' : 'Bill' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="window.print()" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Print bill</button>
                    <button type="button" wire:click="closeBill" class="text-sm text-slate-500 px-3 py-2">Close</button>
                </div>
            </div>

            {{-- Header: Property + GSTIN --}}
            <div class="text-center mb-5 pb-4 border-b border-slate-300">
                <div class="text-xl font-bold text-slate-900">{{ $billProperty?->name ?? 'Hotel' }}</div>
                @if($billProperty?->address)
                    <div class="text-xs text-slate-600 mt-0.5">{{ $billProperty->address }}@if($billProperty->city), {{ $billProperty->city }}@endif @if($billProperty->state)· {{ $billProperty->state }}@endif</div>
                @endif
                <div class="text-xs text-slate-600 mt-0.5">
                    @if($billProperty?->phone){{ $billProperty->phone }} @endif
                    @if($billProperty?->email)· {{ $billProperty->email }}@endif
                </div>
                @if($billProperty?->gst_number)
                    <div class="text-xs text-slate-700 font-medium mt-1">GSTIN: {{ $billProperty->gst_number }}</div>
                @endif
                <div class="mt-3 inline-block text-sm font-bold tracking-wider uppercase text-slate-700">{{ $billOrder->status === 'settled' ? 'Tax Invoice' : 'Bill of Supply' }}</div>
            </div>

            {{-- Order meta --}}
            <div class="grid grid-cols-2 gap-2 text-xs mb-4">
                <div><span class="text-slate-500">Order #</span> <span class="font-mono font-semibold">{{ $billOrder->order_number }}</span></div>
                <div class="text-right"><span class="text-slate-500">Opened</span> {{ $billOrder->opened_at?->format('d M Y · H:i') }}</div>
                <div><span class="text-slate-500">Type</span> <span class="uppercase">{{ str_replace('_',' ',$billOrder->order_type) }}</span></div>
                <div class="text-right">
                    @if($billOrder->room_number)<span class="text-slate-500">Room</span> {{ $billOrder->room_number }}
                    @elseif($billOrder->table)<span class="text-slate-500">Table</span> {{ $billOrder->table->name }}
                    @endif
                </div>
                <div><span class="text-slate-500">Server</span> {{ $billOrder->server?->name ?? '—' }}</div>
                <div class="text-right"><span class="text-slate-500">Guest</span> {{ $billOrder->guest_name ?: '—' }} @if($billOrder->covers)· {{ $billOrder->covers }} pax @endif</div>
                @if($billOrder->outlet)
                    <div class="col-span-2"><span class="text-slate-500">Outlet</span> {{ $billOrder->outlet->name }}</div>
                @endif
            </div>

            {{-- Items table --}}
            <table class="w-full text-sm border-t border-b border-slate-300 mb-3">
                <thead class="text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="py-2 text-left">Item</th>
                        <th class="py-2 text-right">Qty</th>
                        <th class="py-2 text-right">Rate</th>
                        <th class="py-2 text-right">Tax %</th>
                        <th class="py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($billOrder->activeItems as $it)
                        <tr>
                            <td class="py-1.5">{{ $it->item_name }}</td>
                            <td class="py-1.5 text-right">{{ rtrim(rtrim(number_format($it->quantity, 2), '0'), '.') }}</td>
                            <td class="py-1.5 text-right">₹{{ number_format($it->unit_price, 2) }}</td>
                            <td class="py-1.5 text-right">{{ number_format($it->tax_percent, 1) }}%</td>
                            <td class="py-1.5 text-right">₹{{ number_format($it->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-center text-slate-500 text-xs">No items.</td></tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Totals --}}
            @php
                $cgst = round(((float) $billOrder->tax_amount) / 2, 2);
                $sgst = round(((float) $billOrder->tax_amount) - $cgst, 2);
                $paid = $billPayment ? (float) $billPayment->amount : (float) $billOrder->total_amount;
                $change = max(0, $paid - (float) $billOrder->total_amount);
            @endphp
            <div class="ml-auto max-w-xs text-sm space-y-1">
                <div class="flex justify-between"><span class="text-slate-600">Subtotal</span><span>₹{{ number_format($billOrder->subtotal, 2) }}</span></div>
                @if($billOrder->discount_amount > 0)
                    <div class="flex justify-between text-emerald-700"><span>Discount</span><span>− ₹{{ number_format($billOrder->discount_amount, 2) }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-slate-600">Service charge</span><span>₹{{ number_format($billOrder->service_charge, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">CGST</span><span>₹{{ number_format($cgst, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">SGST</span><span>₹{{ number_format($sgst, 2) }}</span></div>
                @if($billOrder->round_off != 0)
                    <div class="flex justify-between text-slate-600"><span>Round off</span><span>₹{{ number_format($billOrder->round_off, 2) }}</span></div>
                @endif
                <div class="border-t border-slate-300 pt-1 mt-1 flex justify-between font-bold text-base"><span>Total</span><span>₹{{ number_format($billOrder->total_amount, 2) }}</span></div>
                @if($billOrder->status === 'settled')
                    <div class="flex justify-between text-xs text-slate-600 pt-2"><span>Paid ({{ strtoupper($billPayment?->mode ?? 'room folio') }})</span><span>₹{{ number_format($paid, 2) }}</span></div>
                    @if($change > 0)
                        <div class="flex justify-between text-xs text-slate-600"><span>Change</span><span>₹{{ number_format($change, 2) }}</span></div>
                    @endif
                @endif
            </div>

            <div class="text-center text-xs text-slate-500 mt-6 pt-3 border-t border-slate-200">
                @if($billOrder->status === 'settled')
                    Settled on {{ $billOrder->settled_at?->format('d M Y · H:i') }}
                @else
                    Billed on {{ $billOrder->billed_at?->format('d M Y · H:i') }}
                @endif
                · Thank you for visiting.
            </div>
        </div>
    @endif

    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Point of Sale</h1>
        <select wire:model.live="selectedOutletId" class="px-3 py-1.5 border border-slate-300 rounded text-sm">
            @foreach($outlets as $o)
                <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->type }})</option>
            @endforeach
        </select>
    </div>
    <p class="text-sm text-slate-600 mb-6">Restaurant, bar, room-service order management. Lifecycle: Sent to kitchen → Preparing → Ready → Served → Billed → Settled.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Outlets</div><div class="text-2xl font-bold">{{ $outlets->count() }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Open orders</div><div class="text-2xl font-bold text-amber-700">{{ $openOrders->count() }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Today's orders</div><div class="text-2xl font-bold">{{ $todayStats->orders ?? 0 }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Today's revenue</div><div class="text-2xl font-bold">₹{{ number_format($todayStats->revenue ?? 0, 0) }}</div><div class="text-[10px] text-slate-400">Settled: ₹{{ number_format($todayStats->settled_revenue ?? 0, 0) }}</div></div>
    </div>

    @if(!$showNewOrder && !$settleOrder)
        <div class="mb-4 flex justify-end">
            <button type="button" wire:click="startNewOrder" class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold">+ New order (KOT)</button>
        </div>
    @endif

    @if($showNewOrder)
        {{-- Order entry mode --}}
        <div class="bg-white rounded-xl border p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">New order</h2>
                <button type="button" wire:click="cancelNewOrder" class="text-sm text-slate-500">Cancel</button>
            </div>

            <div class="grid md:grid-cols-4 gap-3 mb-3">
                <div><label class="block text-xs font-medium mb-1">Order type</label>
                    <select wire:model.live="orderType" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="dine_in">🍽 Dine in</option>
                        <option value="room_service">🛏 Room service</option>
                        <option value="takeaway">🥡 Takeaway</option>
                        <option value="delivery">🛵 Delivery</option>
                    </select>
                </div>

                {{-- Conditional: Table (dine_in) | Room/Reservation (room_service) | Phone (takeaway/delivery) --}}
                @if($orderType === 'dine_in')
                    <div><label class="block text-xs font-medium mb-1">Table number</label>
                        <select wire:model="tableId" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">Walk-in (no table)</option>
                            @foreach($tables as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->section }})</option>@endforeach
                        </select>
                    </div>
                @elseif($orderType === 'room_service')
                    <div class="md:col-span-2"><label class="block text-xs font-medium mb-1 flex items-center gap-1">🛏 Room number <span class="text-rose-500">*</span></label>
                        <select wire:model="reservationId" class="w-full px-3 py-2 border-2 rounded-lg text-sm {{ $reservationId ? 'border-emerald-300' : 'border-amber-300' }}" required>
                            <option value="">— Select in-house guest —</option>
                            @foreach($checkedInReservations as $r)
                                <option value="{{ $r->id }}">Room {{ $r->rooms?->first()?->room?->number ?? '—' }} · {{ $r->guest_name }}</option>
                            @endforeach
                        </select>
                        @if(!$reservationId)<p class="text-[10px] text-amber-700 mt-0.5">Required — order will be charged to this room's folio.</p>@endif
                    </div>
                @elseif($orderType === 'delivery')
                    <div><label class="block text-xs font-medium mb-1">Customer phone</label>
                        <input type="tel" wire:model="guestPhone" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="+91 9XXXXXXXXX">
                    </div>
                @else
                    <div><label class="block text-xs font-medium mb-1">Customer phone</label>
                        <input type="tel" wire:model="guestPhone" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Optional">
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium mb-1">Guest name</label>
                    <input type="text" wire:model="guestName" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="{{ $orderType === 'room_service' ? 'Auto-filled from reservation' : 'Walk-in' }}">
                </div>

                @if($orderType !== 'room_service')
                    <div><label class="block text-xs font-medium mb-1">Covers</label><input type="number" min="1" wire:model="covers" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                @endif
            </div>

            @if($orderType === 'delivery')
                <div class="mb-3">
                    <label class="block text-xs font-medium mb-1">Delivery address</label>
                    <textarea wire:model="deliveryAddress" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Flat / building / area"></textarea>
                </div>
            @endif

            {{-- ============= PAYMENT TIMING ============= --}}
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 mb-4">
                <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Payment</div>
                <div class="grid sm:grid-cols-3 gap-2">
                    {{-- On bill --}}
                    <label class="flex items-start gap-2 p-3 rounded-lg border-2 cursor-pointer transition {{ $paymentTiming === 'on_bill' ? 'border-brand-500 bg-brand-50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                        <input type="radio" wire:model.live="paymentTiming" value="on_bill" class="mt-0.5">
                        <div>
                            <div class="font-semibold text-sm text-slate-900">📋 Pay on bill</div>
                            <div class="text-[11px] text-slate-500">Collect when bill is generated</div>
                        </div>
                    </label>
                    {{-- Prepaid --}}
                    <label class="flex items-start gap-2 p-3 rounded-lg border-2 cursor-pointer transition {{ $paymentTiming === 'prepaid' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                        <input type="radio" wire:model.live="paymentTiming" value="prepaid" class="mt-0.5">
                        <div>
                            <div class="font-semibold text-sm text-slate-900">💵 Prepaid</div>
                            <div class="text-[11px] text-slate-500">Collect now, before kitchen prep</div>
                        </div>
                    </label>
                    {{-- Room charge (only if room_service) --}}
                    <label class="flex items-start gap-2 p-3 rounded-lg border-2 cursor-pointer transition
                        {{ $paymentTiming === 'room_charge' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 bg-white hover:border-slate-300' }}
                        {{ $orderType !== 'room_service' || !$reservationId ? 'opacity-40 cursor-not-allowed' : '' }}">
                        <input type="radio" wire:model.live="paymentTiming" value="room_charge" class="mt-0.5"
                               {{ $orderType !== 'room_service' || !$reservationId ? 'disabled' : '' }}>
                        <div>
                            <div class="font-semibold text-sm text-slate-900">🛏 Charge to room</div>
                            <div class="text-[11px] text-slate-500">Post to guest folio at settle time</div>
                        </div>
                    </label>
                </div>

                {{-- Prepaid mode picker --}}
                @if($paymentTiming === 'prepaid')
                    <div class="mt-3 pt-3 border-t border-slate-200">
                        <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">How payment was made</div>
                        <div class="flex flex-wrap items-center gap-2">
                            @php
                                $modes = [
                                    ['cash','💵','Cash'],
                                    ['card','💳','Card'],
                                    ['upi','📱','UPI'],
                                    ['wallet','👛','Wallet'],
                                    ['bank_transfer','🏦','Bank'],
                                ];
                            @endphp
                            @foreach($modes as [$mode, $icon, $label])
                                <button type="button" wire:click="$set('prepaidMode','{{ $mode }}')"
                                        class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-full border transition
                                               {{ $prepaidMode === $mode ? 'bg-emerald-600 text-white border-emerald-700' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                                    <span>{{ $icon }}</span> {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 mt-2">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Reference / txn id (optional)</label>
                                <input type="text" wire:model="prepaidReference" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="UPI ref / last 4 of card / receipt">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Amount received (₹)</label>
                                <input type="number" step="0.01" min="0" wire:model="prepaidAmount" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Defaults to bill total">
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid lg:grid-cols-3 gap-4">
                {{-- Menu (2 cols) --}}
                <div class="lg:col-span-2">

                    {{-- ============== FILTER BAR ============== --}}
                    <div class="bg-white border border-slate-200 rounded-xl p-3 mb-4 sticky top-2 z-10 shadow-sm">
                        {{-- Row 1: Search + clear --}}
                        <div class="flex items-center gap-2 mb-2">
                            <input wire:model.live.debounce.250ms="menuSearch" type="text" placeholder="Search menu… (Paneer Tikka, Coke, etc.)"
                                   class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-brand-400">
                            @if($foodTypeFilter !== 'all' || $selectedCategoryId || $menuSearch)
                                <button wire:click="clearFilters" type="button" class="text-xs text-slate-600 hover:text-slate-900 px-2 py-1 rounded hover:bg-slate-100">Clear all</button>
                            @endif
                        </div>

                        {{-- Row 2: Food-type filters --}}
                        <div class="flex items-center gap-1.5 flex-wrap mb-2">
                            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 mr-1">Diet:</span>
                            @php
                                $foodChips = [
                                    ['all',     'All',      null],
                                    ['veg',     'Veg',      'veg'],
                                    ['non_veg', 'Non-veg',  'non_veg'],
                                    ['egg',     'Egg',      'egg'],
                                    ['jain',    'Jain',     'jain'],
                                ];
                            @endphp
                            @foreach($foodChips as [$key, $label, $iconType])
                                @php
                                    $active = $foodTypeFilter === $key;
                                    $activeColor = match($key) {
                                        'veg', 'jain' => 'bg-emerald-600 text-white border-emerald-700',
                                        'non_veg' => 'bg-rose-600 text-white border-rose-700',
                                        'egg' => 'bg-amber-500 text-white border-amber-600',
                                        default => 'bg-slate-900 text-white border-slate-900',
                                    };
                                @endphp
                                <button wire:click="setFoodType('{{ $key }}')" type="button"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border transition
                                               {{ $active ? $activeColor : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                                    @if($iconType)<x-food-type-icon :type="$iconType" :size="12" />@endif
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Row 3: Category filters --}}
                        @if($allCategories->count() > 0)
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 mr-1">Category:</span>
                                <button wire:click="setCategory(null)" type="button"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-full border transition
                                               {{ $selectedCategoryId === null ? 'bg-brand-600 text-white border-brand-700' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                                    All <span class="opacity-60">{{ $allCategories->sum('items_count') }}</span>
                                </button>
                                @foreach($allCategories as $c)
                                    <button wire:click="setCategory({{ $c->id }})" type="button"
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border transition
                                                   {{ $selectedCategoryId === $c->id ? 'bg-brand-600 text-white border-brand-700' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                                        {{ $c->name }}
                                        <span class="opacity-60">{{ $c->items_count }}</span>
                                        @if($c->is_liquor)<span class="text-[9px] px-1 py-0.5 bg-rose-100 text-rose-700 rounded">L</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- ============== MENU ITEMS ============== --}}
                    @php
                        $totalItems = $menuCategories->sum(fn($c) => $c->items->count());
                    @endphp
                    @if($totalItems === 0)
                        <div class="bg-slate-50 rounded-lg p-12 text-center text-sm text-slate-500">
                            No menu items match these filters.
                            @if($foodTypeFilter !== 'all' || $selectedCategoryId || $menuSearch)
                                <button wire:click="clearFilters" type="button" class="text-brand-600 font-semibold ml-1">Clear filters →</button>
                            @endif
                        </div>
                    @else
                        @foreach($menuCategories as $cat)
                            @if($cat->items->count() === 0) @continue @endif
                            <div class="mb-4">
                                <div class="text-xs uppercase tracking-wider font-semibold text-slate-500 mb-2 flex items-center gap-2">
                                    {{ $cat->name }}
                                    <span class="text-[10px] text-slate-400 font-mono normal-case tracking-normal">{{ $cat->items->count() }} item{{ $cat->items->count() === 1 ? '' : 's' }}</span>
                                    @if($cat->is_liquor)<span class="text-[10px] px-1.5 py-0.5 bg-rose-100 text-rose-700 rounded">Liquor</span>@endif
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                    @foreach($cat->items as $mi)
                                        @php
                                            $effectiveType = $cat->is_liquor ? 'liquor' : ($mi->food_type ?: 'veg');
                                        @endphp
                                        <button type="button" wire:click="addItem({{ $mi->id }})"
                                                class="text-left bg-white border border-slate-200 rounded-lg p-3 hover:border-brand-300 hover:shadow-sm transition">
                                            <div class="flex items-start gap-2">
                                                <x-food-type-icon :type="$effectiveType" :size="14" />
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-medium text-sm text-slate-900 truncate">{{ $mi->name }}</div>
                                                    <div class="text-[11px] text-slate-500 capitalize">{{ str_replace('_',' ', $effectiveType) }}</div>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between">
                                                @if($mi->is_combo ?? false)
                                                    <span class="text-[9px] uppercase tracking-wider font-semibold px-1.5 py-0.5 bg-violet-100 text-violet-700 rounded">Combo</span>
                                                @else
                                                    <span></span>
                                                @endif
                                                <span class="font-bold text-brand-700">₹{{ number_format($mi->price, 0) }}</span>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- Cart --}}
                <div>
                    <div class="bg-slate-50 rounded-xl p-4 sticky top-4">
                        <div class="font-semibold text-slate-900 mb-3">Cart ({{ $linesView->count() }})</div>
                        @if($linesView->isEmpty())
                            <div class="text-sm text-slate-500 py-4 text-center">Tap menu items to add</div>
                        @else
                            <div class="space-y-2 mb-3 max-h-96 overflow-y-auto">
                                @foreach($linesView as $i => $l)
                                    <div class="bg-white rounded-lg p-2 flex items-center gap-2">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm">{{ $l['mi']->name }}</div>
                                            <div class="text-xs text-slate-500">₹{{ number_format($l['mi']->price, 0) }} × {{ $l['qty'] }}</div>
                                        </div>
                                        <button type="button" wire:click="changeQty({{ $i }}, -1)" class="w-6 h-6 bg-slate-200 rounded text-sm">−</button>
                                        <span class="w-6 text-center font-bold">{{ $l['qty'] }}</span>
                                        <button type="button" wire:click="changeQty({{ $i }}, 1)" class="w-6 h-6 bg-slate-200 rounded text-sm">+</button>
                                    </div>
                                @endforeach
                            </div>
                            <div class="border-t border-slate-300 pt-3 text-sm space-y-1">
                                <div class="flex justify-between"><span class="text-slate-600">Subtotal</span><span>₹{{ number_format($linesSubtotal, 2) }}</span></div>
                                <div class="text-xs text-slate-500">+ tax + service charge calculated on save</div>
                            </div>
                            <button type="button" wire:click="placeOrder" class="w-full mt-3 bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-lg font-semibold text-sm">
                                <span wire:loading.remove wire:target="placeOrder">Send to kitchen (KOT)</span>
                                <span wire:loading wire:target="placeOrder">Sending…</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Settle / payment dialog --}}
    @if($settleOrder)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-brand-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">Settle order — <span class="font-mono">{{ $settleOrder->order_number }}</span></h2>
                <button type="button" wire:click="closeSettle" class="text-sm text-slate-500">Cancel</button>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                {{-- Bill summary --}}
                <div class="bg-slate-50 rounded-lg p-4">
                    <div class="font-semibold text-slate-900 mb-3">Bill summary</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-slate-600">Subtotal</span><span>₹{{ number_format($settleOrder->subtotal, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-600">Service charge</span><span>₹{{ number_format($settleOrder->service_charge, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-600">Tax (CGST+SGST)</span><span>₹{{ number_format($settleOrder->tax_amount, 2) }}</span></div>
                        @if($settleOrder->discount_amount > 0)
                            <div class="flex justify-between text-emerald-700"><span>Discount</span><span>− ₹{{ number_format($settleOrder->discount_amount, 2) }}</span></div>
                        @endif
                        <div class="border-t border-slate-300 pt-2 mt-2 flex justify-between font-bold text-base"><span>Total payable</span><span>₹{{ number_format($settleOrder->total_amount, 2) }}</span></div>
                    </div>
                    <div class="mt-3 text-xs text-slate-500">
                        Type: <span class="uppercase">{{ str_replace('_', ' ', $settleOrder->order_type) }}</span>
                        @if($settleOrder->reservation)
                            <br>Guest: {{ $settleOrder->reservation->guest_name }}
                            @if($settleOrder->room_number) · Room {{ $settleOrder->room_number }}@endif
                        @endif
                    </div>
                </div>

                {{-- Payment form --}}
                <div>
                    @if($settleOrder->order_type === 'room_service' && $settleOrder->reservation_id)
                        <label class="flex items-start gap-2 mb-3 p-3 bg-violet-50 border border-violet-200 rounded-lg cursor-pointer">
                            <input type="checkbox" wire:model.live="chargeToRoom" class="mt-1">
                            <div class="text-sm">
                                <div class="font-medium text-violet-900">Charge to room folio</div>
                                <div class="text-xs text-violet-700">Posts F&B / Beverage charges to {{ $settleOrder->reservation->guest_name }}'s folio. Settles at checkout.</div>
                            </div>
                        </label>
                    @endif

                    @if(!$chargeToRoom)
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium mb-1">Payment mode</label>
                                <select wire:model="payMode" class="w-full px-3 py-2 border rounded-lg text-sm">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="upi">UPI</option>
                                    <option value="bank_transfer">Bank transfer</option>
                                    <option value="wallet">Wallet</option>
                                    <option value="gift_voucher">Gift voucher</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1">Amount</label>
                                <input type="number" step="0.01" wire:model="payAmount" class="w-full px-3 py-2 border rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1">Reference / approval code (optional)</label>
                                <input type="text" wire:model="payReference" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="UPI ref, card approval, voucher #">
                            </div>
                        </div>
                    @else
                        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 text-sm text-violet-900">
                            <div class="font-semibold mb-1">Charge to room — no cash collected</div>
                            The total of <span class="font-bold">₹{{ number_format($settleOrder->total_amount, 2) }}</span> will be added to the guest's folio.
                            They'll settle at checkout.
                        </div>
                    @endif

                    <button type="button" wire:click="confirmSettle" class="w-full mt-4 bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-lg font-semibold text-sm">
                        <span wire:loading.remove wire:target="confirmSettle">{{ $chargeToRoom ? 'Charge to room & close order' : 'Take payment & settle' }}</span>
                        <span wire:loading wire:target="confirmSettle">Processing…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Open orders list --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold">Active orders ({{ $openOrders->count() }})</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr><th class="px-5 py-2">Order #</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Guest / Table / Room</th><th class="px-4 py-2">Covers</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Payment</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($openOrders as $o)
                    <tr>
                        <td class="px-5 py-2 font-mono text-xs">{{ $o->order_number }}<div class="text-[10px] text-slate-500">{{ $o->opened_at?->format('d M H:i') }}</div></td>
                        <td class="px-4 py-2 text-xs uppercase">{{ str_replace('_',' ',$o->order_type) }}</td>
                        <td class="px-4 py-2">{{ $o->guest_name ?: '—' }}@if($o->room_number) <span class="text-xs text-slate-500">· Room {{ $o->room_number }}</span>@elseif($o->table_id) <span class="text-xs text-slate-500">· Table</span>@endif</td>
                        <td class="px-4 py-2">{{ $o->covers }}</td>
                        <td class="px-4 py-2 text-right font-medium">₹{{ number_format($o->total_amount, 2) }}</td>
                        <td class="px-4 py-2">
                            @php
                                $pt = $o->payment_timing ?? 'on_bill';
                                $pmCfg = match($pt) {
                                    'prepaid'     => ['bg-emerald-100 text-emerald-700', '✓ Prepaid'],
                                    'room_charge' => ['bg-violet-100 text-violet-700', '🛏 Room'],
                                    default       => ['bg-amber-100 text-amber-700', '📋 On bill'],
                                };
                                $modeIcon = match($o->payment_mode ?? '') {
                                    'cash'=>'💵','card'=>'💳','upi'=>'📱','wallet'=>'👛','bank_transfer'=>'🏦', default=>''
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1 text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $pmCfg[0] }}">{{ $pmCfg[1] }}</span>
                            @if($o->payment_mode)
                                <div class="text-[10px] text-slate-500 mt-0.5">{{ $modeIcon }} {{ strtoupper($o->payment_mode) }}@if($o->payment_reference) · {{ \Illuminate\Support\Str::limit($o->payment_reference, 14) }}@endif</div>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @php $st = ['open'=>'bg-slate-100','sent_to_kitchen'=>'bg-amber-100 text-amber-800','preparing'=>'bg-orange-100 text-orange-800','ready'=>'bg-emerald-100 text-emerald-800','served'=>'bg-sky-100 text-sky-800','billed'=>'bg-violet-100 text-violet-800'][$o->status] ?? 'bg-slate-100'; @endphp
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $st }}">{{ str_replace('_',' ',$o->status) }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="inline-flex items-center gap-1.5 flex-wrap justify-end">
                                {{-- Mark served: when KDS-ready --}}
                                @if($o->status === 'ready')
                                    <button type="button" wire:click="markServed({{ $o->id }})" class="text-xs px-2 py-1 bg-sky-100 text-sky-800 rounded hover:bg-sky-200" title="Mark order served to guest">Serve</button>
                                @endif

                                {{-- Generate bill: any non-finalized state --}}
                                @if(! in_array($o->status, ['settled','voided','billed']))
                                    <button type="button" wire:click="generateBill({{ $o->id }})" class="text-xs px-2 py-1 bg-violet-100 text-violet-800 rounded hover:bg-violet-200 font-semibold" title="Generate bill (skips kitchen if needed)">Generate bill</button>
                                @endif

                                {{-- View bill: any state with a generated bill --}}
                                @if(in_array($o->status, ['billed','settled']))
                                    <button type="button" wire:click="openBill({{ $o->id }})" class="text-xs px-2 py-1 bg-slate-100 text-slate-700 rounded hover:bg-slate-200">View bill</button>
                                @endif

                                {{-- Settle: bill must exist --}}
                                @if(in_array($o->status, ['served','billed']))
                                    <button type="button" wire:click="openSettle({{ $o->id }})" class="text-xs px-2 py-1 bg-emerald-600 text-white rounded hover:bg-emerald-700 font-semibold">Settle</button>
                                @endif

                                {{-- Void --}}
                                @if(! in_array($o->status, ['settled','voided']))
                                    <button type="button" wire:click="voidOrder({{ $o->id }})" wire:confirm="Void order {{ $o->order_number }}?" class="text-xs text-rose-600 hover:underline">Void</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No active orders. Tap "+ New order (KOT)" to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recently settled today --}}
    @if($recentSettled->isNotEmpty())
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold">Settled / closed today ({{ $recentSettled->count() }})</div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                    <tr><th class="px-5 py-2">Order #</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Guest</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Settled at</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($recentSettled as $o)
                        <tr>
                            <td class="px-5 py-2 font-mono text-xs">{{ $o->order_number }}</td>
                            <td class="px-4 py-2 text-xs uppercase">{{ str_replace('_',' ',$o->order_type) }}</td>
                            <td class="px-4 py-2">{{ $o->guest_name ?: '—' }}@if($o->room_number) <span class="text-xs text-slate-500">· {{ $o->room_number }}</span>@endif</td>
                            <td class="px-4 py-2 text-right font-medium">₹{{ number_format($o->total_amount, 2) }}</td>
                            <td class="px-4 py-2 text-xs">{{ $o->settled_at?->format('H:i') }}</td>
                            <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $o->status === 'settled' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ $o->status }}</span></td>
                            <td class="px-4 py-2 text-right">
                                @if($o->status === 'settled')
                                    <button type="button" wire:click="openBill({{ $o->id }})" class="text-xs px-2 py-1 bg-slate-100 text-slate-700 rounded hover:bg-slate-200">View bill</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
