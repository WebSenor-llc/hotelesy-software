<div>
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">Start your 14-day free trial</h1>
        <p class="text-sm text-slate-600 mb-6">No credit card required. Full access to Hotelesy for 14 days.</p>

        @if($errors->any())
            <div class="mb-5 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form wire:submit.prevent="register" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Hotel / Company name *</label>
                    <input type="text" wire:model.live.debounce.500ms="company_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Your name *</label>
                    <input type="text" wire:model="owner_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email *</label>
                    <input type="email" wire:model="email" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Phone</label>
                    <input type="tel" wire:model="phone" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Password *</label>
                    <input type="password" wire:model="password" required minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Confirm password *</label>
                    <input type="password" wire:model="password_confirmation" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Country (ISO-2) *</label>
                <input type="text" wire:model="country" maxlength="2" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm uppercase focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="register" class="w-full bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-600 hover:to-brand-800 text-white font-semibold py-2.5 rounded-lg transition shadow-md hover:shadow-lg disabled:opacity-60">
                <span wire:loading.remove wire:target="register">Start free trial →</span>
                <span wire:loading wire:target="register">Creating your workspace…</span>
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-200 text-center text-sm text-slate-600">
            Already have an account? <a href="{{ route('login') }}" class="text-brand-700 font-semibold hover:underline">Sign in</a>
        </div>
    </div>
</div>
