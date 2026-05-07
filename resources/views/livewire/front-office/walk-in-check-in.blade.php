<div class="max-w-5xl">
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Walk-in check-in</h1>
    <p class="text-sm text-slate-600 mb-6">Single-screen flow: capture guest, ID, room, advance — creates reservation, opens folio, marks room occupied.</p>

    <form wire:submit.prevent="checkIn" class="space-y-6">
        {{-- Guest --}}
        <div class="bg-white rounded-xl border p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Guest details</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Full name *</label>
                    <input type="text" wire:model="guest_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @error('guest_name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Phone *</label>
                    <input type="tel" wire:model="guest_phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" wire:model="guest_email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">ID type *</label>
                    <select wire:model="id_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="aadhaar">Aadhaar</option>
                        <option value="passport">Passport</option>
                        <option value="voter">Voter ID</option>
                        <option value="driving_license">Driving licence</option>
                        <option value="pan">PAN</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">ID number *</label>
                    <input type="text" wire:model="id_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Address</label>
                    <input type="text" wire:model="address" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>

                {{-- ID proof file upload (mandatory under Indian hospitality law) --}}
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-700 mb-1">ID proof scans (front, back, selfie) — required</label>
                    <input type="file" multiple accept=".jpg,.jpeg,.png,.pdf" wire:model="idProofUploads" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <p class="text-[11px] text-slate-500 mt-1">Up to 5MB each. Stored securely under storage/app/public/id-proofs/.</p>
                    @error('idProofUploads.*')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>

                {{-- Foreign national toggle + FRRO fields --}}
                <div class="md:col-span-3 border-t pt-4 mt-2">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" wire:model.live="is_foreign_national" class="rounded">
                        Foreign national (FRRO Form C will be required)
                    </label>
                </div>

                @if($is_foreign_national)
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Nationality (ISO 2)</label>
                        <input type="text" wire:model="nationality" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase" placeholder="e.g. US">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Passport number *</label>
                        <input type="text" wire:model="passport_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('passport_number')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Passport expiry</label>
                        <input type="date" wire:model="passport_expiry" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Visa number *</label>
                        <input type="text" wire:model="visa_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('visa_number')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Visa expiry</label>
                        <input type="date" wire:model="visa_expiry" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Arrived in India on</label>
                        <input type="date" wire:model="arrival_date_in_india" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Arrived from (ISO 2 country)</label>
                        <input type="text" wire:model="arrival_from_country" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Next destination (country)</label>
                        <input type="text" wire:model="next_destination_country" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Next destination address</label>
                        <input type="text" wire:model="next_destination" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                @endif
            </div>
        </div>

        {{-- Stay --}}
        <div class="bg-white rounded-xl border p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Stay & room</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Arrival *</label>
                    <input type="date" wire:model.live="arrival_date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Departure *</label>
                    <input type="date" wire:model.live="departure_date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Adults *</label>
                        <input type="number" min="1" wire:model="adults" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Children</label>
                        <input type="number" min="0" wire:model="children" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Room type *</label>
                    <select wire:model.live="room_type_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Select…</option>
                        @foreach($roomTypes as $rt)
                            <option value="{{ $rt->id }}">{{ $rt->name }} — ₹{{ number_format($rt->base_rate, 0) }}/night</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Rate plan</label>
                    <select wire:model.live="rate_plan_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" @if(!$room_type_id) disabled @endif>
                        <option value="">Default (BAR)</option>
                        @foreach($ratePlans as $rp)
                            <option value="{{ $rp->id }}">{{ $rp->name }}</option>
                        @endforeach
                    </select>
                    @if(($pricing['displayed_rate'] ?? 0) > 0)
                        <div class="text-[11px] text-slate-500 mt-1">
                            ₹{{ number_format($pricing['displayed_rate'], 2) }}/night
                            @if(($pricing['tax_mode'] ?? 'exclusive') === 'inclusive')
                                (tax incl.)
                            @else
                                + {{ number_format($pricing['tax_pct'], 0) }}% tax
                            @endif
                        </div>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Room *</label>
                    @if($rooms->count())
                        <div class="grid grid-cols-6 gap-2">
                            @foreach($rooms as $room)
                                <button type="button" wire:click="$set('room_id', {{ $room->id }})"
                                    class="rounded-lg border-2 px-2 py-2 text-center transition {{ $room_id === $room->id ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 hover:border-brand-300' }}">
                                    <div class="font-bold text-sm">{{ $room->number }}</div>
                                    <div class="text-[10px] text-slate-500">F{{ $room->floor }}</div>
                                </button>
                            @endforeach
                        </div>
                    @elseif($room_type_id)
                        <div class="text-xs text-rose-600 px-3 py-2">No vacant rooms of this type. Try another type or housekeeping.</div>
                    @else
                        <div class="text-xs text-slate-400 px-3 py-2">Pick a room type first.</div>
                    @endif
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Special requests</label>
                    <textarea wire:model="special_requests" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></textarea>
                </div>
            </div>
        </div>

        {{-- Coupon --}}
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
            <h2 class="text-sm font-semibold text-slate-900 mb-2">Have a coupon?</h2>
            @if(!$appliedPromotion)
                <div class="flex gap-2">
                    <input type="text" wire:model="couponCode" placeholder="Enter code"
                        class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase">
                    <button type="button" wire:click="applyCoupon"
                        class="bg-slate-800 hover:bg-slate-900 text-white text-sm px-4 py-2 rounded-lg">Apply</button>
                </div>
            @else
                <div class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                    <div class="text-sm">
                        <span class="font-semibold text-emerald-700">{{ $appliedPromotion->code }}</span>
                        <span class="text-slate-600">- {{ $appliedPromotion->name }}</span>
                    </div>
                    <button type="button" wire:click="removeCoupon" class="text-xs text-rose-600 hover:underline">Remove</button>
                </div>
            @endif
            @if($couponMessage)
                <div class="text-xs mt-2 {{ $appliedPromotion ? 'text-emerald-700' : 'text-rose-600' }}">{{ $couponMessage }}</div>
            @endif
        </div>

        {{-- Totals --}}
        @if(($pricing['subtotal'] ?? 0) > 0)
            <div class="bg-white border border-slate-200 rounded-lg p-4 text-sm">
                <div class="flex justify-between py-1">
                    <span class="text-slate-600">Room ({{ $pricing['nights'] }} night{{ $pricing['nights'] === 1 ? '' : 's' }})</span>
                    <span>₹{{ number_format($pricing['subtotal'], 2) }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-600">Tax ({{ number_format($pricing['tax_pct'], 0) }}%)</span>
                    <span>₹{{ number_format($pricing['tax_total'], 2) }}</span>
                </div>
                @if(($pricing['discount'] ?? 0) > 0 && $appliedPromotion)
                    <div class="flex justify-between py-1 text-emerald-700">
                        <span>Discount ({{ $appliedPromotion->code }})</span>
                        <span>- ₹{{ number_format($pricing['discount'], 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between pt-2 mt-1 border-t border-slate-200 font-bold text-slate-900">
                    <span>Total</span>
                    <span>₹{{ number_format($pricing['total'], 2) }}</span>
                </div>
            </div>
        @endif

        {{-- Advance --}}
        <div class="bg-white rounded-xl border p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Advance payment</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Mode</label>
                    <select wire:model="payMode" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="upi">UPI</option>
                        <option value="bank_transfer">Bank transfer</option>
                        <option value="company_credit">City ledger / company</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Amount (₹)</label>
                    <input type="number" step="0.01" wire:model="advance" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-lg">
                <span wire:loading.remove wire:target="checkIn">Check in & open folio</span>
                <span wire:loading wire:target="checkIn">Processing…</span>
            </button>
        </div>
    </form>
</div>
