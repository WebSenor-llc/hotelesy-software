<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Reports</h1>
        <div class="flex gap-2 items-center text-sm">
            <input type="date" wire:model.live="rangeStart" class="px-3 py-1.5 border border-slate-300 rounded">
            <span class="text-slate-500">→</span>
            <input type="date" wire:model.live="rangeEnd" class="px-3 py-1.5 border border-slate-300 rounded">
            <button onclick="window.print()" class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm">Print</button>
            <button type="button" wire:click="exportCsv" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Export CSV</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Occupancy, ARR, RevPAR, payments — for the selected date range.</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Avg. occupancy</div><div class="text-2xl font-bold">{{ $kpis['avgOcc'] }}%</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">ARR (Avg Room Rate)</div><div class="text-2xl font-bold">₹{{ number_format($kpis['arr'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">RevPAR</div><div class="text-2xl font-bold">₹{{ number_format($kpis['revpar'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Room revenue</div><div class="text-2xl font-bold">₹{{ number_format($kpis['totalRevenue'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Room-nights sold</div><div class="text-2xl font-bold">{{ $kpis['totalRoomNights'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Available room-nights</div><div class="text-2xl font-bold">{{ $kpis['totalAvailableNights'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Payments collected</div><div class="text-2xl font-bold">₹{{ number_format($kpis['totalPayments'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Days in range</div><div class="text-2xl font-bold">{{ $days->count() }}</div></div>
    </div>

    <div class="bg-white rounded-xl border p-6 mb-6">
        <h2 class="font-semibold mb-3">Daily occupancy & revenue</h2>
        @php $maxOcc = max(1, $days->max('occ_pct')); $maxRev = max(1, $days->max('rev')); @endphp
        <div class="grid gap-1 mb-2" style="grid-template-columns: repeat({{ $days->count() }}, minmax(0, 1fr));">
            @foreach($days as $d)
                <div class="flex flex-col items-center" title="{{ $d['date'] }}: occ {{ $d['occ_pct'] }}%, ₹{{ number_format($d['rev'], 0) }}">
                    <div class="w-full bg-brand-100 rounded relative" style="height: 80px;">
                        <div class="absolute bottom-0 w-full bg-brand-600 rounded" style="height: {{ ($d['occ_pct'] / $maxOcc) * 100 }}%"></div>
                    </div>
                    <div class="text-[9px] text-slate-400 mt-1">{{ \Carbon\Carbon::parse($d['date'])->format('d') }}</div>
                </div>
            @endforeach
        </div>
        <div class="text-xs text-slate-500 text-center">Bars show daily occupancy %. Hover for revenue.</div>
    </div>

    <div class="bg-white rounded-xl border p-6">
        <h2 class="font-semibold mb-3">Reservations by status (created in range)</h2>
        @forelse($byStatus as $status => $count)
            <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                <span class="text-sm">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
                <span class="font-bold">{{ $count }}</span>
            </div>
        @empty
            <div class="text-sm text-slate-500 text-center py-4">No reservations in this range.</div>
        @endforelse
    </div>
</div>
