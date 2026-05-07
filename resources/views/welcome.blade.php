@extends('layouts.app')

@php
$totalCount = 0; $readyCount = 0; $scaffCount = 0; $stubCount = 0; $plannedCount = 0;
foreach ($modules as $cat) {
    foreach ($cat['items'] as $m) {
        $totalCount++;
        if ($m['status']==='ready') $readyCount++;
        elseif ($m['status']==='scaffolded') $scaffCount++;
        elseif ($m['status']==='stub') $stubCount++;
        else $plannedCount++;
    }
}
@endphp

@section('content')
<section class="mb-12">
    <div class="rounded-2xl bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500 text-white p-10 shadow-lg">
        <div class="text-xs uppercase tracking-widest text-brand-100 mb-2">Phase 1–2 foundation</div>
        <h1 class="text-4xl font-bold mb-3">Hotelesy <span class="text-2xl font-normal text-brand-100">by WebSenor</span></h1>
        <p class="text-brand-50 max-w-2xl mb-6">
            Multi-tenant SaaS hotel management platform. Modular ERP for hotels and resorts —
            inspired by IDS Next FortuneNext / FX. Laravel 11 · PHP 8.3 · MySQL · Redis · Livewire 3.
        </p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-3xl">
            <div class="bg-white/10 rounded-lg p-4 backdrop-blur">
                <div class="text-3xl font-bold">{{ $totalCount }}</div>
                <div class="text-xs text-brand-100 uppercase tracking-wider">modules total</div>
            </div>
            <div class="bg-white/10 rounded-lg p-4 backdrop-blur">
                <div class="text-3xl font-bold">{{ $scaffCount }}</div>
                <div class="text-xs text-brand-100 uppercase tracking-wider">scaffolded</div>
            </div>
            <div class="bg-white/10 rounded-lg p-4 backdrop-blur">
                <div class="text-3xl font-bold">{{ $readyCount }}</div>
                <div class="text-xs text-brand-100 uppercase tracking-wider">ready</div>
            </div>
            <div class="bg-white/10 rounded-lg p-4 backdrop-blur">
                <div class="text-3xl font-bold">{{ $plannedCount + $stubCount }}</div>
                <div class="text-xs text-brand-100 uppercase tracking-wider">planned</div>
            </div>
        </div>
    </div>
</section>

<section class="mb-10">
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="text-sm font-semibold text-slate-900 mb-2">Architecture</div>
            <ul class="text-sm text-slate-600 space-y-1">
                <li>• Laravel 11 + Livewire 3</li>
                <li>• MySQL 8 (single DB, scoped tenancy)</li>
                <li>• Spatie multitenancy + permissions</li>
                <li>• Subdomain or header tenant resolution</li>
            </ul>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="text-sm font-semibold text-slate-900 mb-2">Channel manager</div>
            <ul class="text-sm text-slate-600 space-y-1">
                <li>• Driver-pattern abstraction</li>
                <li>• AxisRooms (default)</li>
                <li>• STAAH, SiteMinder, RateGain pluggable</li>
            </ul>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="text-sm font-semibold text-slate-900 mb-2">Integrations ready</div>
            <ul class="text-sm text-slate-600 space-y-1">
                <li>• Razorpay / Stripe / PayU</li>
                <li>• Onity, Saflok, dormakaba locks</li>
                <li>• Hyperverge / Jumio ID scanning</li>
            </ul>
        </div>
    </div>
</section>

<section>
    <div class="flex items-baseline justify-between mb-4">
        <h2 class="text-2xl font-bold text-slate-900">Module catalog</h2>
        <a href="{{ route('modules') }}" class="text-sm text-brand-600 hover:text-brand-700 font-medium">View all →</a>
    </div>

    <div class="space-y-8">
        @foreach($modules as $catKey => $cat)
            <div>
                <h3 class="text-xs uppercase tracking-widest font-semibold text-slate-500 mb-3">{{ $cat['label'] }}</h3>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($cat['items'] as $m)
                        @php
                            $badge = match($m['status']) {
                                'ready'      => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Ready'],
                                'scaffolded' => ['bg-sky-50 text-sky-700 border-sky-200', 'Scaffolded'],
                                'stub'       => ['bg-amber-50 text-amber-700 border-amber-200', 'Stub'],
                                default      => ['bg-slate-100 text-slate-600 border-slate-200', 'Planned'],
                            };
                        @endphp
                        <div class="bg-white rounded-xl border border-slate-200 p-4 hover:border-brand-300 hover:shadow-sm transition">
                            <div class="flex items-start justify-between mb-1.5">
                                <div class="font-semibold text-slate-900 text-sm">{{ $m['name'] }}</div>
                                <span class="text-[10px] uppercase tracking-wider font-medium px-2 py-0.5 rounded-full border {{ $badge[0] }}">{{ $badge[1] }}</span>
                            </div>
                            <div class="text-xs text-slate-500 mb-2">{{ $m['desc'] }}</div>
                            <code class="text-[10px] text-slate-400 font-mono">{{ $m['code'] }}</code>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>

<section class="mt-12">
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <div class="font-semibold text-amber-900 mb-1">Heads-up — what's actually wired</div>
        <p class="text-sm text-amber-800 mb-2">
            This boots the project with migrations, seeded modules/tenants/users, and 20 controllers in
            <code class="bg-amber-100 px-1 rounded">app/Http/Controllers</code>.
            What's <em>not</em> wired yet: the Livewire components for Dashboard, Reservations, and Front Office.
            The aspirational route map lives in <code class="bg-amber-100 px-1 rounded">routes/web.full.php.bak</code>.
        </p>
        <p class="text-sm text-amber-800">
            Next milestone: build out <code class="bg-amber-100 px-1 rounded">app/Livewire/Dashboard/Overview</code>
            and the auth flow so this becomes a real PMS shell.
        </p>
    </div>
</section>
@endsection
