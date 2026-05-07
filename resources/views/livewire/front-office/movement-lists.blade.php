<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Movement lists</h1>
        <input type="date" wire:model.live="date" class="px-3 py-1.5 border border-slate-300 rounded text-sm">
    </div>
    <p class="text-sm text-slate-600 mb-4">Operational lists for the front desk · {{ $d->format('l, d M Y') }}</p>

    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="border-b flex">
            @foreach(['arrivals'=>'Arrivals ('.$arrivals->count().')','departures'=>'Departures ('.$departures->count().')','in_house'=>'In-house ('.$inHouse->count().')','no_show'=>'No shows ('.$noShows->count().')'] as $key=>$label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" class="px-5 py-3 text-sm font-medium border-b-2 transition {{ $tab === $key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-600 hover:text-slate-900' }}">{{ $label }}</button>
            @endforeach
        </div>

        @php
            $rows = match($tab) {
                'arrivals' => $arrivals, 'departures' => $departures, 'in_house' => $inHouse, 'no_show' => $noShows,
            };
        @endphp

        @if($rows->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-slate-500">No records.</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                    <tr>
                        <th class="px-5 py-2.5 font-semibold">Reservation</th>
                        <th class="px-4 py-2.5 font-semibold">Guest</th>
                        <th class="px-4 py-2.5 font-semibold">Room</th>
                        <th class="px-4 py-2.5 font-semibold">Pax</th>
                        <th class="px-4 py-2.5 font-semibold">{{ $tab === 'arrivals' ? 'ETA' : ($tab === 'departures' ? 'ETD' : 'Departure') }}</th>
                        <th class="px-4 py-2.5 font-semibold text-right">Total / Balance</th>
                        <th class="px-4 py-2.5 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($rows as $r)
                        <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('reservations.show', $r) }}'">
                            <td class="px-5 py-2.5 font-mono text-xs text-brand-600">{{ $r->reservation_number }}</td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium">{{ $r->guest_name }}</div>
                                <div class="text-xs text-slate-500">{{ $r->guest_phone }}</div>
                            </td>
                            <td class="px-4 py-2.5">
                                @php $rr = $r->rooms->first(); @endphp
                                @if($rr?->room)
                                    <div class="font-bold">{{ $rr->room->number }}</div>
                                    <div class="text-xs text-slate-500">{{ $rr->roomType?->name }}</div>
                                @else
                                    <span class="text-xs text-slate-500">{{ $rr->roomType?->name ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">{{ $r->adults }}A {{ $r->children ? '+'.$r->children.'C' : '' }}</td>
                            <td class="px-4 py-2.5 text-xs">
                                @if($tab === 'arrivals') {{ $r->arrival_time ?: '—' }}
                                @elseif($tab === 'departures') {{ $r->departure_time ?: '—' }}
                                @else {{ \Carbon\Carbon::parse($r->departure_date)->format('d M') }}
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="font-medium">₹{{ number_format($r->total_amount, 0) }}</div>
                                <div class="text-xs {{ $r->balance_amount > 0 ? 'text-rose-600' : 'text-emerald-600' }}">Bal ₹{{ number_format($r->balance_amount, 0) }}</div>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ str_replace('_',' ',$r->status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
