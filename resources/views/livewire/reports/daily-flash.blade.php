<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Daily Flash Report</h1>
        <div class="flex items-center gap-2">
            <label class="text-sm text-slate-600">Business date:</label>
            <input type="date" wire:model.live="businessDate" class="px-3 py-1.5 border border-slate-300 rounded text-sm">
            <button onclick="window.print()" class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm">Print</button>
            <button type="button" wire:click="exportCsv" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Export CSV</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">{{ $date->format('l, d F Y') }} · End-of-day operational summary for management.</p>

    {{-- Headline KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-gradient-to-br from-brand-600 to-brand-800 text-white rounded-xl p-4">
            <div class="text-xs uppercase opacity-80">Occupancy</div>
            <div class="text-3xl font-bold">{{ $kpis['occPct'] }}%</div>
            <div class="text-xs opacity-80">{{ $rooms['occupied'] }} of {{ $rooms['totalRooms'] }} rooms</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">ARR</div>
            <div class="text-2xl font-bold">₹{{ number_format($kpis['arr'], 0) }}</div>
            <div class="text-xs text-slate-500">Avg Room Rate</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">RevPAR</div>
            <div class="text-2xl font-bold">₹{{ number_format($kpis['revpar'], 0) }}</div>
            <div class="text-xs text-slate-500">per available room</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Total revenue</div>
            <div class="text-2xl font-bold">₹{{ number_format($kpis['totalRevenue'], 0) }}</div>
            <div class="text-xs text-slate-500">+₹{{ number_format($kpis['totalTax'], 0) }} tax</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Payments collected</div>
            <div class="text-2xl font-bold text-emerald-700">₹{{ number_format($kpis['totalPayments'], 0) }}</div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        {{-- Rooms breakdown --}}
        <div class="bg-white rounded-xl border p-5">
            <div class="text-sm font-semibold text-slate-900 mb-3">Rooms breakdown</div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-600">Total rooms</span><span class="font-bold">{{ $rooms['totalRooms'] }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Occupied</span><span class="font-bold text-rose-600">{{ $rooms['occupied'] }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Vacant</span><span class="font-bold text-emerald-600">{{ $rooms['vacant'] }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Out of order</span><span class="font-bold text-amber-600">{{ $rooms['oos'] }}</span></div>
            </div>
        </div>

        {{-- Movement --}}
        <div class="bg-white rounded-xl border p-5">
            <div class="text-sm font-semibold text-slate-900 mb-3">Movement</div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-600">Arrivals expected</span><span class="font-bold">{{ $arrivals->whereIn('status', ['confirmed','checked_in'])->count() }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Arrivals checked-in</span><span class="font-bold text-emerald-700">{{ $arrivals->where('status','checked_in')->count() }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Departures expected</span><span class="font-bold">{{ $departures->whereIn('status', ['checked_in','checked_out'])->count() }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Departures checked-out</span><span class="font-bold text-violet-700">{{ $departures->where('status','checked_out')->count() }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">No-shows</span><span class="font-bold text-rose-600">{{ $kpis['noShows'] }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">In-house guests</span><span class="font-bold">{{ $inHouse->count() }}</span></div>
            </div>
        </div>

        {{-- Pickup --}}
        <div class="bg-white rounded-xl border p-5">
            <div class="text-sm font-semibold text-slate-900 mb-3">Pickup (bookings made today)</div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-600">New reservations</span><span class="font-bold">{{ $kpis['pickupToday'] }}</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Booked revenue</span><span class="font-bold">₹{{ number_format($kpis['pickupRevenueToday'], 0) }}</span></div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        {{-- Revenue by category --}}
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Revenue by category</div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50 border-b">
                    <tr><th class="px-5 py-2 font-semibold">Category</th><th class="px-4 py-2 text-right font-semibold">Amount</th><th class="px-4 py-2 text-right font-semibold">Tax</th><th class="px-4 py-2 text-right font-semibold">Net</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($revByCategory as $r)
                        <tr><td class="px-5 py-2 capitalize">{{ str_replace('_',' ',$r->category) }}</td><td class="px-4 py-2 text-right">₹{{ number_format($r->amount, 2) }}</td><td class="px-4 py-2 text-right text-xs">₹{{ number_format($r->tax, 2) }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($r->net, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-4 text-center text-slate-500 text-sm">No charges posted today.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Payments by mode --}}
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Payments by mode</div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50 border-b">
                    <tr><th class="px-5 py-2 font-semibold">Mode</th><th class="px-4 py-2 text-right font-semibold">Count</th><th class="px-4 py-2 text-right font-semibold">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($paymentsByMode as $p)
                        <tr><td class="px-5 py-2 capitalize">{{ str_replace('_',' ',$p->mode) }}</td><td class="px-4 py-2 text-right">{{ $p->cnt }}</td><td class="px-4 py-2 text-right font-medium text-emerald-700">₹{{ number_format($p->amount, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-4 text-center text-slate-500 text-sm">No payments recorded today.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- By source --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">In-house by source / market segment</div>
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50 border-b">
                <tr><th class="px-5 py-2 font-semibold">Source</th><th class="px-4 py-2 text-right font-semibold">Reservations</th><th class="px-4 py-2 text-right font-semibold">Revenue</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($bySource as $s)
                    <tr><td class="px-5 py-2">{{ $s->source }}</td><td class="px-4 py-2 text-right">{{ $s->cnt }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($s->revenue, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-4 text-center text-slate-500 text-sm">No active reservations on this date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Top spenders --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Top spenders today</div>
        @if($topSpenders->count())
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach($topSpenders as $g)
                        <tr><td class="px-5 py-2 font-medium"><a href="{{ route('reservations.show', $g) }}" class="text-brand-600 hover:underline">{{ $g->guest_name }}</a></td><td class="px-4 py-2 text-xs text-slate-500">{{ $g->reservation_number }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($g->today_charges ?? 0, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="px-5 py-6 text-center text-slate-500 text-sm">No charges posted to in-house folios today.</div>
        @endif
    </div>
</div>
