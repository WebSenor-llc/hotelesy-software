<div class="max-w-3xl mx-auto px-6 py-16">
    {{-- Stepper --}}
    <div class="flex items-center justify-center gap-3 mb-10 text-xs uppercase tracking-widest font-semibold">
        @foreach([['form','1','Your details'],['pay','2','Authorize ₹1'],['success','3','You\'re in!']] as $i => $s)
            @php $active = $step === $s[0]; $done = (['form'=>0,'pay'=>1,'success'=>2][$step] ?? 0) > $i; @endphp
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] {{ $active ? 'bg-ink-900 text-gold-300' : ($done ? 'bg-emerald-500 text-white' : 'bg-ink-100 text-ink-500') }}">
                    {{ $done ? '✓' : $s[1] }}
                </div>
                <span class="{{ $active ? 'text-ink-900' : ($done ? 'text-emerald-600' : 'text-ink-400') }}">{{ $s[2] }}</span>
            </div>
            @if($i < 2)<span class="text-ink-300">—</span>@endif
        @endforeach
    </div>

    {{-- STEP 1: Form --}}
    @if($step === 'form')
    <div class="text-center mb-8">
        <span class="text-xs uppercase tracking-[0.3em] text-gold-600 font-semibold">Free trial · 30 days</span>
        <h1 class="font-display font-black text-4xl md:text-5xl text-ink-900 mt-3 leading-tight">A few details and you're <span class="italic text-gold-600">running.</span></h1>
        <p class="text-ink-600 mt-3">No credit card. UPI mandate of ₹1. Cancel before day 30 — pay nothing more.</p>
    </div>
    <form wire:submit="startTrial" class="bg-white rounded-2xl border border-ink-100 p-8 shadow-sm space-y-5">
        <div class="grid md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">Full name *</label>
                <input wire:model="name" type="text" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                @error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">Email *</label>
                <input wire:model="email" type="email" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                @error('email')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="grid md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">Phone *</label>
                <input wire:model="phone" type="tel" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                @error('phone')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">Password *</label>
                <input wire:model="password" type="password" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                @error('password')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="grid md:grid-cols-3 gap-5">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">Hotel name *</label>
                <input wire:model="hotel_name" type="text" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                @error('hotel_name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">Rooms *</label>
                <input wire:model="rooms_count" type="number" min="1" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
                @error('rooms_count')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">City *</label>
            <input wire:model="city" type="text" class="w-full px-4 py-3 border border-ink-200 rounded-xl focus:border-ink-900 focus:outline-none">
            @error('city')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
        </div>
        <label class="flex items-start gap-2.5 text-sm text-ink-600">
            <input type="checkbox" wire:model="agree" class="mt-0.5">
            <span>I agree to the Terms of Service & Privacy Policy. I understand I'll be auto-charged ₹{{ number_format(config('razorpay.default_monthly_paise')/100, 0) }}/month after the 30-day trial unless cancelled.</span>
        </label>
        @error('agree')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror

        <button type="submit" class="w-full bg-ink-900 hover:bg-ink-800 text-ink-50 font-bold py-4 rounded-full transition" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="startTrial">Continue to ₹1 verification →</span>
            <span wire:loading wire:target="startTrial">Setting up your account…</span>
        </button>
    </form>
    @endif

    {{-- STEP 2: Razorpay payment --}}
    @if($step === 'pay')
    <div class="bg-white rounded-2xl border border-ink-100 p-10 shadow-sm text-center">
        <div class="w-16 h-16 rounded-full bg-gold-100 text-gold-700 flex items-center justify-center text-3xl mx-auto">₹</div>
        <h2 class="font-display font-black text-3xl text-ink-900 mt-5">Authorize ₹1 to start your trial</h2>
        <p class="text-ink-600 mt-3 max-w-md mx-auto">We'll open Razorpay's secure UPI checkout. After you approve the mandate, your 30-day trial begins immediately.</p>
        <div class="mt-6 inline-block bg-ink-50 border border-ink-200 rounded-xl px-6 py-3 text-left">
            <div class="text-xs uppercase tracking-widest text-ink-500">Charge today</div>
            <div class="font-mono font-bold text-2xl text-ink-900">₹1.00</div>
        </div>
        <button id="open-razorpay" class="block mx-auto mt-6 bg-ink-900 hover:bg-ink-800 text-ink-50 font-bold py-4 px-8 rounded-full transition">
            Pay ₹1 via UPI →
        </button>
        <p class="text-xs text-ink-500 mt-4">Secured by Razorpay · Your bank's UPI app will open</p>
    </div>

    @if(session('warning'))<div class="mt-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-4 text-sm">{{ session('warning') }}</div>@endif

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById('open-razorpay')?.addEventListener('click', function () {
            const options = {
                key: @json(app(\App\Services\RazorpayService::class)->publicKey()),
                amount: @json($razorpayOrder['amount']),
                currency: @json($razorpayOrder['currency'] ?? 'INR'),
                order_id: @json($razorpayOrder['id']),
                name: 'Hotelesy by WebSenor',
                description: '30-day trial · ₹1 mandate authorization',
                method: { upi: true, card: true, netbanking: false, wallet: false },
                handler: function (response) {
                    Livewire.dispatch('confirmPayment', {
                        razorpayPaymentId: response.razorpay_payment_id,
                        razorpayOrderId: response.razorpay_order_id,
                        razorpaySignature: response.razorpay_signature
                    });
                    @this.confirmPayment(response.razorpay_payment_id, response.razorpay_order_id, response.razorpay_signature);
                },
                theme: { color: '#22201d' }
            };
            // Stub-mode shortcut: when no real key, simulate success.
            if (options.key === 'rzp_test_stub' || @json(app(\App\Services\RazorpayService::class)->isStubMode())) {
                @this.confirmPayment('pay_stub_' + Math.random().toString(36).slice(2,14), options.order_id, 'sig_stub');
                return;
            }
            const rzp = new Razorpay(options);
            rzp.open();
        });
    </script>
    @endif

    {{-- STEP 3: success --}}
    @if($step === 'success')
    <div class="bg-emerald-50 border-2 border-emerald-200 rounded-2xl p-10 text-center">
        <div class="w-20 h-20 rounded-full bg-emerald-500 text-white flex items-center justify-center text-4xl mx-auto">✓</div>
        <h2 class="font-display font-black text-4xl text-ink-900 mt-6">Welcome to Hotelesy!</h2>
        <p class="text-ink-700 mt-3 max-w-md mx-auto">Your 30-day trial is active. We've sent your login details to <strong>{{ $email }}</strong>.</p>
        <div class="mt-7 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('login') }}" class="inline-flex justify-center bg-ink-900 hover:bg-ink-800 text-ink-50 font-bold px-7 py-3 rounded-full transition">Sign in to dashboard →</a>
            <a href="{{ route('home') }}" class="inline-flex justify-center text-ink-700 hover:text-ink-900 font-semibold px-7 py-3">Back home</a>
        </div>
    </div>
    @endif
</div>
