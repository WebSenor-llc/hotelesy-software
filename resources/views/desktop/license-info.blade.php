<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>License · Hotelesy Desktop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] }, colors: { brand: {50:'#f5f7ff',100:'#e9eeff',200:'#cbd5ff',300:'#a4b3ff',400:'#7a89ff',500:'#5663f5',600:'#3f48dc',700:'#3239b0',800:'#272d8a',900:'#1d2168'}}}}}
    </script>
    <style> body { font-family: 'Inter', sans-serif; } .drag { -webkit-app-region: drag; } </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="drag h-8 bg-slate-900 text-slate-300 flex items-center px-4 text-[11px] tracking-wide select-none">
        <span class="font-semibold text-white">Hotelesy</span>
        <span class="mx-2 text-slate-500">·</span>
        <span>Desktop edition</span>
        <span class="ml-auto text-slate-500">v{{ config('desktop.version', '1.0.0') }}</span>
    </div>

    <div class="max-w-2xl mx-auto p-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="px-7 py-5 border-b border-slate-200 bg-gradient-to-br from-slate-50 to-white">
                <h1 class="text-xl font-bold text-slate-900">License &amp; machine</h1>
                <p class="text-sm text-slate-600 mt-0.5">Diagnostic information for your Hotelesy Desktop installation.</p>
            </div>

            @if(session('success'))<div class="mx-7 mt-5 px-3 py-2 rounded bg-emerald-50 border border-emerald-200 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            @if(session('warning'))<div class="mx-7 mt-5 px-3 py-2 rounded bg-amber-50 border border-amber-200 text-sm text-amber-800">{{ session('warning') }}</div>@endif

            <div class="p-7 space-y-5 text-sm">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Status</div>
                        <div class="mt-1">
                            @php
                                $colors = [
                                    'active'      => 'bg-emerald-100 text-emerald-800',
                                    'soft_warn'   => 'bg-amber-100 text-amber-800',
                                    'readonly'    => 'bg-rose-100 text-rose-800',
                                    'invalid'     => 'bg-rose-100 text-rose-800',
                                    'unactivated' => 'bg-slate-100 text-slate-700',
                                ];
                                $cls = $colors[$state['status']] ?? 'bg-slate-100 text-slate-700';
                            @endphp
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $cls }}">
                                {{ strtoupper($state['status']) }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Machine</div>
                        <div class="mt-1 text-slate-900 font-mono">{{ $fingerprint }}</div>
                        <div class="text-xs text-slate-500">{{ $host }} · {{ $os }}</div>
                    </div>

                    @if(!empty($state['license']))
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Plan</div>
                            <div class="mt-1 text-slate-900 capitalize">{{ $state['license']['plan'] ?? 'desktop' }}</div>
                        </div>

                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Valid until</div>
                            <div class="mt-1 text-slate-900">
                                {{ !empty($state['license']['valid_until'])
                                    ? \Illuminate\Support\Carbon::parse($state['license']['valid_until'])->format('d M Y')
                                    : 'Lifetime' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Activated</div>
                            <div class="mt-1 text-slate-900">
                                {{ !empty($state['license']['activated_at'])
                                    ? \Illuminate\Support\Carbon::parse($state['license']['activated_at'])->format('d M Y')
                                    : '—' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Last validated</div>
                            <div class="mt-1 text-slate-900">
                                @if(!empty($state['license']['last_validated']))
                                    {{ \Illuminate\Support\Carbon::parse($state['license']['last_validated'])->diffForHumans() }}
                                @else
                                    never
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                @if(($state['days_offline'] ?? 0) > 0)
                    <div class="px-4 py-3 rounded bg-slate-50 border border-slate-200 text-xs text-slate-700">
                        <strong>Offline for {{ $state['days_offline'] }} days.</strong>
                        Hotelesy will switch to read-only mode after {{ config('desktop.lock_readonly_after_days', 60) }} offline days.
                    </div>
                @endif
            </div>

            <div class="bg-slate-50 border-t border-slate-200 px-7 py-4 flex items-center gap-3">
                <form method="POST" action="{{ route('desktop.license.phonehome') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow">
                        Re-validate now
                    </button>
                </form>

                <form method="POST" action="{{ route('desktop.license.deactivate') }}"
                      onsubmit="return confirm('Release this license slot? You will need the activation key to re-enable Hotelesy on this computer or another.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white border border-rose-300 text-rose-700 hover:bg-rose-50">
                        Deactivate this machine
                    </button>
                </form>

                @auth<a href="{{ route('dashboard') }}" class="ml-auto text-xs text-slate-500 hover:text-slate-800">← Back to dashboard</a>@endauth
            </div>
        </div>
    </div>
</body>
</html>
