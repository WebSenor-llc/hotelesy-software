@extends('layouts.guest')

@section('content')
@php
    $tenant = app(\App\Services\TenantContext::class)->tenant();
    $license = $tenant?->license()->with('plan')->first();
    $plans = \App\Models\SubscriptionPlan::where('is_active', true)->where('code', '!=', 'trial')->orderBy('price_monthly')->get();
@endphp

<div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
    <div class="flex items-start gap-4 mb-6">
        <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-2xl font-bold">⏰</div>
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                @if($license && $license->status === 'trial')
                    Your trial has ended
                @else
                    Your license has expired
                @endif
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                @if($license)
                    Expired on {{ optional($license->expires_at)->format('d M Y') }} ·
                    Plan was <strong>{{ $license->plan?->name ?? '—' }}</strong>.
                @else
                    No active license found for your account.
                @endif
            </p>
        </div>
    </div>

    @if(session('warning'))
        <div class="mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif

    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">Choose a plan to continue</h2>
    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        @foreach($plans as $plan)
            <div class="border border-slate-200 rounded-xl p-4 hover:border-brand-300 hover:shadow-sm transition">
                <div class="font-bold text-slate-900">{{ $plan->name }}</div>
                <div class="text-2xl font-bold text-brand-700 mt-2">₹{{ number_format($plan->price_monthly, 0) }}<span class="text-xs text-slate-500 font-normal">/mo</span></div>
                <ul class="mt-3 text-xs text-slate-600 space-y-1">
                    <li>{{ $plan->max_properties }} {{ Str::plural('property', $plan->max_properties) }}</li>
                    <li>Up to {{ $plan->max_rooms }} rooms</li>
                    <li>Up to {{ $plan->max_users }} users</li>
                </ul>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
        <a href="mailto:sales@hotelesy.app?subject=Upgrade%20my%20Hotelesy%20plan{{ $tenant ? '%20-%20' . urlencode($tenant->name) : '' }}" class="flex-1 text-center bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-600 hover:to-brand-800 text-white font-semibold py-2.5 rounded-lg transition shadow-md">
            Contact sales →
        </a>
        <form method="POST" action="{{ url('logout') }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold py-2.5 rounded-lg transition">Sign out</button>
        </form>
    </div>

    <p class="text-xs text-slate-500 text-center mt-6">
        Need help? Email <a href="mailto:support@hotelesy.app" class="text-brand-700 hover:underline">support@hotelesy.app</a>.
    </p>
</div>
@endsection
