<div>
    {{-- ============================================================
         GREETING + QUICK ACTIONS BAR
         ============================================================ --}}
    @php
        $hour = now()->format('H');
        $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp
    <div class="rounded-2xl bg-gradient-to-br from-brand-700 via-brand-600 to-indigo-600 text-white px-6 py-6 mb-6 shadow-lg relative overflow-hidden">
        <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 20% 30%, white 0, transparent 40%), radial-gradient(circle at 80% 70%, white 0, transparent 40%);"></div>
        <div class="relative flex items-center justify-between flex-wrap gap-4">
            <div>
                <div class="text-xs uppercase tracking-widest text-brand-100 font-semibold">{{ now()->format('l, d M Y') }}</div>
                <h1 class="text-2xl font-bold mt-1">{{ $greet }}, {{ auth()->user()->name }} 👋</h1>
                <p class="text-sm text-brand-100 mt-1">
                    Here's what's happening at your hotel today —
                    <strong class="text-white">{{ $kpis['arrivals_today'] }}</strong> arrivals,
                    <strong class="text-white">{{ $kpis['departures_today'] }}</strong> departures,
                    <strong class="text-white">{{ $kpis['in_house'] }}</strong> in-house.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if(Route::has('reservations.new'))
                <a href="{{ route('reservations.new') }}" class="bg-white text-brand-700 hover:bg-brand-50 font-semibold text-sm px-4 py-2 rounded-lg shadow flex items-center gap-1.5">
                    <span>✚</span> New booking
                </a>
                @endif
                @if(Route::has('frontoffice.walkin'))
                <a href="{{ route('frontoffice.walkin') }}" class="bg-brand-800 hover:bg-brand-900 text-white font-semibold text-sm px-4 py-2 rounded-lg flex items-center gap-1.5">
                    <span>➔</span> Walk-in
                </a>
                @endif
                @if(Route::has('frontoffice.movement'))
                <a href="{{ route('frontoffice.movement') }}" class="bg-white/10 backdrop-blur hover:bg-white/20 text-white font-semibold text-sm px-4 py-2 rounded-lg border border-white/20">⇄ Movements</a>
                @endif
                @if(Route::has('pos.index'))
                <a href="{{ route('pos.index') }}" class="bg-white/10 backdrop-blur hover:bg-white/20 text-white font-semibold text-sm px-4 py-2 rounded-lg border border-white/20">🍴 POS</a>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================================================
         PRIMARY KPI CARDS — with sparklines, deltas, mini charts
         ============================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Occupancy --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-brand-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Occupancy</div>
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center text-base">◰</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">{{ $kpis['occupancy_pct'] }}%</div>
                <div class="text-xs text-slate-500 mt-1">{{ $kpis['occupied_rooms'] }} of {{ $kpis['total_rooms'] }} rooms occupied</div>
                {{-- Inline progress bar --}}
                <div class="mt-3 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-brand-500 to-brand-700" style="width: {{ $kpis['occupancy_pct'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Today's Revenue --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Today's Revenue</div>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-base">₹</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">₹{{ number_format($kpis['today_revenue'], 0) }}</div>
                <div class="text-xs mt-1 flex items-center gap-1.5">
                    @if($kpis['revenue_delta'] > 0)
                        <span class="text-emerald-600 font-semibold">↑ {{ $kpis['revenue_delta'] }}%</span>
                    @elseif($kpis['revenue_delta'] < 0)
                        <span class="text-rose-600 font-semibold">↓ {{ abs($kpis['revenue_delta']) }}%</span>
                    @else
                        <span class="text-slate-500">—</span>
                    @endif
                    <span class="text-slate-500">vs yesterday</span>
                </div>
                {{-- 7-day sparkline --}}
                @php
                    $maxRev = max(1, $last7->max('rev'));
                @endphp
                <svg class="mt-3 w-full" viewBox="0 0 140 28" preserveAspectRatio="none" style="height:24px">
                    @php
                        $path = '';
                        $area = '';
                        foreach ($last7 as $i => $d) {
                            $x = $i * (140 / 6);
                            $y = 28 - (($d['rev'] / $maxRev) * 24) - 2;
                            $path .= ($i === 0 ? "M {$x},{$y}" : " L {$x},{$y}");
                            $area .= ($i === 0 ? "M {$x},28 L {$x},{$y}" : " L {$x},{$y}");
                        }
                        $area .= ' L 140,28 Z';
                    @endphp
                    <path d="{{ $area }}" fill="rgba(16,185,129,0.15)"/>
                    <path d="{{ $path }}" fill="none" stroke="rgb(16,185,129)" stroke-width="1.5"/>
                </svg>
            </div>
        </div>

        {{-- ADR --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">ADR</div>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-base">◇</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">₹{{ number_format($kpis['adr'], 0) }}</div>
                <div class="text-xs text-slate-500 mt-1">Average daily rate</div>
                <div class="mt-3 flex items-center gap-1 text-xs">
                    <span class="text-slate-500">RevPAR:</span>
                    <span class="font-semibold text-slate-900">₹{{ number_format($kpis['revpar'], 0) }}</span>
                </div>
            </div>
        </div>

        {{-- Outstanding --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-rose-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Outstanding</div>
                    <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-base">!</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">₹{{ number_format($kpis['outstanding'], 0) }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ $kpis['open_folios'] }} open folio{{ $kpis['open_folios'] === 1 ? '' : 's' }}</div>
                <div class="mt-3">
                    @if(Route::has('frontoffice.cashier'))
                    <a href="{{ route('frontoffice.cashier') }}" class="text-xs font-semibold text-rose-600 hover:underline">Settle now →</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         SECONDARY METRIC STRIP — fast at-a-glance ops counts
         ============================================================ --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500">Arrivals</div>
            <div class="flex items-baseline gap-1.5"><span class="text-2xl font-bold text-emerald-600">{{ $kpis['arrivals_today'] }}</span><span class="text-xs text-slate-400">today</span></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500">Departures</div>
            <div class="flex items-baseline gap-1.5"><span class="text-2xl font-bold text-rose-600">{{ $kpis['departures_today'] }}</span><span class="text-xs text-slate-400">today</span></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500">In-house</div>
            <div class="flex items-baseline gap-1.5"><span class="text-2xl font-bold text-brand-700">{{ $kpis['in_house'] }}</span><span class="text-xs text-slate-400">stays</span></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500">HK pending</div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-bold {{ $kpis['hk_pending'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ $kpis['hk_pending'] }}</span>
                <span class="text-xs text-slate-400">+{{ $kpis['hk_in_progress'] }} in progress</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500">POS orders</div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-bold text-slate-900">{{ $kpis['pos_orders'] }}</span>
                <span class="text-xs text-slate-400">₹{{ number_format($kpis['pos_revenue'], 0) }}</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <div class="text-[11px] uppercase tracking-wider text-slate-500">Pickup 7d</div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-bold text-slate-900">{{ $kpis['pickup_7d'] }}</span>
                <span class="text-xs text-slate-400">{{ $kpis['bookings_today'] }} today</span>
            </div>
        </div>
    </div>

    {{-- ============================================================
         OCCUPANCY TREND BAR CHART + SOURCE MIX DONUT
         ============================================================ --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        {{-- 7-day occupancy bar chart --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-slate-900">7-day occupancy & revenue</h2>
                    <p class="text-xs text-slate-500">Last 7 days · room-nights & revenue</p>
                </div>
            </div>
            @php
                $maxOcc = max(1, $last7->max('occ'));
                $maxRev = max(1, $last7->max('rev'));
            @endphp
            <div class="grid grid-cols-7 gap-2 h-44">
                @foreach($last7 as $d)
                    @php
                        $occH = $maxOcc > 0 ? max(4, ($d['occ'] / $maxOcc) * 140) : 4;
                        $revH = $maxRev > 0 ? max(2, ($d['rev'] / $maxRev) * 140) : 2;
                        $isToday = $d['date'] === now()->toDateString();
                    @endphp
                    <div class="flex flex-col items-center justify-end gap-1 group" title="{{ $d['date'] }}: {{ $d['occ'] }} rooms · ₹{{ number_format($d['rev'], 0) }}">
                        <div class="text-[10px] font-mono text-slate-500 mb-1">{{ $d['pct'] }}%</div>
                        <div class="flex items-end gap-0.5 h-36">
                            <div class="w-3 bg-gradient-to-t from-brand-500 to-brand-300 rounded-t transition group-hover:from-brand-600" style="height: {{ $occH }}px"></div>
                            <div class="w-3 bg-gradient-to-t from-emerald-500 to-emerald-300 rounded-t transition group-hover:from-emerald-600" style="height: {{ $revH }}px"></div>
                        </div>
                        <div class="text-[11px] {{ $isToday ? 'font-bold text-brand-700' : 'text-slate-600' }}">{{ $d['label'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-gradient-to-t from-brand-500 to-brand-300"></span>Room-nights</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-gradient-to-t from-emerald-500 to-emerald-300"></span>Revenue</span>
            </div>
        </div>

        {{-- Booking source mix --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-1">Booking source · 30d</h2>
            <p class="text-xs text-slate-500 mb-3">Where reservations are coming from</p>
            @if($sourceMix->isEmpty())
                <div class="text-sm text-slate-500 py-8 text-center">No data yet.</div>
            @else
                <div class="space-y-2">
                    @php
                        $palette = ['bg-brand-500','bg-emerald-500','bg-amber-500','bg-rose-500','bg-indigo-500','bg-cyan-500'];
                    @endphp
                    @foreach($sourceMix as $i => $s)
                        @php $pct = round(($s->count / $sourceTotal) * 100, 1); @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs mb-0.5">
                                <span class="font-medium text-slate-700 capitalize">{{ str_replace('_',' ', $s->source ?: 'direct') }}</span>
                                <span class="font-mono text-slate-500">{{ $s->count }} · {{ $pct }}%</span>
                            </div>
                            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $palette[$i] ?? 'bg-slate-400' }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ============================================================
         TODAY EVENTS STRIP — banquet events for the day
         ============================================================ --}}
    @if($banquetToday->count())
        <div class="rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50/50 p-4 mb-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-semibold text-amber-900 flex items-center gap-2">🎉 Today's events ({{ $banquetToday->count() }})</h3>
                @if(Route::has('banquet.index'))<a href="{{ route('banquet.index') }}" class="text-xs text-amber-700 font-semibold hover:underline">View all →</a>@endif
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($banquetToday as $e)
                    @php
                        $clientName = $e->company?->name ?? trim(($e->guest?->first_name ?? '') . ' ' . ($e->guest?->last_name ?? ''));
                    @endphp
                    <div class="bg-white rounded-lg border border-amber-200 p-3">
                        <div class="text-sm font-semibold text-slate-900">{{ $e->event_name ?: 'Event' }}</div>
                        <div class="text-xs text-slate-600 mt-0.5">{{ \Illuminate\Support\Str::of($e->event_start_time)->limit(5,'') }} – {{ \Illuminate\Support\Str::of($e->event_end_time)->limit(5,'') }} · {{ $e->expected_pax }} pax</div>
                        @if($clientName)<div class="text-xs text-slate-500 mt-0.5">{{ $clientName }}</div>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ============================================================
         ROOM STATUS BOARD + ARRIVALS / DEPARTURES SIDE-BY-SIDE
         ============================================================ --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        {{-- Room status grid --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-slate-900">Room status board</h2>
                    <p class="text-xs text-slate-500">{{ $kpis['vacant_rooms'] }} vacant · {{ $kpis['occupied_rooms'] }} occupied · {{ $kpis['dirty_rooms'] }} dirty · {{ $kpis['ooo_rooms'] }} OOO</p>
                </div>
                <div class="hidden md:flex items-center gap-3 text-[11px]">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>Vacant</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>Occupied</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>Dirty</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>OOO</span>
                </div>
            </div>
            @if($rooms->isEmpty())
                <div class="text-sm text-slate-500 text-center py-8">No rooms configured. <a href="{{ Route::has('setup.rooms') ? route('setup.rooms') : '#' }}" class="text-brand-600 font-medium">Add rooms →</a></div>
            @else
                @php $byFloor = $rooms->groupBy('floor'); @endphp
                <div class="space-y-3">
                @foreach($byFloor as $floor => $floorRooms)
                    <div class="flex items-start gap-3">
                        <div class="w-12 flex-shrink-0 text-[11px] font-semibold text-slate-500 uppercase tracking-wider pt-2">F{{ $floor }}</div>
                        <div class="flex-1 grid grid-cols-6 sm:grid-cols-8 lg:grid-cols-10 gap-1.5">
                            @foreach($floorRooms as $room)
                                @php
                                    $color = match(true) {
                                        $room->status === 'occupied_clean' => 'bg-rose-500 text-white',
                                        $room->status === 'occupied_dirty' => 'bg-rose-700 text-white',
                                        $room->status === 'vacant_dirty' => 'bg-amber-400 text-amber-900',
                                        in_array($room->status, ['out_of_order','out_of_service','blocked']) => 'bg-slate-400 text-white',
                                        $room->status === 'inspected' => 'bg-emerald-600 text-white',
                                        default => 'bg-emerald-400 text-emerald-900',
                                    };
                                @endphp
                                <div class="rounded-md {{ $color }} px-1.5 py-1.5 text-center transition hover:scale-110 hover:shadow cursor-pointer" title="{{ $room->number }} · {{ str_replace('_',' ',$room->status) }}">
                                    <div class="text-xs font-bold leading-tight">{{ $room->number }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                </div>
            @endif
        </div>

        {{-- Combined arrivals + departures --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            {{-- Tab-like toggle (CSS-only via :checked) --}}
            <div x-data="{ tab: 'arr' }" x-cloak>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-1">
                        <button @click="tab='arr'" :class="tab==='arr' ? 'bg-white shadow text-brand-700' : 'text-slate-600'" class="px-3 py-1 text-xs font-semibold rounded-md">Arrivals ({{ $arrivalsList->count() }})</button>
                        <button @click="tab='dep'" :class="tab==='dep' ? 'bg-white shadow text-brand-700' : 'text-slate-600'" class="px-3 py-1 text-xs font-semibold rounded-md">Departures ({{ $departuresList->count() }})</button>
                    </div>
                </div>

                {{-- Arrivals tab --}}
                <div x-show="tab==='arr'">
                    @forelse($arrivalsList as $r)
                        <div class="py-2.5 border-b border-slate-100 last:border-0 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm flex-shrink-0">{{ strtoupper(substr($r->guest_name ?: 'G', 0, 1)) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-1">
                                    <div class="font-medium text-sm text-slate-900 truncate">{{ $r->guest_name ?: 'Guest' }}</div>
                                    <div class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded {{ $r->status === 'checked_in' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }} flex-shrink-0">{{ $r->status === 'checked_in' ? 'In' : 'Due' }}</div>
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $r->arrival_time ? \Carbon\Carbon::parse($r->arrival_time)->format('H:i') : 'No ETA' }} · {{ $r->nights }}N · {{ $r->rooms_count }} rm · ₹{{ number_format($r->total_amount, 0) }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500 py-6 text-center">No arrivals today.</div>
                    @endforelse
                </div>

                {{-- Departures tab --}}
                <div x-show="tab==='dep'" style="display:none">
                    @forelse($departuresList as $r)
                        <div class="py-2.5 border-b border-slate-100 last:border-0 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-sm flex-shrink-0">{{ strtoupper(substr($r->guest_name ?: 'G', 0, 1)) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-1">
                                    <div class="font-medium text-sm text-slate-900 truncate">{{ $r->guest_name ?: 'Guest' }}</div>
                                    <div class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded {{ $r->status === 'checked_out' ? 'bg-slate-100 text-slate-700' : 'bg-rose-100 text-rose-700' }} flex-shrink-0">{{ $r->status === 'checked_out' ? 'Out' : 'Due' }}</div>
                                </div>
                                <div class="text-xs text-slate-500">{{ $r->departure_time ? \Carbon\Carbon::parse($r->departure_time)->format('H:i') : 'No ETD' }} · ₹{{ number_format($r->total_amount, 0) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500 py-6 text-center">No departures today.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         PAYMENT MIX + UPCOMING ARRIVALS + ACTIVITY FEED
         ============================================================ --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        {{-- Payment mode mix --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-1">Payments today</h2>
            <p class="text-xs text-slate-500 mb-3">₹{{ number_format($kpis['payments_total'], 0) }} collected via {{ $paymentByMode->count() }} mode{{ $paymentByMode->count() === 1 ? '' : 's' }}</p>
            @if($paymentByMode->isEmpty())
                <div class="text-sm text-slate-500 py-6 text-center">No payments yet today.</div>
            @else
                <div class="space-y-2">
                    @php $iconMap = ['cash'=>'💵','card'=>'💳','upi'=>'📱','bank_transfer'=>'🏦','cheque'=>'📝','wallet'=>'👛']; @endphp
                    @foreach($paymentByMode as $mode => $amt)
                        @php $pct = $kpis['payments_total'] > 0 ? round(($amt / $kpis['payments_total']) * 100, 1) : 0; @endphp
                        <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-lg">{{ $iconMap[$mode] ?? '💰' }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline justify-between">
                                    <span class="text-sm font-medium text-slate-900 capitalize">{{ str_replace('_',' ', $mode) }}</span>
                                    <span class="text-sm font-mono font-semibold text-slate-900">₹{{ number_format($amt, 0) }}</span>
                                </div>
                                <div class="h-1 bg-slate-100 rounded-full overflow-hidden mt-1">
                                    <div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Upcoming arrivals next 7 days --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-900">Upcoming · 7d</h2>
                @if(Route::has('reservations.index'))<a href="{{ route('reservations.index') }}" class="text-xs text-brand-600 font-semibold">All →</a>@endif
            </div>
            @forelse($upcomingArrivals as $r)
                <div class="py-2.5 border-b border-slate-100 last:border-0 flex items-start gap-3">
                    <div class="w-12 flex-shrink-0 text-center">
                        <div class="text-[10px] uppercase tracking-wider text-slate-500">{{ \Carbon\Carbon::parse($r->arrival_date)->format('M') }}</div>
                        <div class="text-lg font-bold text-slate-900 leading-tight">{{ \Carbon\Carbon::parse($r->arrival_date)->format('d') }}</div>
                        <div class="text-[10px] text-slate-500">{{ \Carbon\Carbon::parse($r->arrival_date)->format('D') }}</div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-slate-900 truncate">{{ $r->guest_name ?: 'Guest' }}</div>
                        <div class="text-xs text-slate-500">{{ $r->nights }}N · {{ $r->rooms_count }} rm · ₹{{ number_format($r->total_amount, 0) }}</div>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500 py-6 text-center">No upcoming arrivals.</div>
            @endforelse
        </div>

        {{-- Activity feed --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-3">Recent activity</h2>
            @forelse($activity as $a)
                @php
                    $tones = [
                        'emerald' => 'bg-emerald-100 text-emerald-700',
                        'rose' => 'bg-rose-100 text-rose-700',
                        'slate' => 'bg-slate-100 text-slate-700',
                        'brand' => 'bg-brand-100 text-brand-700',
                    ];
                    $iconBg = $tones[$a['tone']] ?? 'bg-slate-100 text-slate-700';
                    $icon = $a['kind'] === 'payment' ? '₹' : '☰';
                @endphp
                <div class="py-2 flex items-start gap-3 border-b border-slate-100 last:border-0">
                    <div class="w-7 h-7 rounded-full {{ $iconBg }} flex items-center justify-center text-xs font-bold flex-shrink-0">{{ $icon }}</div>
                    <div class="flex-1 min-w-0">
                        @if($a['href'])
                            <a href="{{ $a['href'] }}" class="text-sm text-slate-900 font-medium hover:text-brand-700 truncate block">{{ $a['title'] }}</a>
                        @else
                            <div class="text-sm text-slate-900 font-medium truncate">{{ $a['title'] }}</div>
                        @endif
                        <div class="text-[11px] text-slate-500">{{ $a['meta'] }} · {{ $a['when'] ? \Carbon\Carbon::parse($a['when'])->diffForHumans() : '' }}</div>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500 py-6 text-center">No recent activity.</div>
            @endforelse
        </div>
    </div>

    {{-- ============================================================
         QUICK NAVIGATION TILES — module shortcuts
         ============================================================ --}}
    @php
        $tiles = [
            ['key'=>'frontoffice.movement','icon'=>'⇄','title'=>'Front Office','desc'=>'Arrivals · Departures','color'=>'from-blue-500 to-indigo-600'],
            ['key'=>'reservations.tape','icon'=>'▦','title'=>'Tape Chart','desc'=>'Visual room grid','color'=>'from-violet-500 to-purple-600'],
            ['key'=>'housekeeping.index','icon'=>'✦','title'=>'Housekeeping','desc'=>$kpis['hk_pending'].' pending','color'=>'from-amber-500 to-orange-600'],
            ['key'=>'pos.index','icon'=>'🍴','title'=>'POS','desc'=>$kpis['pos_open'].' open orders','color'=>'from-rose-500 to-pink-600'],
            ['key'=>'channel.index','icon'=>'⚭','title'=>'Channels','desc'=>'OTAs · GDS','color'=>'from-cyan-500 to-teal-600'],
            ['key'=>'reports.index','icon'=>'◔','title'=>'Reports','desc'=>'Daily flash · GST','color'=>'from-emerald-500 to-green-600'],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @foreach($tiles as $t)
            @if(Route::has($t['key']))
            <a href="{{ route($t['key']) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-brand-300 hover:shadow-md transition p-4">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br {{ $t['color'] }} text-white flex items-center justify-center text-lg shadow group-hover:scale-110 transition">{{ $t['icon'] }}</div>
                <div class="mt-2.5 font-semibold text-sm text-slate-900">{{ $t['title'] }}</div>
                <div class="text-xs text-slate-500">{{ $t['desc'] }}</div>
            </a>
            @endif
        @endforeach
    </div>

    {{-- ============================================================
         ALPINE.JS — needed for the arrivals/departures tab toggle
         ============================================================ --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none}</style>
</div>
