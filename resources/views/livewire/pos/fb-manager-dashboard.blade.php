<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">F&B Manager Dashboard</h1>
            <p class="text-sm text-slate-500">Kitchen revenue, top items, room-service dues, GST collection</p>
        </div>
        <div class="text-xs text-slate-500 font-mono">
            {{ \Carbon\Carbon::parse($rangeFrom)->format('d M') }}
            @if($rangeFrom !== $rangeTo) — {{ \Carbon\Carbon::parse($rangeTo)->format('d M Y') }} @else, {{ \Carbon\Carbon::parse($rangeFrom)->format('Y') }} @endif
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap items-center gap-3">
        @foreach(['today'=>'Today','yesterday'=>'Yesterday','last_7'=>'Last 7 days','month'=>'This month','range'=>'Custom range'] as $key=>$label)
            <button type="button" wire:click="setPreset('{{ $key }}')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition
                    {{ $rangePreset === $key ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                {{ $label }}
            </button>
        @endforeach

        @if($rangePreset === 'range')
            <div class="flex items-center gap-2 ml-2 pl-3 border-l border-slate-200">
                <label class="text-[10px] uppercase tracking-wider text-slate-500">From</label>
                <input type="date" wire:model.live="rangeFrom" class="px-2 py-1 border border-slate-300 rounded text-xs">
                <label class="text-[10px] uppercase tracking-wider text-slate-500">To</label>
                <input type="date" wire:model.live="rangeTo" class="px-2 py-1 border border-slate-300 rounded text-xs">
            </div>
        @endif

        <div class="flex items-center gap-2 ml-auto">
            <label class="text-[10px] uppercase tracking-wider text-slate-500">Outlet</label>
            <select wire:model.live="outletId" class="px-2 py-1 border border-slate-300 rounded text-xs min-w-[160px]">
                <option value="">All outlets</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- KPI ROW: Big numbers for the active range --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 text-white rounded-xl p-5 shadow-sm">
            <div class="text-[11px] uppercase tracking-wider opacity-80 mb-1">Net revenue (settled)</div>
            <div class="text-3xl font-bold font-mono">₹{{ number_format($revenue, 0) }}</div>
            <div class="text-xs opacity-80 mt-1">{{ $orderCount }} orders · avg ₹{{ number_format($avgTicket, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 mb-1">Cash collected</div>
            <div class="text-3xl font-bold text-slate-900 font-mono">₹{{ number_format($cashCollected, 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">From settled POS payments</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 mb-1">GST collected</div>
            <div class="text-3xl font-bold text-slate-900 font-mono">₹{{ number_format($taxCollected, 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">CGST ₹{{ number_format($cgst, 0) }} · SGST ₹{{ number_format($sgst, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 mb-1">Service charge</div>
            <div class="text-3xl font-bold text-slate-900 font-mono">₹{{ number_format($serviceCharge, 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">Subtotal ₹{{ number_format($subtotal, 0) }}</div>
        </div>
    </div>

    {{-- COMPARISON CARDS: at-a-glance, regardless of selected range --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @foreach($compareCards as $label => $data)
            <div class="bg-slate-50 rounded-lg border border-slate-200 p-4">
                <div class="text-[11px] uppercase tracking-wider text-slate-500 mb-1">{{ $label }}</div>
                <div class="text-xl font-bold text-slate-900 font-mono">₹{{ number_format($data['revenue'], 0) }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5">{{ $data['orders'] }} {{ Str::plural('order', $data['orders']) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Top selling items + Payment mode breakdown --}}
    <div class="grid lg:grid-cols-3 gap-5 mb-5">
        {{-- TOP SELLING ITEMS --}}
        <div class="bg-white rounded-xl border border-slate-200 lg:col-span-2 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                <span class="font-semibold text-slate-900">Top selling items</span>
                <span class="text-[11px] text-slate-500">By revenue · top 10</span>
            </div>
            @if($topItems->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-slate-400">No settled orders in this range yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-2">#</th>
                            <th class="text-left px-5 py-2">Item</th>
                            <th class="text-right px-5 py-2">Qty</th>
                            <th class="text-right px-5 py-2">Orders</th>
                            <th class="text-right px-5 py-2">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($topItems as $i => $item)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-2.5 text-slate-400 font-mono text-xs">{{ $i + 1 }}</td>
                                <td class="px-5 py-2.5 font-medium text-slate-900">{{ $item->item_name }}</td>
                                <td class="px-5 py-2.5 text-right font-mono">{{ (int) $item->total_qty }}</td>
                                <td class="px-5 py-2.5 text-right font-mono text-slate-500">{{ (int) $item->order_count }}</td>
                                <td class="px-5 py-2.5 text-right font-mono font-bold">₹{{ number_format($item->total_amount, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- PAYMENT MODE BREAKDOWN --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 font-semibold text-slate-900">Payment modes</div>
            @if($paymentByMode->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-slate-400">No payments recorded.</div>
            @else
                @php $totalPay = max(1, (float) $paymentByMode->sum('total')); @endphp
                <div class="p-3 space-y-2">
                    @foreach($paymentByMode as $p)
                        @php $pct = round(((float) $p->total / $totalPay) * 100, 1); @endphp
                        <div>
                            <div class="flex items-baseline justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700">
                                    @switch($p->mode)
                                        @case('cash') 💵 Cash @break
                                        @case('card') 💳 Card @break
                                        @case('upi') 📱 UPI @break
                                        @case('bank_transfer') 🏦 Bank @break
                                        @case('wallet') 💰 Wallet @break
                                        @case('company_credit') 📒 City ledger @break
                                        @case('cheque') 📄 Cheque @break
                                        @default {{ ucfirst(str_replace('_',' ',$p->mode)) }}
                                    @endswitch
                                    <span class="text-[10px] text-slate-500 ml-1">×{{ $p->txns }}</span>
                                </span>
                                <span class="font-mono font-semibold text-slate-900">₹{{ number_format($p->total, 0) }}</span>
                            </div>
                            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand-500" style="width: {{ $pct }}%"></div>
                            </div>
                            <div class="text-[10px] text-slate-500 mt-0.5">{{ $pct }}%</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Order type breakdown + Room dues --}}
    <div class="grid lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 font-semibold text-slate-900">Revenue by order type</div>
            @if($orderTypeBreakdown->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-400">No data.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                        <tr><th class="text-left px-5 py-2">Type</th><th class="text-right px-5 py-2">Orders</th><th class="text-right px-5 py-2">Revenue</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($orderTypeBreakdown as $row)
                            <tr>
                                <td class="px-5 py-2.5 capitalize">{{ str_replace('_', ' ', $row->order_type) }}</td>
                                <td class="px-5 py-2.5 text-right font-mono">{{ $row->orders }}</td>
                                <td class="px-5 py-2.5 text-right font-mono font-semibold">₹{{ number_format($row->revenue, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ROOM-WISE DUE PAYMENTS (room service charged to folio, not yet settled) --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                <span class="font-semibold text-slate-900">Room-service dues by room</span>
                <span class="text-[11px] text-slate-500">In-house, not yet settled</span>
            </div>
            @if($dueByRoom->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-400">No outstanding room-service charges. ✓</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                        <tr><th class="text-left px-5 py-2">Room</th><th class="text-right px-5 py-2">Orders</th><th class="text-right px-5 py-2">Total due</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($dueByRoom as $row)
                            <tr class="hover:bg-amber-50">
                                <td class="px-5 py-2.5 font-bold text-slate-900">Room {{ $row->room_number }}</td>
                                <td class="px-5 py-2.5 text-right font-mono">{{ $row->orders }}</td>
                                <td class="px-5 py-2.5 text-right font-mono font-bold text-amber-700">₹{{ number_format($row->total_due, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td class="px-5 py-2.5 font-semibold text-slate-700">Total outstanding</td>
                            <td class="px-5 py-2.5 text-right font-mono">{{ $dueByRoom->sum('orders') }}</td>
                            <td class="px-5 py-2.5 text-right font-mono font-bold text-amber-800">₹{{ number_format($dueByRoom->sum('total_due'), 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </div>
</div>
