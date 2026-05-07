<div>
    <div class="flex items-baseline justify-between mb-2">
        <h1 class="text-2xl font-bold text-slate-900">Revenue report</h1>
        <div class="flex items-center gap-2">
            <button wire:click="exportCsv" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm font-medium">↓ Export CSV</button>
            <button onclick="window.print()" class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm">Print</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-4">
        Property revenue across rooms, F&B, banquet and ancillary streams ·
        <span class="font-medium">{{ $start->format('d M Y') }} – {{ $end->format('d M Y') }}</span>
    </p>

    {{-- Date range filter --}}
    <div class="bg-white border rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
        @php
            $btn = function($key, $label, $current) {
                $active = $current === $key;
                $cls = $active
                    ? 'bg-brand-600 text-white border-brand-600'
                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50';
                return [$cls, $label];
            };
        @endphp
        @foreach (['today'=>'Today','yesterday'=>'Yesterday','month'=>'This month','last_month'=>'Last month','custom'=>'Custom'] as $k => $label)
            @php [$cls, $lbl] = $btn($k, $label, $preset); @endphp
            <button wire:click="applyPreset('{{ $k }}')" class="px-3 py-1.5 text-sm border rounded-md {{ $cls }}">{{ $lbl }}</button>
        @endforeach

        <div class="flex items-center gap-2 ml-auto text-sm">
            <input type="date" wire:model.live="startDate" class="px-3 py-1.5 border border-slate-300 rounded">
            <span class="text-slate-500">→</span>
            <input type="date" wire:model.live="endDate" class="px-3 py-1.5 border border-slate-300 rounded">
        </div>
    </div>

    {{-- Headline KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-gradient-to-br from-brand-600 to-brand-800 text-white rounded-xl p-4">
            <div class="text-xs uppercase opacity-80">Occupancy</div>
            <div class="text-3xl font-bold">{{ $kpis['occupancyPct'] }}%</div>
            <div class="text-xs opacity-80">{{ $kpis['roomNightsSold'] }} of {{ $kpis['availableNights'] }} room-nights</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">ADR</div>
            <div class="text-2xl font-bold">₹{{ number_format($kpis['adr'], 0) }}</div>
            <div class="text-xs text-slate-500">Avg Daily Rate</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">RevPAR</div>
            <div class="text-2xl font-bold">₹{{ number_format($kpis['revpar'], 0) }}</div>
            <div class="text-xs text-slate-500">per available room</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Total revenue</div>
            <div class="text-2xl font-bold">₹{{ number_format($kpis['totalRev'], 0) }}</div>
            <div class="text-xs text-slate-500">+₹{{ number_format($kpis['totalTaxes'], 0) }} tax</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Payments collected</div>
            <div class="text-2xl font-bold text-emerald-700">₹{{ number_format($kpis['totalPayments'], 0) }}</div>
        </div>
    </div>

    {{-- Revenue breakdown --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border p-5 lg:col-span-2">
            <div class="font-semibold text-slate-900 mb-3">Revenue breakdown</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="py-2">Room revenue</td><td class="py-2 text-right font-medium">₹{{ number_format($breakdown['room'], 2) }}</td></tr>
                    <tr><td class="py-2">F&B revenue</td><td class="py-2 text-right font-medium">₹{{ number_format($breakdown['fb'], 2) }}</td></tr>
                    <tr><td class="py-2">Banquet revenue</td><td class="py-2 text-right font-medium">₹{{ number_format($breakdown['banquet'], 2) }}</td></tr>
                    <tr>
                        <td class="py-2">Other ancillary <span class="text-xs text-slate-500">(laundry / spa / amenity / standalone POS)</span></td>
                        <td class="py-2 text-right font-medium">₹{{ number_format($breakdown['ancillary'], 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-slate-200">
                        <td class="py-2 font-semibold">Net revenue</td>
                        <td class="py-2 text-right font-bold">₹{{ number_format($kpis['totalRev'], 2) }}</td>
                    </tr>
                    <tr><td class="py-2 text-slate-600">Taxes</td><td class="py-2 text-right">₹{{ number_format($breakdown['taxes'], 2) }}</td></tr>
                    <tr class="bg-slate-50">
                        <td class="py-2 font-semibold">Grand total</td>
                        <td class="py-2 text-right font-bold text-brand-700">₹{{ number_format($kpis['grandTotal'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border p-5">
            <div class="font-semibold text-slate-900 mb-3">Ancillary detail</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="py-2">Laundry / spa / misc charges</td><td class="py-2 text-right">₹{{ number_format($breakdown['other'], 2) }}</td></tr>
                    <tr><td class="py-2">Amenity orders</td><td class="py-2 text-right">₹{{ number_format($breakdown['amenity'], 2) }}</td></tr>
                    <tr><td class="py-2">Standalone POS</td><td class="py-2 text-right">₹{{ number_format($breakdown['pos'], 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Revenue by source --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Revenue by source</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2 font-semibold">Source</th>
                    <th class="px-4 py-2 text-right font-semibold">Bookings</th>
                    <th class="px-4 py-2 text-right font-semibold">Rooms</th>
                    <th class="px-4 py-2 text-right font-semibold">Room revenue</th>
                    <th class="px-4 py-2 text-right font-semibold">Total amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($bySourceRows as $row)
                    <tr>
                        <td class="px-5 py-2.5">
                            <span class="px-2 py-0.5 text-xs bg-slate-100 rounded">{{ str_replace('_',' ', $row->source_type) }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right">{{ $row->bookings }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $row->rooms }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format((float) $row->room_revenue, 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ number_format((float) $row->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No reservations in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Revenue by room type --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Revenue by room type</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2 font-semibold">Room type</th>
                    <th class="px-4 py-2 text-right font-semibold">Rooms booked</th>
                    <th class="px-4 py-2 text-right font-semibold">Room nights</th>
                    <th class="px-4 py-2 text-right font-semibold">Room revenue</th>
                    <th class="px-4 py-2 text-right font-semibold">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($byRoomTypeRows as $row)
                    <tr>
                        <td class="px-5 py-2.5 font-medium">{{ $row->room_type }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $row->rooms_booked }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $row->room_nights }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format((float) $row->room_revenue, 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ number_format((float) $row->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No room bookings in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
