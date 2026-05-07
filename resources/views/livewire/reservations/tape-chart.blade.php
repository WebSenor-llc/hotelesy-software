<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Tape chart</h1>
        <div class="flex items-center gap-2 text-sm">
            <button type="button" wire:click="shift(-7)" class="px-3 py-1.5 border border-slate-300 rounded">← Week</button>
            <button type="button" wire:click="shift(-1)" class="px-2 py-1.5 border border-slate-300 rounded">−1</button>
            <button type="button" wire:click="jumpToday" class="px-3 py-1.5 bg-brand-50 border border-brand-200 text-brand-700 rounded">Today</button>
            <button type="button" wire:click="shift(1)" class="px-2 py-1.5 border border-slate-300 rounded">+1</button>
            <button type="button" wire:click="shift(7)" class="px-3 py-1.5 border border-slate-300 rounded">Week →</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-4">{{ $start->format('d M') }} – {{ $end->format('d M Y') }} · drag a bar onto another room of the same type to reassign · click to open</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b sticky top-0 z-10">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-slate-700 sticky left-0 bg-slate-50 min-w-32 border-r">Room</th>
                    @foreach($dates as $d)
                        <th class="px-1 py-2 text-center min-w-12 {{ $d->isToday() ? 'bg-brand-100 text-brand-800' : ($d->isWeekend() ? 'bg-slate-100' : '') }}">
                            <div class="text-xs font-bold">{{ $d->format('d') }}</div>
                            <div class="text-[10px] font-normal text-slate-500">{{ $d->format('D') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($rooms as $room)
                    <tr class="relative">
                        <td class="px-3 py-1.5 sticky left-0 bg-white border-r font-medium">
                            <div class="font-bold text-slate-900">{{ $room->number }}</div>
                            <div class="text-[10px] text-slate-500">{{ $room->roomType?->code }} · F{{ $room->floor }}</div>
                        </td>
                        <td colspan="{{ count($dates) }}" class="relative p-0 h-12">
                            <div class="grid h-full" style="grid-template-columns: repeat({{ count($dates) }}, minmax(0, 1fr));">
                                @foreach($dates as $d)
                                    <div class="border-r border-slate-100 h-full transition-colors {{ $d->isToday() ? 'bg-brand-50/40' : ($d->isWeekend() ? 'bg-slate-50' : '') }}"
                                         data-room-id="{{ $room->id }}"
                                         data-date="{{ $d->toDateString() }}"
                                         x-data
                                         @dragover.prevent="$el.classList.add('bg-emerald-100')"
                                         @dragleave="$el.classList.remove('bg-emerald-100')"
                                         @drop.prevent="
                                            $el.classList.remove('bg-emerald-100');
                                            const rrId = parseInt($event.dataTransfer.getData('reservation-room-id'));
                                            if (rrId) { $wire.reassignRoom(rrId, {{ $room->id }}, '{{ $d->toDateString() }}'); }
                                         "></div>
                                @endforeach
                            </div>
                            @foreach($bars[$room->id] ?? [] as $bar)
                                @php
                                    $offsetPct = ($bar['offset'] / count($dates)) * 100;
                                    $widthPct  = ($bar['length'] / count($dates)) * 100;
                                @endphp
                                <a href="{{ route('reservations.show', $bar['r']) }}"
                                   class="absolute top-1 bottom-1 rounded {{ $bar['color'] }} px-2 py-0.5 text-xs font-semibold flex items-center hover:ring-2 hover:ring-offset-1 hover:ring-brand-500 transition shadow-sm cursor-grab active:cursor-grabbing"
                                   style="left: calc({{ $offsetPct }}% + 2px); width: calc({{ $widthPct }}% - 4px);"
                                   title="{{ $bar['r']->guest_name }} · {{ $bar['r']->reservation_number }} · {{ $bar['r']->status }} (drag to reassign)"
                                   draggable="true"
                                   x-data
                                   @dragstart="$event.dataTransfer.setData('reservation-room-id', '{{ $bar['rr_id'] }}'); $event.dataTransfer.effectAllowed = 'move';"
                                   @click="if($event.defaultPrevented) return;">
                                    <span class="truncate">{{ $bar['r']->guest_name ?: 'Guest' }}</span>
                                </a>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center gap-4 text-xs flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-300"></span>Tentative</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-sky-400"></span>Confirmed</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500"></span>Checked in</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-violet-400"></span>Checked out</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-400"></span>No show</span>
    </div>
</div>
