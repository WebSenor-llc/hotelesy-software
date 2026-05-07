<div class="max-w-5xl mx-auto px-6 py-16">
    @if(! $plan)
    <div class="text-center text-ink-500 py-20">No plan selected. <a href="{{ route('home') }}#pricing" class="text-ink-900 underline">View pricing →</a></div>
    @else
    <div class="grid lg:grid-cols-5 gap-8">
        {{-- Form --}}
        <div class="lg:col-span-3">
            @if($step === 'form')
            <h1 class="font-display font-black text-4xl text-ink-900 leading-tight">Just a few details before checkout.</h1>
            <p class="text-ink-600 mt-3 mb-8">Your account is created the moment payment succeeds.</p>
            <form wire:submit="startCheckout" class="bg-white rounded-2xl border border-ink-100 p-7 shadow-sm space-y-4">
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Full name *</label>
                        <input wire:model="name" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                        @error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div><label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Email *</label>
                        <input wire:model="email" type="email" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                        @error('email')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Phone *</label>
                        <input wire:model="phone" type="tel" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                        @error('phone')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div><label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Password *</label>
                        <input wire:model="password" type="password" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                        @error('password')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="md:col-span-2"><label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Hotel name *</label>
                        <input wire:model="hotel_name" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                        @error('hotel_name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div><label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Rooms *</label>
                        <input wire:model="rooms_count" type="number" min="1" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                        @error('rooms_count')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div><label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">City *</label>
                    <input wire:model="city" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                    @error('city')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <label class="flex items-start gap-2 text-sm text-ink-600">
                    <input type="checkbox" wire:model="agree" class="mt-0.5">
                    <span>I agree to the Terms of Service and Privacy Policy.</span>
                </label>
                @error('agree')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                <button type="submit" class="w-full bg-ink-900 hover:bg-ink-800 text-ink-50 font-bold py-4 rounded-full transition" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="startCheckout">Continue to payment →</span>
                    <span wire:loading wire:target="startCheckout">Setting up…</span>
                </button>
            </form>
            @endif

            @if($step === 'pay')
            <div class="bg-white rounded-2xl border border-ink-100 p-10 shadow-sm text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-3xl mx-auto">₹</div>
                <h2 class="font-display font-black text-3xl text-ink-900 mt-5">Pay ₹{{ number_format($amount, 0) }} to activate</h2>
                <p class="text-ink-600 mt-3">Your subscription will activate immediately on successful payment.</p>
                <button id="open-rzp" class="mt-6 bg-ink-900 hover:bg-ink-800 text-ink-50 font-bold py-4 px-8 rounded-full transition">
                    Pay ₹{{ number_format($amount, 0) }} →
                </button>
            </div>
            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
                document.getElementById('open-rzp')?.addEventListener('click', function () {
                    const options = {
                        key: @json(app(\App\Services\RazorpayService::class)->publicKey()),
                        amount: @json($razorpayOrder['amount']),
                        currency: @json($razorpayOrder['currency'] ?? 'INR'),
                        order_id: @json($razorpayOrder['id']),
                        name: 'Hotelesy by WebSenor',
                        description: @json("Hotelesy ".$plan->name." · ".$cycle),
                        handler: function (response) {
                            @this.confirmPayment(response.razorpay_payment_id, response.razorpay_order_id, response.razorpay_signature);
                        },
                        theme: { color: '#22201d' }
                    };
                    if (options.key === 'rzp_test_stub' || @json(app(\App\Services\RazorpayService::class)->isStubMode())) {
                        @this.confirmPayment('pay_stub_' + Math.random().toString(36).slice(2,14), options.order_id, 'sig_stub');
                        return;
                    }
                    new Razorpay(options).open();
                });
            </script>
            @endif

            @if($step === 'success')
            <div class="bg-emerald-50 border-2 border-emerald-200 rounded-2xl p-10 text-center">
                <div class="w-20 h-20 rounded-full bg-emerald-500 text-white flex items-center justify-center text-4xl mx-auto">✓</div>
                <h2 class="font-display font-black text-4xl text-ink-900 mt-6">You're in!</h2>
                <p class="text-ink-700 mt-3">Your {{ $plan->name }} subscription is now active.</p>
                <a href="{{ route('login') }}" class="inline-flex mt-6 bg-ink-900 hover:bg-ink-800 text-ink-50 font-bold px-7 py-3 rounded-full transition">Sign in to dashboard →</a>
            </div>
            @endif
        </div>

        {{-- Order summary sidebar --}}
        <aside class="lg:col-span-2">
            <div class="bg-ink-900 text-ink-50 rounded-2xl p-6 sticky top-24">
                <div class="text-xs uppercase tracking-widest text-gold-300 mb-2">Order summary</div>
                <div class="font-display font-black text-2xl">{{ $plan->name }}</div>
                <div class="text-xs text-ink-300 mt-1">{{ ucfirst($cycle) }} billing · {{ $cycle === 'yearly' ? '12 months' : '30 days' }}</div>

                <div class="mt-5 space-y-2 text-sm text-ink-300">
                    <div class="flex justify-between"><span>Subtotal</span><span class="font-mono">₹{{ number_format($amount, 0) }}</span></div>
                    <div class="flex justify-between"><span>GST (18%)</span><span class="font-mono">₹{{ number_format($amount * 0.18, 0) }}</span></div>
                </div>
                <div class="mt-4 pt-4 border-t border-white/10 flex justify-between font-bold text-lg">
                    <span>Total today</span>
                    <span class="text-gold-300">₹{{ number_format($amount * 1.18, 0) }}</span>
                </div>
                <ul class="mt-5 space-y-1.5 text-xs text-ink-300">
                    <li class="flex items-start gap-1.5"><span class="text-gold-300">✓</span> {{ $plan->max_rooms ?? 100 }} rooms · {{ $plan->max_users ?? 10 }} users</li>
                    <li class="flex items-start gap-1.5"><span class="text-gold-300">✓</span> Unlimited bookings · no commission</li>
                    <li class="flex items-start gap-1.5"><span class="text-gold-300">✓</span> Free onboarding & support</li>
                    <li class="flex items-start gap-1.5"><span class="text-gold-300">✓</span> 7-day money-back guarantee</li>
                </ul>
            </div>
        </aside>
    </div>
    @endif
</div>
