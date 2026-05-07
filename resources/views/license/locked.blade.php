@extends('layouts.guest')

@section('content')
@php
    $tenant = app(\App\Services\TenantContext::class)->tenant();
    $license = $tenant?->license()->with('plan')->first();
@endphp

<div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
    <div class="flex items-start gap-4 mb-6">
        <div class="w-12 h-12 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-2xl font-bold">🔒</div>
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                @if($license && $license->status === 'cancelled')
                    Your account has been cancelled
                @else
                    Your account is suspended
                @endif
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                @if($license?->suspended_reason)
                    Reason: {{ $license->suspended_reason }}
                @else
                    Access to Hotelesy has been temporarily disabled.
                @endif
            </p>
        </div>
    </div>

    @if(session('warning'))
        <div class="mb-5 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">{{ session('warning') }}</div>
    @endif

    <div class="bg-slate-50 rounded-lg p-4 text-sm text-slate-700 mb-6">
        Please contact our support team to resolve this issue and restore access.
        Have your account email and tenant name ready.
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
        <a href="mailto:support@hotelesy.app?subject=Account%20suspended{{ $tenant ? '%20-%20' . urlencode($tenant->name) : '' }}" class="flex-1 text-center bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-600 hover:to-brand-800 text-white font-semibold py-2.5 rounded-lg transition shadow-md">
            Contact support
        </a>
        <form method="POST" action="{{ url('logout') }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold py-2.5 rounded-lg transition">Sign out</button>
        </form>
    </div>
</div>
@endsection
