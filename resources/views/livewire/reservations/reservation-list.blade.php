<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Reservations</h1>
        <a href="{{ route('reservations.new') }}" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ New booking</a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-4 mb-4 flex flex-wrap gap-3 items-center">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search guest, phone, email, booking #…"
               class="flex-1 min-w-64 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none">
        <select wire:model.live="statusFilter" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All statuses</option>
            @foreach($statuses as $s)
                <option value="{{ $s }}">{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-5 py-3 font-semibold">Reservation #</th>
                    <th class="px-4 py-3 font-semibold">Guest</th>
                    <th class="px-4 py-3 font-semibold">Arrival</th>
                    <th class="px-4 py-3 font-semibold">Departure</th>
                    <th class="px-4 py-3 font-semibold">Nights</th>
                    <th class="px-4 py-3 font-semibold">Pax</th>
                    <th class="px-4 py-3 font-semibold">Total</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($reservations as $r)
                <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('reservations.show', $r) }}'">
                    <td class="px-5 py-3 font-mono text-xs text-brand-600 hover:text-brand-700">{{ $r->reservation_number ?: '#'.$r->id }}</td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-900">{{ $r->guest_name ?: '—' }}</div>
                        <div class="text-xs text-slate-500">{{ $r->guest_phone }}</div>
                    </td>
                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($r->arrival_date)->format('d M Y') }}</td>
                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($r->departure_date)->format('d M Y') }}</td>
                    <td class="px-4 py-3">{{ $r->nights }}</td>
                    <td class="px-4 py-3">{{ $r->adults }}A {{ $r->children ? '+ '.$r->children.'C' : '' }}</td>
                    <td class="px-4 py-3 font-medium">₹{{ number_format($r->total_amount, 0) }}</td>
                    <td class="px-4 py-3">
                        @php
                            $colors = [
                                'tentative'   => 'bg-slate-100 text-slate-700',
                                'confirmed'   => 'bg-sky-100 text-sky-700',
                                'checked_in'  => 'bg-emerald-100 text-emerald-700',
                                'checked_out' => 'bg-violet-100 text-violet-700',
                                'cancelled'   => 'bg-rose-100 text-rose-700',
                                'no_show'     => 'bg-amber-100 text-amber-700',
                            ];
                            $cls = $colors[$r->status] ?? 'bg-slate-100 text-slate-700';
                        @endphp
                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $cls }}">{{ str_replace('_',' ',$r->status) }}</span>
                    </td>
                </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No reservations match your filters. <a href="{{ route('reservations.new') }}" class="text-brand-600 font-medium">Create one →</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $reservations->links() }}</div>
</div>
