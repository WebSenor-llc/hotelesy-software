<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Availability</h1>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="shift(-7)" class="px-3 py-1.5 text-sm border border-slate-300 rounded-md hover:bg-slate-100">← Prev week</button>
            <button type="button" wire:click="jumpToToday" class="px-3 py-1.5 text-sm border border-brand-300 text-brand-700 rounded-md hover:bg-brand-50 font-semibold">Today</button>
            <button type="button" wire:click="shift(7)" class="px-3 py-1.5 text-sm border border-slate-300 rounded-md hover:bg-slate-100">Next week →</button>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700 sticky left-0 bg-slate-50">Room type</th>
                    @foreach($dates as $d)
                        <th class="px-2 py-3 text-center text-xs font-semibold {{ $d->isToday() ? 'bg-brand-50 text-brand-700' : 'text-slate-700' }}">
                            <div>{{ $d->format('d') }}</div>
                            <div class="text-[10px] text-slate-500 font-normal">{{ $d->format('D') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($roomTypes as $rt)
                    @php $total = $totalRoomsByType[$rt->id] ?? 0; @endphp
                    <tr>
                        <td class="px-4 py-3 sticky left-0 bg-white border-r border-slate-100">
                            <div class="font-semibold text-slate-900">{{ $rt->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $total }} rooms · ₹{{ number_format($rt->base_rate, 0) }}
                                @if($rt->allow_overbook)
                                    <span class="ml-1 text-[10px] uppercase tracking-wider px-1 rounded bg-orange-100 text-orange-700">+{{ $rt->overbook_limit }} OB</span>
                                @endif
                            </div>
                        </td>
                        @foreach($dates as $d)
                            @php
                                $key = $rt->id.'|'.$d->toDateString();
                                $occ = $occupied[$key] ?? 0;
                                $effectiveCapacity = $total + ($rt->allow_overbook ? (int) $rt->overbook_limit : 0);
                                $avail = max(0, $effectiveCapacity - $occ);
                                $isStopSell = !empty($stopSell[$key]);
                                $isSoldOut  = !empty($soldOut[$key]);
                                $isOverbooked = $occ > $total;
                                $pct = $total > 0 ? ($occ / $total) : 0;
                                $cellCls = match(true) {
                                    $isStopSell  => 'bg-rose-200 text-rose-900 ring-1 ring-rose-400',
                                    $isSoldOut   => 'bg-rose-100 text-rose-800',
                                    $isOverbooked=> 'bg-orange-200 text-orange-900',
                                    $avail === 0 => 'bg-rose-100 text-rose-800',
                                    $pct >= 0.7  => 'bg-amber-100 text-amber-800',
                                    $pct > 0     => 'bg-emerald-100 text-emerald-800',
                                    default      => 'bg-slate-50 text-slate-500',
                                };
                            @endphp
                            <td class="px-2 py-2 text-center">
                                <button type="button"
                                    wire:click="openCell({{ $rt->id }}, '{{ $d->toDateString() }}')"
                                    class="w-full rounded {{ $cellCls }} text-xs font-semibold py-1.5 hover:ring-2 hover:ring-brand-400 transition relative"
                                    title="{{ $isStopSell ? 'Stop-sell · ' : '' }}{{ $isSoldOut ? 'Sold-out · ' : '' }}{{ $occ }} sold of {{ $total }}">
                                    {{ $avail }}
                                    @if($isStopSell)
                                        <span class="absolute top-0 right-0 text-[8px] px-0.5 leading-none">SS</span>
                                    @elseif($isOverbooked)
                                        <span class="absolute top-0 right-0 text-[8px] px-0.5 leading-none">OB</span>
                                    @endif
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ $days + 1 }}" class="px-4 py-12 text-center text-sm text-slate-500">No room types configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center gap-4 text-xs text-slate-600 flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-200"></span>Available</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-100 border border-amber-200"></span>Tight (≥70% occ)</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-100 border border-rose-200"></span>Sold out / no inventory</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-200 ring-1 ring-rose-400"></span>Stop-sell</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-orange-200"></span>Overbooked</span>
        <span class="text-slate-500">· click any cell for inventory actions</span>
    </div>

    @if($popoverRoomTypeId && $popoverDate)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" wire:click.self="closeCell">
            <div class="bg-white rounded-xl border w-full max-w-md shadow-xl">
                <div class="px-5 py-3 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900">Inventory actions · {{ \Carbon\Carbon::parse($popoverDate)->format('D, d M Y') }}</h3>
                    <button type="button" wire:click="closeCell" class="text-slate-500 hover:text-slate-700 text-xl leading-none">&times;</button>
                </div>
                <div class="p-5 space-y-4">
                    @php $rtName = $roomTypes->firstWhere('id', $popoverRoomTypeId)?->name ?? 'Room type'; @endphp
                    <div class="text-sm text-slate-600">{{ $rtName }}</div>

                    <div class="border border-slate-200 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium">Stop-sell</div>
                            <div class="text-xs text-slate-500">Block all bookings on this date.</div>
                        </div>
                        <button type="button" wire:click="toggleStopSell"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $popStopSell ? 'bg-rose-600 text-white hover:bg-rose-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            {{ $popStopSell ? 'On — click to disable' : 'Off — click to enable' }}
                        </button>
                    </div>

                    <div class="border border-slate-200 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium">Mark sold-out manually</div>
                            <div class="text-xs text-slate-500">Independent of computed availability.</div>
                        </div>
                        <button type="button" wire:click="toggleSoldOut"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $popSoldOut ? 'bg-rose-600 text-white hover:bg-rose-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            {{ $popSoldOut ? 'On — click to clear' : 'Off — click to mark' }}
                        </button>
                    </div>

                    <div class="border border-slate-200 rounded-lg p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <div class="text-sm font-medium">Open for overbooking (room type)</div>
                                <div class="text-xs text-slate-500">Allows N rooms beyond inventory.</div>
                            </div>
                            <button type="button" wire:click="toggleOverbook"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $popAllowOverbook ? 'bg-orange-600 text-white hover:bg-orange-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                {{ $popAllowOverbook ? 'On — click to disable' : 'Off — click to enable' }}
                            </button>
                        </div>
                        @if($popAllowOverbook)
                            <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-200">
                                <span class="text-xs text-slate-600">Limit</span>
                                <input type="number" min="0" max="50" wire:model="popOverbookLimit" class="w-20 px-2 py-1 border border-slate-300 rounded text-sm text-right">
                                <button type="button" wire:click="saveOverbookLimit" class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1 rounded">Save</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
