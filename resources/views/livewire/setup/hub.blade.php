<div>
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Master setup</h1>
    <p class="text-sm text-slate-600 mb-6">Configure properties, room types, rate plans, taxes, users, promotions, integrations.</p>

    <div class="grid md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach([
            ['Properties',                'setup.properties',       'Multiple hotels under your tenant',     $stats['properties'].' total'],
            ['Property settings',         'setup.property',         'Address, GST, FSSAI, business hours',   'current'],
            ['Room types',                'setup.room-types',       'Categories with rates and occupancy',   $stats['roomTypes'].' types'],
            ['Rooms',                     'setup.rooms',            'Physical rooms, floors, statuses',      $stats['rooms'].' rooms'],
            ['Rate plans',                'setup.rate-plans',       'BAR, OTA, corporate, promotional',      $stats['ratePlans'].' plans'],
            ['Taxes',                     'setup.taxes',            'GST slabs, service charge, luxury tax', $stats['taxes'].' taxes'],
            ['Users & roles',             'setup.users',            'Staff accounts and permissions',        $stats['users'].' users'],

            ['POS outlets',               'setup.pos-outlets',      'Restaurants, bars, room service',       $stats['posOutlets'].' outlets'],
            ['Menu categories',           'setup.menu-categories',  'POS menu structure',                    $stats['menuCategories'].' categories'],
            ['Menu items',                'setup.menu-items',       'F&B items, prices, taxes, food type',   $stats['menuItems'].' items'],
            ['POS tables',                'setup.pos-tables',       'Floor plan and table layouts',          $stats['posTables'].' tables'],
            ['KDS stations',              'setup.kds-stations',     'Kitchen display routing per outlet',    $stats['kdsStations'].' stations'],

            ['Banquet halls',             'setup.banquet-halls',    'Event spaces, capacities, rates',       $stats['banquetHalls'].' halls'],
            ['Banquet packages',          'setup.banquet-packages', 'Per-pax wedding/conference packages',   $stats['banquetPackages'].' packages'],

            ['Companies',                 'crm.companies',          'Corporate ledgers, GST, credit terms',  $stats['companies'].' companies'],
            ['Store categories',          'setup.store-categories', 'F&B inventory taxonomy',                $stats['storeCategories'].' categories'],
            ['Voucher types',             'setup.voucher-types',    'Receipt, payment, journal series',      $stats['voucherTypes'].' types'],

            ['Promotions & coupons',      'promotions.index',       'Discount codes, vouchers, offers',      ''],
            ['Integrations',              'integrations.index',     'Razorpay, channels, locks, OCR',        ''],
        ] as $card)
            <a href="{{ Route::has($card[1]) ? route($card[1]) : '#' }}" class="bg-white rounded-xl border p-5 hover:border-brand-300 hover:shadow-md transition">
                <div class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div class="font-bold text-slate-900 mb-1">{{ $card[0] }}</div>
                <div class="text-xs text-slate-500 mb-2">{{ $card[2] }}</div>
                @if($card[3])<div class="text-[10px] uppercase tracking-wider font-semibold text-brand-600">{{ $card[3] }}</div>@endif
            </a>
        @endforeach
    </div>
</div>
