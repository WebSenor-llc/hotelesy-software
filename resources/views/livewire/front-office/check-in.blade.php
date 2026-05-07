<div>
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Check-in</h1>
    <p class="text-sm text-slate-600 mb-6">Today's expected arrivals — click one to assign a room.</p>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Arrivals list --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 font-semibold text-slate-900">Arrivals ({{ $arrivals->count() }})</div>
            @forelse($arrivals as $a)
                <button type="button" wire:click="selectReservation({{ $a->id }})" class="w-full text-left px-5 py-3 border-b border-slate-100 hover:bg-brand-50 transition {{ $selectedReservationId === $a->id ? 'bg-brand-50' : '' }}">
                    <div class="flex items-start justify-between mb-1">
                        <div class="font-semibold text-slate-900">{{ $a->guest_name ?: 'Guest' }}</div>
                        <span class="font-mono text-[10px] text-slate-500">{{ $a->reservation_number }}</span>
                    </div>
                    <div class="text-xs text-slate-500">
                        {{ $a->nights }}N · {{ $a->adults }}A{{ $a->children ? '+'.$a->children.'C' : '' }} ·
                        ₹{{ number_format($a->total_amount, 0) }}
                    </div>
                </button>
            @empty
                <div class="px-5 py-12 text-center text-sm text-slate-500">No arrivals expected today.</div>
            @endforelse
        </div>

        {{-- Action panel --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            @if($selectedReservationId && $availableRooms->count())
                <h3 class="font-semibold text-slate-900 mb-4">Assign a room</h3>
                <div class="grid grid-cols-4 gap-2 mb-4 max-h-64 overflow-y-auto">
                    @foreach($availableRooms as $room)
                        <button type="button" wire:click="$set('selectedRoomId', {{ $room->id }})"
                                class="rounded-lg border-2 px-3 py-3 text-center transition {{ $selectedRoomId === $room->id ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 hover:border-brand-300' }}">
                            <div class="font-bold">{{ $room->number }}</div>
                            <div class="text-[10px] text-slate-500">F{{ $room->floor }}</div>
                        </button>
                    @endforeach
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Key card number</label>
                    <input type="text" wire:model="keyCardNumber" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Optional">
                </div>
                <button type="button" wire:click="checkIn"
                        @disabled(!$selectedRoomId)
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="checkIn">Check in to room {{ $selectedRoomId ? $availableRooms->firstWhere('id', $selectedRoomId)?->number : '…' }}</span>
                    <span wire:loading wire:target="checkIn">Processing…</span>
                </button>
            @elseif($selectedReservationId)
                <div class="text-center py-8 text-sm text-rose-600">No vacant rooms of the required type. Try housekeeping.</div>
            @else
                <div class="text-center py-12 text-sm text-slate-400">Select a reservation on the left to begin.</div>
            @endif
        </div>
    </div>
</div>
