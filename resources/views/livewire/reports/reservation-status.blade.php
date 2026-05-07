<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">Reservation status report</h1>
        <div class="flex gap-2 items-center text-sm">
            <input type="date" wire:model.live="startDate" class="px-3 py-1.5 border rounded">
            <span class="text-slate-500">→</span>
            <input type="date" wire:model.live="endDate" class="px-3 py-1.5 border rounded">
            <button onclick="window.print()" class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm">Print</button>
            <button type="button" wire:click="exportCsv" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Export CSV</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Confirmed / cancelled / no-show / in-house breakdown · {{ $start->format('d M') }} – {{ $end->format('d M Y') }}</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Total reservations</div><div class="text-2xl font-bold">{{ $totals['total'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Booked revenue</div><div class="text-2xl font-bold">₹{{ number_format($totals['revenue'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">No-show rate</div><div class="text-2xl font-bold {{ $kpis['noShowRate'] > 5 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $kpis['noShowRate'] }}%</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Cancelled %</div><div class="text-2xl font-bold {{ $totals['cancelled_pct'] > 10 ? 'text-rose-600' : 'text-amber-600' }}">{{ $totals['cancelled_pct'] }}%</div></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold">By status</div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Status</th><th class="px-4 py-2 text-right">Count</th><th class="px-4 py-2 text-right">Revenue</th><th class="px-4 py-2 text-right">Paid</th><th class="px-4 py-2 text-right">Balance</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($byStatus as $s)
                        @php
                            $cls = match($s->status) {
                                'tentative' => 'text-slate-700', 'confirmed' => 'text-sky-700',
                                'checked_in' => 'text-emerald-700', 'checked_out' => 'text-violet-700',
                                'cancelled' => 'text-rose-700', 'no_show' => 'text-amber-700',
                                default => 'text-slate-700',
                            };
                        @endphp
                        <tr><td class="px-5 py-2 capitalize {{ $cls }} font-medium">{{ str_replace('_',' ',$s->status) }}</td><td class="px-4 py-2 text-right font-bold">{{ $s->cnt }}</td><td class="px-4 py-2 text-right">₹{{ number_format($s->revenue, 0) }}</td><td class="px-4 py-2 text-right text-emerald-700">₹{{ number_format($s->paid, 0) }}</td><td class="px-4 py-2 text-right text-rose-700">₹{{ number_format($s->balance, 0) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No reservations in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold">By market segment</div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Segment</th><th class="px-4 py-2 text-right">Reservations</th><th class="px-4 py-2 text-right">Revenue</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bySegment as $s)
                        <tr><td class="px-5 py-2">{{ $s->segment }}</td><td class="px-4 py-2 text-right">{{ $s->cnt }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($s->revenue, 0) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold">By source</div>
            <div class="overflow-y-auto max-h-72">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b sticky top-0"><tr><th class="px-5 py-2">Source</th><th class="px-4 py-2 text-right">Reservations</th><th class="px-4 py-2 text-right">Revenue</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bySource as $s)
                        <tr><td class="px-5 py-2">{{ $s->src }}</td><td class="px-4 py-2 text-right">{{ $s->cnt }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($s->revenue, 0) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold">Top corporate accounts</div>
            <div class="overflow-y-auto max-h-72">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b sticky top-0"><tr><th class="px-5 py-2">Company</th><th class="px-4 py-2">GST</th><th class="px-4 py-2 text-right">Bookings</th><th class="px-4 py-2 text-right">Revenue</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($byCompany as $c)
                        <tr><td class="px-5 py-2 font-medium">{{ $c->company?->name ?? '—' }}</td><td class="px-4 py-2 font-mono text-[10px]">{{ $c->company?->gst_number ?? '—' }}</td><td class="px-4 py-2 text-right">{{ $c->cnt }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($c->revenue, 0) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">No corporate bookings.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
