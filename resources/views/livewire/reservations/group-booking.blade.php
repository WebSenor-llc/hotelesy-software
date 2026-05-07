<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">New group booking</h1>
        <a href="{{ route('reservations.index') }}" class="text-sm text-slate-600">← Back to reservations</a>
    </div>
    <p class="text-sm text-slate-600 mb-5">Block multiple rooms across different room types under one master booking. Single contact, single folio.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <div class="font-semibold mb-1">Please fix these issues:</div>
            <ul class="list-disc list-inside text-xs">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form wire:submit="save" class="space-y-5">
        {{-- ===== Group identity ===== --}}
        <div class="bg-white rounded-xl border p-5">
            <h3 class="font-semibold text-slate-900 mb-3">Group identity & contact</h3>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Group / event name *</label>
                    <input type="text" wire:model="groupName" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Sharma Wedding · ABC Corp Offsite">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Lead contact name *</label>
                    <input type="text" wire:model="contactName" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Phone *</label>
                    <input type="tel" wire:model="contactPhone" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="+91 9XXXXXXXXX">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Email</label>
                    <input type="email" wire:model="contactEmail" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Company / corporate</label>
                    <select wire:model.live="companyId" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">— None —</option>
                        @foreach($companies as $co)<option value="{{ $co->id }}">{{ $co->name }} @if($co->gst_number)· {{ $co->gst_number }}@endif</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Source</label>
                    <select wire:model="sourceType" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="direct">Direct</option>
                        <option value="corporate">Corporate</option>
                        <option value="travel_agent">Travel agent</option>
                        <option value="ota">OTA</option>
                        <option value="walk_in">Walk-in</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="isVip"> Mark as VIP
                    </label>
                </div>
            </div>
        </div>

        {{-- ===== Stay dates ===== --}}
        <div class="bg-white rounded-xl border p-5">
            <h3 class="font-semibold text-slate-900 mb-3">Stay dates</h3>
            <div class="grid md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Arrival *</label>
                    <input type="date" wire:model.live="arrivalDate" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Departure *</label>
                    <input type="date" wire:model.live="departureDate" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">ETA</label>
                    <input type="time" wire:model="arrivalTime" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">ETD</label>
                    <input type="time" wire:model="departureTime" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-500">
                <span class="font-semibold text-slate-700">{{ $nights }}</span> night{{ $nights === 1 ? '' : 's' }} ·
                <span class="font-semibold text-slate-700">{{ $totalRooms }}</span> room{{ $totalRooms === 1 ? '' : 's' }} total
            </div>
        </div>

        {{-- ===== Room lines ===== --}}
        <div class="bg-white rounded-xl border p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-slate-900">Rooms in this group</h3>
                <button type="button" wire:click="addRoomLine" class="text-xs bg-brand-600 hover:bg-brand-700 text-white font-semibold px-3 py-1.5 rounded">+ Add room type</button>
            </div>
            <div class="space-y-3">
                @foreach($roomLines as $i => $line)
                    <div class="grid md:grid-cols-7 gap-2 items-start p-3 rounded-lg border border-slate-200 bg-slate-50">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Room type *</label>
                            <select wire:model.live="roomLines.{{ $i }}.room_type_id" class="w-full px-2 py-1.5 border rounded text-sm">
                                <option value="">— select —</option>
                                @foreach($roomTypes as $rt)<option value="{{ $rt->id }}">{{ $rt->name }} ({{ $rt->code }}) · ₹{{ number_format($rt->base_rate, 0) }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Rate plan</label>
                            <select wire:model="roomLines.{{ $i }}.rate_plan_id" class="w-full px-2 py-1.5 border rounded text-sm">
                                <option value="">BAR</option>
                                @foreach($ratePlans as $rp)<option value="{{ $rp->id }}">{{ $rp->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Qty *</label>
                            <input type="number" min="1" max="100" wire:model.live="roomLines.{{ $i }}.quantity" class="w-full px-2 py-1.5 border rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">₹ / night *</label>
                            <input type="number" step="0.01" min="0" wire:model.live="roomLines.{{ $i }}.rate_per_night" class="w-full px-2 py-1.5 border rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Adults</label>
                            <input type="number" min="1" max="10" wire:model="roomLines.{{ $i }}.adults" class="w-full px-2 py-1.5 border rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Children</label>
                            <input type="number" min="0" max="10" wire:model="roomLines.{{ $i }}.children" class="w-full px-2 py-1.5 border rounded text-sm">
                        </div>
                        <div class="md:col-span-7">
                            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">Per-room guest names (optional, comma-separated for clarity)</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="roomLines.{{ $i }}.guest_name" class="flex-1 px-2 py-1.5 border rounded text-sm" placeholder="e.g. Bride's family · Groom's family · — leave blank to auto-name as 'Lead #1, #2…'">
                                @if(count($roomLines) > 1)
                                    <button type="button" wire:click="removeRoomLine({{ $i }})" class="text-xs text-rose-600 hover:bg-rose-50 px-3 rounded">Remove</button>
                                @endif
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">
                                Line subtotal: <span class="font-mono font-semibold text-slate-800">₹{{ number_format(((float) ($line['rate_per_night'] ?? 0)) * (int) ($line['quantity'] ?? 1) * $nights, 2) }}</span>
                                ({{ $line['quantity'] ?? 1 }} room × {{ $nights }} night × ₹{{ number_format((float) ($line['rate_per_night'] ?? 0), 0) }})
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ===== Money summary ===== --}}
        <div class="bg-white rounded-xl border p-5">
            <h3 class="font-semibold text-slate-900 mb-3">Money summary</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Subtotal ({{ $totalRooms }} room × {{ $nights }} night)</span><span class="font-mono">₹{{ number_format($subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-600">GST (auto split 12% / 18% by tariff)</span><span class="font-mono">₹{{ number_format($tax, 2) }}</span></div>
                    <div class="border-t pt-2 mt-2 flex justify-between font-bold text-base"><span>Grand total</span><span class="font-mono text-brand-700">₹{{ number_format($total, 2) }}</span></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Advance to collect (₹)</label>
                        <input type="number" step="0.01" min="0" wire:model="advanceAmount" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Advance due by</label>
                        <input type="date" wire:model="advanceDueDate" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Billing to</label>
                        <select wire:model="billingTo" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="guest">Lead contact (one folio)</option>
                            <option value="company">Company</option>
                            <option value="split">Split per room</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border p-5">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Special requests (guest-visible)</label>
                    <textarea rows="2" wire:model="specialRequests" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Adjacent rooms, late check-out for group, vegetarian welcome amenities…"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Internal notes (staff only)</label>
                    <textarea rows="2" wire:model="internalNotes" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('reservations.index') }}" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded">Cancel</a>
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Create group booking · ₹{{ number_format($total, 2) }}</span>
                <span wire:loading wire:target="save">Creating…</span>
            </button>
        </div>
    </form>
</div>
