<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Hotelesy by WebSenor' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] }, colors: { brand: {50:'#f5f7ff',100:'#e9eeff',200:'#cbd5ff',300:'#a4b3ff',400:'#7a89ff',500:'#5663f5',600:'#3f48dc',700:'#3239b0',800:'#272d8a',900:'#1d2168'}}}}}
    </script>
    <style> body { font-family: 'Inter', sans-serif; } </style>
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen">
@php
    $user = auth()->user();
    $ctx = app(\App\Services\TenantContext::class);
    $tenant = $ctx->tenant();
    $property = $ctx->property();
    $current = request()->route()?->getName();
    // Each nav item carries `perm` — required permission (from spatie). null = always visible.
    // Items are filtered: super_admin / Owner / GM see everything;
    // others only see items they have permission for.
    $navGroups = [
        ['' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => '◰', 'perm' => null],
        ]],
        ['Reservations' => [
            ['key' => 'reservations.index', 'label' => 'All reservations', 'icon' => '☰', 'perm' => 'reservations.view'],
            ['key' => 'reservations.new',   'label' => 'New booking',      'icon' => '✚', 'perm' => 'reservations.create'],
            ['key' => 'reservations.group', 'label' => 'Group booking',    'icon' => '◫', 'perm' => 'reservations.create'],
            ['key' => 'reservations.tape',  'label' => 'Tape chart',       'icon' => '▦', 'perm' => 'reservations.view'],
            ['key' => 'availability',       'label' => 'Availability',     'icon' => '◳', 'perm' => 'reservations.view'],
        ]],
        ['Front Office' => [
            ['key' => 'frontoffice.movement', 'label' => 'Arrivals / Departures', 'icon' => '⇄', 'perm' => 'frontoffice.view'],
            ['key' => 'frontoffice.walkin',   'label' => 'Walk-in check-in',      'icon' => '➔', 'perm' => 'frontoffice.create'],
            ['key' => 'frontoffice.checkin',  'label' => 'Check-in',              'icon' => '⇲', 'perm' => 'frontoffice.edit'],
            ['key' => 'frontoffice.checkout', 'label' => 'Check-out',             'icon' => '⇱', 'perm' => 'frontoffice.edit'],
            ['key' => 'frontoffice.cashier',  'label' => 'Cashier shift',         'icon' => '$', 'perm' => 'frontoffice.view'],
            ['key' => 'folio.index',          'label' => 'Folios',                'icon' => '☰', 'perm' => 'frontoffice.view'],
            ['key' => 'invoices.index',       'label' => 'GST tax invoices',      'icon' => '⚖', 'perm' => 'frontoffice.view'],
        ]],
        ['Operations' => [
            ['key' => 'housekeeping.index', 'label' => 'Housekeeping',          'icon' => '✦', 'perm' => 'housekeeping.view'],
            ['key' => 'pos.index',          'label' => 'POS / Restaurant',      'icon' => '🍴', 'perm' => 'pos.view'],
            ['key' => 'kds.index',          'label' => 'Kitchen Display',       'icon' => '◴', 'perm' => 'pos.view'],
            ['key' => 'amenities.index',    'label' => 'Amenities & services',  'icon' => '✧', 'perm' => 'frontoffice.view'],
            ['key' => 'banquet.index',      'label' => 'Banquet & events',      'icon' => '◈', 'perm' => 'banquet.view'],
            ['key' => 'store.index',        'label' => 'Store / Materials',     'icon' => '▤', 'perm' => 'store.view'],
            ['key' => 'night-audit',        'label' => 'Night audit',           'icon' => '☾', 'perm' => 'frontoffice.edit'],
        ]],
        ['Distribution & Revenue' => [
            ['key' => 'rates.calendar',   'label' => 'Rate calendar',        'icon' => '◫', 'perm' => 'revenue.view'],
            ['key' => 'promotions.index', 'label' => 'Promotions & coupons', 'icon' => '%', 'perm' => 'revenue.view'],
            ['key' => 'channel.index',    'label' => 'Channel manager',      'icon' => '⚭', 'perm' => 'channel.view'],
            ['key' => 'revenue.index',    'label' => 'Revenue management',   'icon' => '↗', 'perm' => 'revenue.view'],
        ]],
        ['CRM & Reports' => [
            ['key' => 'crm.guests',        'label' => 'Guest profiles',     'icon' => '☻', 'perm' => 'crm.view'],
            ['key' => 'crm.companies',     'label' => 'Companies',          'icon' => '⚒', 'perm' => 'crm.view'],
            ['key' => 'reviews.index',     'label' => 'Reviews inbox',      'icon' => '☆', 'perm' => 'crm.view'],
            ['key' => 'reports.index',     'label' => 'Reports overview',   'icon' => '◔', 'perm' => 'reports.view'],
            ['key' => 'reports.flash',     'label' => 'Daily flash',        'icon' => '⚡', 'perm' => 'reports.view'],
            ['key' => 'reports.reservations','label' => 'Reservation status','icon' => '◑', 'perm' => 'reports.view'],
            ['key' => 'reports.revenue',   'label' => 'Revenue report',     'icon' => '📈', 'perm' => 'reports.view'],
            ['key' => 'reports.tax',       'label' => 'Tax / GST',          'icon' => '₹', 'perm' => 'reports.view'],
            ['key' => 'reports.gst',       'label' => 'GST management',     'icon' => '⊞', 'perm' => 'reports.view'],
        ]],
        ['Accounts' => [
            ['key' => 'accounts.index',         'label' => 'Vouchers & ledger', 'icon' => '₹',  'perm' => 'accounts.view'],
            ['key' => 'setup.voucher-types',    'label' => 'Voucher types',     'icon' => '◧', 'perm' => 'accounts.edit'],
        ]],
        ['Compliance' => [
            ['key' => 'compliance.form-c',           'label' => 'Form C / FRRO',     'icon' => '🇮🇳', 'perm' => 'frontoffice.view'],
            ['key' => 'compliance.police-register',  'label' => 'Police register',   'icon' => '◇', 'perm' => 'frontoffice.view'],
            ['key' => 'compliance.tds',              'label' => 'TDS report',        'icon' => '◆', 'perm' => 'accounts.view'],
            ['key' => 'compliance.e-invoices',       'label' => 'E-invoices',        'icon' => '⊕', 'perm' => 'accounts.view'],
        ]],
        ['Setup' => [
            ['key' => 'setup.hub',         'label' => 'Setup hub',          'icon' => '⚙', 'perm' => 'setup.view'],
            ['key' => 'setup.properties',  'label' => 'Properties',         'icon' => '🏨', 'perm' => 'setup.view'],
            ['key' => 'setup.property',    'label' => 'Property settings',  'icon' => '◰', 'perm' => 'setup.edit'],
            ['key' => 'setup.room-types',  'label' => 'Room types',         'icon' => '◰', 'perm' => 'setup.edit'],
            ['key' => 'setup.rooms',       'label' => 'Rooms',              'icon' => '▢', 'perm' => 'setup.edit'],
            ['key' => 'setup.rate-plans',  'label' => 'Rate plans',         'icon' => '◧', 'perm' => 'setup.edit'],
            ['key' => 'setup.taxes',       'label' => 'Taxes',              'icon' => '%', 'perm' => 'setup.edit'],
            ['key' => 'setup.tax-rules',   'label' => 'GST tax rules',      'icon' => '⚖', 'perm' => 'setup.edit'],
            ['key' => 'setup.users',       'label' => 'Users & roles',      'icon' => '☻', 'perm' => 'staff.view'],
            ['key' => 'integrations.index','label' => 'Integrations',       'icon' => '⚯', 'perm' => 'setup.edit'],
        ]],
        ['POS masters' => [
            ['key' => 'setup.pos-outlets',     'label' => 'Outlets',          'icon' => '◰', 'perm' => 'pos.edit'],
            ['key' => 'setup.menu-categories', 'label' => 'Menu categories',  'icon' => '◇', 'perm' => 'pos.edit'],
            ['key' => 'setup.menu-items',      'label' => 'Menu items',       'icon' => '◈', 'perm' => 'pos.edit'],
            ['key' => 'setup.pos-tables',      'label' => 'Tables',           'icon' => '▢', 'perm' => 'pos.edit'],
            ['key' => 'setup.kds-stations',    'label' => 'KDS stations',     'icon' => '◴', 'perm' => 'pos.edit'],
        ]],
        ['Banquet & store masters' => [
            ['key' => 'setup.banquet-halls',    'label' => 'Banquet halls',     'icon' => '◧', 'perm' => 'banquet.edit'],
            ['key' => 'setup.banquet-packages', 'label' => 'Banquet packages',  'icon' => '◇', 'perm' => 'banquet.edit'],
            ['key' => 'setup.store-categories', 'label' => 'Store categories',  'icon' => '▤', 'perm' => 'store.edit'],
        ]],
    ];

    // Permission filter: super admin and Owner / GM see ALL.
    // Otherwise filter items by spatie permission.
    $isPrivileged = $user?->is_super_admin
        || $user?->hasRole('Owner / Director')
        || $user?->hasRole('General Manager');
    $can = function ($perm) use ($user, $isPrivileged) {
        if ($isPrivileged) return true;
        if ($perm === null) return true;
        return $user && $user->hasPermissionTo($perm);
    };
    // Filter & drop empty groups
    $navGroups = collect($navGroups)
        ->map(function ($group) use ($can) {
            return collect($group)
                ->map(fn($items) => collect($items)->filter(fn($it) => $can($it['perm']))->values()->all())
                ->filter(fn($items) => !empty($items))
                ->all();
        })
        ->filter(fn($group) => !empty($group))
        ->values()
        ->all();
@endphp

<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="w-64 bg-white border-r border-slate-200 flex flex-col fixed inset-y-0">
        <div class="px-5 py-5 border-b border-slate-200">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold shadow">H</div>
                <div>
                    <div class="font-bold text-slate-900 leading-tight">Hotelesy</div>
                    <div class="text-[11px] text-slate-500 leading-tight">by WebSenor</div>
                </div>
            </a>
        </div>

        @if(auth()->user()?->is_super_admin)
        <div class="px-3 py-3 border-b border-slate-200 bg-amber-50">
            <div class="px-2 text-[10px] uppercase tracking-wider font-semibold text-amber-700 mb-1">Super admin</div>
            <a href="{{ route('super.dashboard') }}" class="block px-2 py-0.5 text-sm text-amber-900 hover:underline">Sales overview</a>
            <a href="{{ route('super.revenue') }}"   class="block px-2 py-0.5 text-sm text-amber-900 hover:underline">Revenue & invoices</a>
            <a href="{{ route('super.leads') }}"     class="block px-2 py-0.5 text-sm text-amber-900 hover:underline">Lead management</a>
            <a href="{{ route('super.tenants') }}"   class="block px-2 py-0.5 text-sm text-amber-900 hover:underline">Clients (tenants)</a>
            <a href="{{ route('super.licenses') }}"  class="block px-2 py-0.5 text-sm text-amber-900 hover:underline">Licenses</a>
            <a href="{{ route('super.plans') }}"     class="block px-2 py-0.5 text-sm text-amber-900 hover:underline">Subscription plans</a>
        </div>
        @endif

        @if($property)
        <div class="px-5 py-3 border-b border-slate-200 bg-slate-50">
            <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-0.5">Property</div>
            <div class="font-semibold text-sm text-slate-900 leading-tight">{{ $property->name }}</div>
            <div class="text-xs text-slate-500">{{ $property->city }}, {{ $property->country }}</div>
        </div>
        @endif

        <nav class="flex-1 py-3 px-3 overflow-y-auto">
            @foreach($navGroups as $group)
                @foreach($group as $groupLabel => $items)
                    @if($groupLabel)<div class="px-3 mt-3 mb-1 text-[10px] uppercase tracking-wider font-semibold text-slate-400">{{ $groupLabel }}</div>@endif
                    @foreach($items as $item)
                        @php
                            $href = Route::has($item['key']) ? route($item['key']) : '#';
                            $active = $current === $item['key'] || str_starts_with($current ?? '', $item['key']);
                        @endphp
                        <a href="{{ $href }}" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm transition {{ $active ? 'bg-brand-50 text-brand-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            <span class="w-5 text-center text-[13px] {{ $active ? 'text-brand-600' : 'text-slate-400' }}">{{ $item['icon'] ?? '·' }}</span>
                            <span class="flex-1">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endforeach
            @endforeach
        </nav>

        <div class="border-t border-slate-200 p-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-semibold text-sm">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-slate-900 truncate">{{ $user->name ?? 'Guest' }}</div>
                    <div class="text-xs text-slate-500 truncate">{{ $user->email ?? '' }}</div>
                    @php $primaryRole = $user?->is_super_admin ? 'Super Admin' : ($user?->getRoleNames()->first() ?? null); @endphp
                    @if($primaryRole)
                        <div class="mt-1 inline-block text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-100">{{ $primaryRole }}</div>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ url('logout') }}">
                @csrf
                <button type="submit" class="w-full text-xs text-slate-600 hover:text-rose-600 text-left px-3 py-1.5 rounded hover:bg-slate-100">↗ Sign out</button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 ml-64">
        @php
            $shellLicense = null;
            if (auth()->check() && !auth()->user()->is_super_admin && auth()->user()->tenant_id) {
                $shellLicense = app(\App\Services\TenantContext::class)->bypass(
                    fn () => \App\Models\License::where('tenant_id', auth()->user()->tenant_id)->first()
                );
            }
            $shellShowBanner = $shellLicense && (
                $shellLicense->status === \App\Models\License::STATUS_TRIAL
                || ($shellLicense->expires_at && $shellLicense->expires_at->lt(now()->addDays(7)))
            );
        @endphp
        @if($shellShowBanner)
            <div class="bg-amber-50 border-b border-amber-200 px-8 py-2 text-xs text-amber-800 flex items-center justify-between">
                <div>
                    <strong>{{ ucfirst($shellLicense->status) }}:</strong>
                    license expires {{ $shellLicense->expires_at?->diffForHumans() }}
                    ({{ $shellLicense->daysRemaining() }} day{{ $shellLicense->daysRemaining() === 1 ? '' : 's' }} left).
                    @if($shellLicense->status === \App\Models\License::STATUS_TRIAL)
                        <a href="mailto:support@hotelesy.app" class="underline ml-1">Upgrade now</a>
                    @endif
                </div>
            </div>
        @endif
        <header class="bg-white border-b border-slate-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    @php
                        $detailLabels = [
                            'reservations.show' => 'Reservation',
                            'crm.guest.show'    => 'Guest profile',
                            'folio.show'        => 'Folio',
                        ];
                        $currentLabel = $detailLabels[$current ?? ''] ?? null;
                        if (!$currentLabel) {
                            $currentLabel = 'Dashboard';
                            foreach ($navGroups as $g) {
                                foreach ($g as $items) {
                                    foreach ($items as $it) {
                                        if ($current === $it['key']) { $currentLabel = $it['label']; }
                                    }
                                }
                            }
                        }
                    @endphp
                    <h1 class="text-xl font-bold text-slate-900">{{ $currentLabel }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    @if(session('success'))
                        <div class="px-3 py-1.5 rounded-md bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">{{ session('success') }}</div>
                    @endif
                    @if($tenant)
                        <span class="px-3 py-1 text-xs font-medium bg-brand-50 text-brand-700 rounded-full border border-brand-200">{{ $tenant->name }}</span>
                    @endif
                    <span class="px-3 py-1 text-xs font-medium bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200">{{ config('app.env') }}</span>
                </div>
            </div>
        </header>

        <main class="p-8">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
