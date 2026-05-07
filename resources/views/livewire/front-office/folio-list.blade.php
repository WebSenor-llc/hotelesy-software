<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Folios</h1>
            <p class="text-sm text-slate-500">Every guest folio · view, settle, issue invoice</p>
        </div>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-amber-700 font-semibold">Open folios</div>
            <div class="text-2xl font-bold text-amber-900">{{ $stats['open'] }}</div>
        </div>
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-rose-700 font-semibold">Open balance total</div>
            <div class="text-2xl font-bold text-rose-900">₹{{ number_format($stats['open_balance_total'], 0) }}</div>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-slate-700 font-semibold">Closed</div>
            <div class="text-2xl font-bold text-slate-900">{{ $stats['closed'] }}</div>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-emerald-700 font-semibold">Settled</div>
            <div class="text-2xl font-bold text-emerald-900">{{ $stats['settled'] }}</div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border border-slate-200 p-3 mb-4 flex flex-wrap items-center gap-2">
        @foreach([
            ['open','Open'],
            ['closed','Closed (unpaid)'],
            ['settled','Settled'],
            ['all','All'],
        ] as [$key, $label])
            <button wire:click="$set('statusFilter','{{ $key }}')"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full {{ $statusFilter === $key ? 'bg-brand-600 text-white' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                {{ $label }}
            </button>
        @endforeach

        <input wire:model.live.debounce.300ms="search" type="text"
               placeholder="Search folio #, guest, GSTIN, reservation #…"
               class="ml-auto px-3 py-1.5 border border-slate-300 rounded-lg text-sm w-72">

        <input wire:model.live="dateFrom" type="date" class="px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
        <span class="text-xs text-slate-500">to</span>
        <input wire:model.live="dateTo" type="date" class="px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
    </div>

    {{-- Folios table --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2.5">Folio #</th>
                    <th class="px-3 py-2.5">Guest / Billing</th>
                    <th class="px-3 py-2.5">Reservation</th>
                    <th class="px-3 py-2.5 text-right">Charges</th>
                    <th class="px-3 py-2.5 text-right">Paid</th>
                    <th class="px-3 py-2.5 text-right">Balance</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5">GST invoice</th>
                    <th class="px-4 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($folios as $f)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-mono text-xs font-semibold text-slate-900">{{ $f->folio_number }}</div>
                            <div class="text-[10px] text-slate-500">{{ $f->created_at?->format('d M Y · H:i') }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="font-medium text-slate-900">{{ $f->billing_name ?: ($f->reservation?->guest_name ?? '—') }}</div>
                            @if($f->billing_gst)
                                <div class="text-[10px] font-mono text-slate-500">GSTIN: {{ $f->billing_gst }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            @if($f->reservation)
                                <a href="{{ route('reservations.show', $f->reservation->id) }}" class="text-xs font-mono text-brand-600 hover:underline">{{ $f->reservation->reservation_number }}</a>
                                <div class="text-[10px] text-slate-500">{{ $f->reservation->nights }}N · {{ \Carbon\Carbon::parse($f->reservation->arrival_date)->format('d M') }} → {{ \Carbon\Carbon::parse($f->reservation->departure_date)->format('d M') }}</div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-xs">₹{{ number_format($f->total_charges, 2) }}</td>
                        <td class="px-3 py-3 text-right font-mono text-xs text-emerald-700">₹{{ number_format($f->total_payments, 2) }}</td>
                        <td class="px-3 py-3 text-right font-mono text-xs font-bold {{ $f->balance > 0 ? 'text-rose-600' : 'text-emerald-700' }}">₹{{ number_format($f->balance, 2) }}</td>
                        <td class="px-3 py-3">
                            @php
                                $cls = match($f->status) {
                                    'open' => 'bg-amber-100 text-amber-700',
                                    'closed' => 'bg-slate-100 text-slate-700',
                                    'settled' => 'bg-emerald-100 text-emerald-700',
                                    'voided' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $cls }}">{{ $f->status }}</span>
                        </td>
                        <td class="px-3 py-3">
                            @if($invId = $invoiceMap[$f->id] ?? null)
                                <a href="{{ route('invoice.show', $invId) }}" class="text-xs text-emerald-700 hover:underline font-semibold">⚖ Issued</a>
                            @else
                                <span class="text-[10px] text-slate-400">Not issued</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('folio.show', $f->id) }}" class="text-xs bg-brand-600 hover:bg-brand-700 text-white font-semibold px-3 py-1.5 rounded">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">
                        No folios match these filters. @if($statusFilter !== 'all')<button wire:click="$set('statusFilter','all')" class="text-brand-600 font-semibold">Show all →</button>@endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3 border-t bg-slate-50">{{ $folios->links() }}</div>
    </div>
</div>
