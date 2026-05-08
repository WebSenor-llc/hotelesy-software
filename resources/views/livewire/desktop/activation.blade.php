<div class="min-h-screen bg-gradient-to-br from-slate-50 via-brand-50 to-slate-100 flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-xl shadow-lg">H</div>
                <div class="text-left">
                    <div class="font-bold text-slate-900 text-lg leading-tight">Hotelesy <span class="text-xs font-normal text-slate-500">Desktop</span></div>
                    <div class="text-xs text-slate-500">v{{ $version }} · {{ $os }}</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-7">
            <h1 class="text-xl font-bold text-slate-900">Activate your license</h1>
            <p class="text-sm text-slate-600 mt-1 mb-5">Enter the activation key from your purchase email — looks like <span class="font-mono">HTLY-XXXX-XXXX-XXXX-XXXX</span>. Hotelesy will bind the license to this machine.</p>

            @if(session('reason'))<div class="mb-4 px-3 py-2 rounded bg-rose-50 border border-rose-200 text-xs text-rose-800">{{ session('reason') }}</div>@endif
            @if(session('warning'))<div class="mb-4 px-3 py-2 rounded bg-amber-50 border border-amber-200 text-xs text-amber-800">{{ session('warning') }}</div>@endif

            @if($error)<div class="mb-4 px-3 py-2 rounded bg-rose-50 border border-rose-200 text-xs text-rose-800">{{ $error }}</div>@endif
            @if($success)<div class="mb-4 px-3 py-2 rounded bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">{{ $success }}</div>@endif

            <form wire:submit="activate" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5 uppercase tracking-wider">Activation key</label>
                    <input type="text" wire:model="activationKey"
                           placeholder="HTLY-XXXX-XXXX-XXXX-XXXX"
                           autofocus required
                           class="w-full px-4 py-3 border-2 border-slate-300 rounded-lg text-sm font-mono uppercase tracking-wider focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                    @error('activationKey')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="w-full bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-600 hover:to-brand-800 text-white font-semibold py-3 rounded-lg transition shadow-md disabled:opacity-50"
                        wire:loading.attr="disabled" wire:target="activate">
                    <span wire:loading.remove wire:target="activate">Activate Hotelesy</span>
                    <span wire:loading wire:target="activate">Contacting license server…</span>
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-200 space-y-2">
                <div class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">This machine</div>
                <div class="text-xs text-slate-700"><strong>Fingerprint:</strong> <span class="font-mono">{{ $fingerprint }}</span></div>
                <div class="text-xs text-slate-700"><strong>Host:</strong> {{ $host }}</div>
                <div class="text-xs text-slate-700"><strong>OS:</strong> {{ $os }}</div>
            </div>

            <div class="mt-5 pt-5 border-t border-slate-200 text-xs text-slate-500">
                <strong class="text-slate-700">Need help?</strong>
                Email <a href="mailto:support@hotelesy.app" class="text-brand-600 hover:underline">support@hotelesy.app</a> with your fingerprint above.
                Lost your key? Visit <a href="https://hotelesy.com/account" class="text-brand-600 hover:underline">hotelesy.com/account</a>.
            </div>
        </div>
    </div>
</div>
