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

        {{-- ============================================================
             TEST MODE — Razorpay isn't configured (or you're on a dev
             environment). These buttons let you simulate a successful or
             failed payment so you can exercise the full flow without
             real money or UPI app.
             ============================================================ --}}
        @if(app(\App\Services\RazorpayService::class)->isStubMode())
            <div class="mt-8 pt-6 border-t border-dashed border-amber-300">
                <div class="inline-flex items-center gap-2 text-[10px] uppercase tracking-widest font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 mb-3">
                    <span>⚠</span> Test mode · Razorpay keys not configured
                </div>
                <div class="text-xs text-ink-600 mb-4">Simulate the payment outcome without a real UPI app.</div>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button type="button" wire:click="testPaymentSuccess"
                            class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3 rounded-full transition"
                            wire:loading.attr="disabled" wire:target="testPaymentSuccess">
                        <span wire:loading.remove wire:target="testPaymentSuccess">✓ Simulate success → dashboard</span>
                        <span wire:loading wire:target="testPaymentSuccess">Activating…</span>
                    </button>
                    <button type="button" wire:click="testPaymentFailure"
                            class="inline-flex items-center justify-center gap-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold px-6 py-3 rounded-full transition"
                            wire:loading.attr="disabled" wire:target="testPaymentFailure">
                        <span wire:loading.remove wire:target="testPaymentFailure">✗ Simulate failure</span>
                        <span wire:loading wire:target="testPaymentFailure">Marking failed…</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if(session('warning'))<div class="mt-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-4 text-sm">{{ session('warning') }}</div>@endif

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById('open-razorpay')?.addEventListener('click', function () {
            // In stub mode, the "Pay ₹1" button simulates a real Razorpay flow
            // by piggy-backing on the test-success handler — no popup opens.
            if (@json(app(\App\Services\RazorpayService::class)->isStubMode())) {
                @this.testPaymentSuccess();
                return;
            }
            const options = {
                key: @json(app(\App\Services\RazorpayService::class)->publicKey()),
                amount: @json($razorpayOrder['amount']),
                currency: @json($razorpayOrder['currency'] ?? 'INR'),
                order_id: @json($razorpayOrder['id']),
                name: 'Hotelesy by WebSenor',
                description: '30-day trial · ₹1 mandate authorization',
                method: { upi: true, card: true, netbanking: false, wallet: false },
                handler: function (response) {
                    @this.confirmPayment(response.razorpay_payment_id, response.razorpay_order_id, response.razorpay_signature);
                },
                modal: {
                    ondismiss: function () {
                        @this.testPaymentFailure();   // user closed Razorpay → mark failed
                    }
                },
                theme: { color: '#22201d' }
            };
            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                @this.testPaymentFailure();
            });
            rzp.open();
        });
    </script>
    @endif

    {{-- =====================================================
         STEP 3: SUCCESS — proper "thank you" page with details
         The user is auto-logged-in here. They click "Continue to
         setup" to land on /dashboard already authenticated.
         ===================================================== --}}
    @if($step === 'success')
    @php
        $isDevHost = ! str_contains((string) request()->getHost(), '.');
        $port = request()->getPort();
        $portSuffix = (in_array($port, [80, 443]) || empty($port)) ? '' : ':' . $port;
        $publicWebsiteUrl = $isDevHost
            ? url('/h/' . $successTenantSlug)
            : request()->getScheme() . '://' . $successTenantSlug . '.' . request()->getHost() . $portSuffix;
        $firstName = $successOwnerName ? explode(' ', $successOwnerName)[0] : 'there';
        $monthlyPrice = number_format(config('razorpay.default_monthly_paise', 99900) / 100, 0);
    @endphp

    <div class="space-y-6">
        {{-- Big celebration banner --}}
        <div class="bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700 text-white rounded-3xl p-10 text-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 30% 30%, white 0, transparent 40%), radial-gradient(circle at 70% 70%, white 0, transparent 40%);"></div>
            <div class="relative">
                <div class="w-24 h-24 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-6xl mx-auto mb-4">🎉</div>
                <h1 class="font-display font-black text-5xl leading-tight">Welcome aboard, {{ $firstName }}!</h1>
                <p class="text-white/90 text-lg mt-3">Your 30-day Hotelesy trial is live. Let's get <strong>{{ $successHotelName }}</strong> set up.</p>
            </div>
        </div>

        {{-- Subscription summary --}}
        <div class="bg-white rounded-2xl border-2 border-emerald-100 p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="font-bold text-ink-900 text-lg">Your subscription</h3>
                <span class="text-[10px] uppercase tracking-widest font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Active · Trial</span>
            </div>
            <div class="grid sm:grid-cols-2 gap-4 text-sm">
                <div class="flex justify-between border-b border-slate-100 py-1.5"><span class="text-slate-500">Plan</span><span class="font-semibold">Free 30-day trial</span></div>
                <div class="flex justify-between border-b border-slate-100 py-1.5"><span class="text-slate-500">Charged today</span><span class="font-semibold font-mono">₹1.00 (UPI mandate)</span></div>
                <div class="flex justify-between border-b border-slate-100 py-1.5"><span class="text-slate-500">Trial ends on</span><span class="font-semibold">{{ $successTrialEnds }}</span></div>
                <div class="flex justify-between border-b border-slate-100 py-1.5"><span class="text-slate-500">First auto-charge</span><span class="font-semibold">₹{{ $monthlyPrice }}/mo on {{ $successTrialEnds }}</span></div>
            </div>
            <p class="text-xs text-slate-500 mt-4">Cancel anytime from your dashboard before {{ $successTrialEnds }} to avoid the renewal charge.</p>
        </div>

        {{-- Account details --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h3 class="font-bold text-ink-900 text-lg mb-4">Your account</h3>
            <div class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div><span class="text-xs uppercase tracking-widest text-slate-500 font-semibold">Hotel name</span><div class="font-semibold mt-0.5">{{ $successHotelName }}@if($successCity), {{ $successCity }}@endif</div></div>
                <div><span class="text-xs uppercase tracking-widest text-slate-500 font-semibold">Owner</span><div class="font-semibold mt-0.5">{{ $successOwnerName }}</div></div>
                <div><span class="text-xs uppercase tracking-widest text-slate-500 font-semibold">Email</span><div class="font-semibold mt-0.5 font-mono text-[13px]">{{ $successOwnerEmail }}</div></div>
                <div><span class="text-xs uppercase tracking-widest text-slate-500 font-semibold">Tenant ID</span><div class="font-semibold mt-0.5 font-mono text-[13px]">{{ $successTenantSlug }}</div></div>
            </div>

            {{-- Public website URL --}}
            <div class="mt-5 pt-5 border-t border-slate-100">
                <span class="text-xs uppercase tracking-widest text-slate-500 font-semibold block mb-1">Your public hotel website</span>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ $publicWebsiteUrl }}" target="_blank" class="font-mono text-sm text-brand-700 hover:underline break-all">{{ $publicWebsiteUrl }}</a>
                    <a href="{{ $publicWebsiteUrl }}" target="_blank" class="text-[10px] uppercase tracking-widest font-semibold px-2 py-0.5 rounded bg-brand-100 text-brand-700 hover:bg-brand-200">Visit ↗</a>
                </div>
                <p class="text-[11px] text-slate-500 mt-1">This is the booking website your guests will visit. We've already published a starter design — you can customise it from Setup.</p>
            </div>

            {{-- License key one-time display --}}
            @if($successLicenseKey)
                <div class="mt-5 pt-5 border-t border-slate-100">
                    <span class="text-xs uppercase tracking-widest text-amber-700 font-semibold block mb-1">⚠ License key — save this somewhere safe</span>
                    <div class="font-mono text-base bg-amber-50 border border-amber-200 px-3 py-2 rounded select-all">{{ $successLicenseKey }}</div>
                    <p class="text-[11px] text-slate-500 mt-1">Shown once. Keep this for support and audit purposes — it's stored securely on your account.</p>
                </div>
            @endif
        </div>

        {{-- Next steps checklist --}}
        <div class="bg-gradient-to-br from-amber-50 via-orange-50 to-rose-50 rounded-2xl border border-amber-200 p-6">
            <h3 class="font-bold text-ink-900 text-lg mb-1">What to do next</h3>
            <p class="text-sm text-slate-600 mb-4">A typical hotel goes live in under 2 hours. Here's the fastest path:</p>
            <ol class="space-y-3">
                @php
                    $steps = [
                        ['Set up your property',     'Property name, GSTIN, check-in/out times, contact details', 'setup.property'],
                        ['Add room types',           'Deluxe, Suite, etc. with photos, capacity & base rate',     'setup.room-types'],
                        ['Add individual rooms',     'Room numbers, floors, smoking/accessible flags',            'setup.rooms'],
                        ['Configure rate plans',     'BAR, package rates, GST inclusive/exclusive',               'setup.rate-plans'],
                        ['Connect channels',         'Booking.com, MakeMyTrip, Agoda, Goibibo',                   'channel.index'],
                        ['Take your first booking',  'Walk-in, online or via phone',                              'reservations.new'],
                    ];
                @endphp
                @foreach($steps as $i => [$title, $desc, $route])
                    <li class="flex items-start gap-3">
                        <span class="flex-shrink-0 w-7 h-7 rounded-full bg-ink-900 text-white text-xs font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                        <div class="flex-1">
                            <div class="font-semibold text-sm text-ink-900">{{ $title }}</div>
                            <div class="text-xs text-slate-600">{{ $desc }}</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- Big CTA --}}
        <div class="text-center pt-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 bg-ink-900 hover:bg-ink-800 text-ink-50 font-bold px-10 py-5 rounded-full transition shadow-lg">
                Continue to setup → my dashboard
            </a>
            <p class="text-xs text-slate-500 mt-3">You're already signed in. We'll take you straight to your dashboard.</p>
            <a href="{{ route('home') }}" class="text-xs text-slate-500 hover:text-slate-800 mt-3 inline-block">← Back to Hotelesy home</a>
        </div>
    </div>
    @endif
</div>
