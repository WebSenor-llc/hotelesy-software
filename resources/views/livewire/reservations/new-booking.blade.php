<div class="max-w-4xl">
    <h1 class="text-2xl font-bold text-slate-900 mb-1">New booking</h1>
    <p class="text-sm text-slate-600 mb-6">Create a confirmed reservation for the front desk.</p>

    <form wire:submit.prevent="save" class="bg-white rounded-xl border border-slate-200 p-6 space-y-6">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 mb-3 uppercase tracking-wider">Guest details</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Guest name *</label>
                    <input type="text" wire:model="guest_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                    @error('guest_name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Phone</label>
                    <input type="tel" wire:model="guest_phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @error('guest_phone')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" wire:model="guest_email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @error('guest_email')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-slate-900 mb-3 uppercase tracking-wider">Stay</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Arrival *</label>
                    <input type="date" wire:model.live="arrival_date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @error('arrival_date')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Departure *</label>
                    <input type="date" wire:model.live="departure_date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @error('departure_date')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Adults *</label>
                    <input type="number" min="1" max="10" wire:model="adults" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Children</label>
                    <input type="number" min="0" max="10" wire:model="children" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Rooms *</label>
                    <input type="number" min="1" max="10" wire:model.live="rooms_count" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-slate-900 mb-3 uppercase tracking-wider">Room & rate</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Room type *</label>
                    <select wire:model.live="room_type_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Select…</option>
                        @foreach($roomTypes as $rt)
                            <option value="{{ $rt->id }}">{{ $rt->name }} — ₹{{ number_format($rt->base_rate, 0) }}/night</option>
                        @endforeach
                    </select>
                    @error('room_type_id')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Rate plan</label>
                    <select wire:model.live="rate_plan_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Default (BAR)</option>
                        @foreach($ratePlans as $rp)
                            <option value="{{ $rp->id }}">{{ $rp->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if(($pricing['displayed_rate'] ?? 0) > 0)
                <div class="mt-3 text-xs text-slate-600">
                    Rate: <span class="font-semibold text-slate-900">₹{{ number_format($pricing['displayed_rate'], 2) }}/night</span>
                    @if(($pricing['tax_mode'] ?? 'exclusive') === 'inclusive')
                        <span class="text-slate-500">(tax inclusive)</span>
                    @else
                        <span class="text-slate-500">+ {{ number_format($pricing['tax_pct'], 0) }}% tax</span>
                    @endif
                </div>
            @endif
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
                    <span class="text-slate-600">Room ({{ $pricing['rooms_count'] }} × {{ $pricing['nights'] }} nights)</span>
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

        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Special requests</label>
            <textarea wire:model="special_requests" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Late check-in, high floor, allergies, etc."></textarea>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-slate-200">
            <a href="{{ route('reservations.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← Cancel</a>
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold">
                <span wire:loading.remove wire:target="save">Create reservation</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
