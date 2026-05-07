<div>
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-12 h-12 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-2xl flex-shrink-0">!</div>
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-slate-900">License unavailable</h1>
                <p class="text-sm text-slate-600 mt-1">
                    @if($tenantName){{ $tenantName }}'s @endif subscription is not currently active. Access to Hotelesy is paused until the license is renewed.
                </p>
            </div>
        </div>

        @if(session('warning'))
            <div class="mb-5 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">{{ session('warning') }}</div>
        @endif

        @if($license)
            <dl class="grid grid-cols-2 gap-4 text-sm border-t border-slate-200 pt-5">
                <div>
                    <dt class="text-xs uppercase tracking-wider text-slate-500">Status</dt>
                    <dd class="mt-1"><span class="text-[11px] uppercase tracking-wider px-2 py-1 rounded {{ $license->statusBadgeClass() }}">{{ $license->status }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-slate-500">Plan</dt>
                    <dd class="mt-1 font-semibold">{{ $license->plan?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-slate-500">Expired on</dt>
                    <dd class="mt-1 font-semibold">{{ $license->expires_at?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-slate-500">Days since expiry</dt>
                    <dd class="mt-1 font-semibold text-rose-700">
                        {{ $license->expires_at ? (int) round(now()->diffInDays($license->expires_at, false)) * -1 : '—' }} days
                    </dd>
                </div>
            </dl>
            @if($license->suspended_reason)
                <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    <strong>Reason:</strong> {{ $license->suspended_reason }}
                </div>
            @endif
        @else
            <div class="text-sm text-slate-600 border-t border-slate-200 pt-5">No license is associated with your tenant. Please contact support.</div>
        @endif

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="mailto:support@hotelesy.app?subject=License%20renewal%20-%20{{ urlencode($tenantName ?? 'Tenant') }}"
               class="text-center bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-600 hover:to-brand-800 text-white font-semibold py-2.5 rounded-lg transition shadow-md">
                Renew now
            </a>
            <a href="mailto:support@hotelesy.app"
               class="text-center bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold py-2.5 rounded-lg transition">
                Contact your administrator
            </a>
        </div>

        <form method="POST" action="{{ url('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-xs text-slate-500 hover:text-rose-600">Sign out</button>
        </form>
    </div>
</div>
