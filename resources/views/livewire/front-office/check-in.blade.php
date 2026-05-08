<div>
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Check-in</h1>
    <p class="text-sm text-slate-600 mb-6">Today's expected arrivals — click one to assign a room.</p>

    {{-- Flash messages — sticky so the cashier sees confirmation even after the list re-renders. --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm flex items-start gap-2"
             x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition>
            <span class="flex-1"><strong>✓</strong> {{ session('success') }}</span>
            <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-900 font-bold leading-none">×</button>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <strong>✗</strong> {{ session('error') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-sm">
            <strong>⚠</strong> {{ session('warning') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <div class="font-semibold mb-1">Please fix:</div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

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
                        @php $isDirty = $room->status === 'vacant_dirty'; @endphp
                        <button type="button" wire:click="$set('selectedRoomId', {{ $room->id }})"
                                title="{{ $isDirty ? 'Vacant but not yet cleaned — coordinate with housekeeping before handing keys' : 'Ready for check-in' }}"
                                class="rounded-lg border-2 px-3 py-3 text-center transition {{ $selectedRoomId === $room->id ? 'border-brand-600 bg-brand-50 text-brand-700' : ($isDirty ? 'border-amber-300 bg-amber-50/50 hover:border-amber-500' : 'border-slate-200 hover:border-brand-300') }}">
                            <div class="font-bold">{{ $room->number }}</div>
                            <div class="text-[10px] text-slate-500">F{{ $room->floor }}</div>
                            @if($isDirty)
                                <div class="text-[9px] font-semibold text-amber-700 mt-0.5 uppercase tracking-wide">Dirty</div>
                            @endif
                        </button>
                    @endforeach
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Key card number</label>
                    <input type="text" wire:model="keyCardNumber" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Optional">
                </div>

                {{-- ID PROOF — legal compliance: every checked-in guest must have
                     an ID on file. Cashier scans/uploads at check-in. --}}
                <div class="border-t border-slate-200 pt-3 mb-3">
                    <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">ID proof (legal compliance)</div>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">ID type</label>
                            <select wire:model="id_type" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                                <option value="aadhaar">Aadhaar</option>
                                <option value="passport">Passport</option>
                                <option value="voter">Voter ID</option>
                                <option value="driving_license">Driving licence</option>
                                <option value="pan">PAN</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">ID number *</label>
                            <input type="text" wire:model="id_number" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">ID document files (jpg / png / pdf, max 5 MB each)</label>
                        <input type="file" wire:model="idProofUploads" multiple
                               accept="image/*,application/pdf"
                               class="w-full px-2 py-1.5 border border-slate-300 rounded text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-slate-100 file:text-slate-700">
                        <div class="text-[10px] text-slate-500 mt-1">Front, back, selfie — multi-upload supported. Stored against this guest's profile.</div>
                        <div wire:loading wire:target="idProofUploads" class="text-[10px] text-brand-600 mt-1">Uploading…</div>
                    </div>
                </div>

                {{-- FOREIGN NATIONAL — only show fields when toggled on --}}
                <div class="border-t border-slate-200 pt-3 mb-3">
                    <label class="flex items-center gap-2 text-sm cursor-pointer mb-2">
                        <input type="checkbox" wire:model.live="is_foreign_national" class="rounded">
                        <span class="font-semibold text-amber-900">🇮🇳 Foreign national — Form C / FRRO required</span>
                    </label>
                    @if($is_foreign_national)
                        <div class="grid grid-cols-2 gap-2 mb-2 bg-amber-50/50 p-3 rounded-lg border border-amber-200">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Nationality (ISO 2)</label>
                                <input type="text" wire:model="nationality" maxlength="2" class="w-full px-2 py-2 border border-slate-300 rounded text-sm uppercase font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Passport no. *</label>
                                <input type="text" wire:model="passport_number" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Passport expiry</label>
                                <input type="date" wire:model="passport_expiry" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Visa no. *</label>
                                <input type="text" wire:model="visa_number" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Visa expiry</label>
                                <input type="date" wire:model="visa_expiry" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Arrived in India on</label>
                                <input type="date" wire:model="arrival_date_in_india" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Arrived from</label>
                                <input type="text" wire:model="arrival_from_country" class="w-full px-2 py-2 border border-slate-300 rounded text-sm" placeholder="Country">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Next destination</label>
                                <input type="text" wire:model="next_destination" class="w-full px-2 py-2 border border-slate-300 rounded text-sm">
                            </div>
                        </div>
                    @endif
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
