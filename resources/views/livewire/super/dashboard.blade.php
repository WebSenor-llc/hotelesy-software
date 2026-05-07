<div>
    {{-- ============== HEADER ============== --}}
    <div class="rounded-2xl bg-gradient-to-br from-amber-600 via-amber-700 to-rose-700 text-white px-6 py-6 mb-6 shadow-lg relative overflow-hidden">
        <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 30% 30%, white 0, transparent 40%), radial-gradient(circle at 70% 70%, white 0, transparent 40%);"></div>
        <div class="relative flex items-center justify-between flex-wrap gap-4">
            <div>
                <div class="text-xs uppercase tracking-widest text-amber-100 font-semibold">{{ now()->format('l, d M Y · H:i') }}</div>
                <h1 class="text-2xl font-bold mt-1">Super Admin · Sales Operations</h1>
                <p class="text-sm text-amber-100 mt-1">
                    <strong class="text-white">{{ $kpis['active_clients'] }}</strong> active clients ·
                    <strong class="text-white">₹{{ number_format($kpis['mrr'], 0) }}</strong> MRR ·
                    <strong class="text-white">{{ $kpis['expiring_7'] }}</strong> expiring this week
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('super.tenants') }}" class="bg-white text-amber-700 hover:bg-amber-50 font-semibold text-sm px-4 py-2 rounded-lg shadow">All tenants</a>
                <a href="{{ route('super.licenses') }}" class="bg-white/10 backdrop-blur hover:bg-white/20 text-white font-semibold text-sm px-4 py-2 rounded-lg border border-white/20">Licenses</a>
                <a href="{{ route('super.plans') }}" class="bg-white/10 backdrop-blur hover:bg-white/20 text-white font-semibold text-sm px-4 py-2 rounded-lg border border-white/20">Plans</a>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    {{-- ============== HERO KPIs ============== --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Revenue this month --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Revenue · this month</div>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-base">₹</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">₹{{ number_format($kpis['revenue_this_month'], 0) }}</div>
                <div class="text-xs mt-1 flex items-center gap-1.5">
                    @if($kpis['revenue_delta'] > 0)
                        <span class="text-emerald-600 font-semibold">↑ {{ $kpis['revenue_delta'] }}%</span>
                    @elseif($kpis['revenue_delta'] < 0)
                        <span class="text-rose-600 font-semibold">↓ {{ abs($kpis['revenue_delta']) }}%</span>
                    @else
                        <span class="text-slate-500">—</span>
                    @endif
                    <span class="text-slate-500">vs last month</span>
                </div>
                @php $maxRev = max(1, $monthlyTrend->max('rev')); @endphp
                <svg class="mt-3 w-full" viewBox="0 0 140 28" preserveAspectRatio="none" style="height:24px">
                    @php
                        $path = ''; $area = '';
                        foreach ($monthlyTrend as $i => $d) {
                            $x = $i * (140 / 5);
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

        {{-- MRR --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-brand-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Monthly Recurring</div>
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center text-base">↻</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">₹{{ number_format($kpis['mrr'], 0) }}</div>
                <div class="text-xs text-slate-500 mt-1">ARR ₹{{ number_format($kpis['arr'], 0) }}</div>
                <div class="mt-3 text-xs text-slate-500">Lifetime: <strong class="text-slate-900">₹{{ number_format($kpis['revenue_lifetime'], 0) }}</strong></div>
            </div>
        </div>

        {{-- Active clients --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-violet-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Clients</div>
                    <div class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center text-base">☻</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">{{ $kpis['active_clients'] }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ $kpis['total_tenants'] }} total tenants</div>
                <div class="mt-3 text-xs text-emerald-600 font-semibold">+{{ $kpis['new_tenants_month'] }} this month</div>
            </div>
        </div>

        {{-- New subscriptions --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-50 rounded-full -mr-8 -mt-8 opacity-60"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">New subs · this month</div>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-base">✚</div>
                </div>
                <div class="text-3xl font-bold text-slate-900">{{ $kpis['new_subs_month'] }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ $leadStats['this_month'] }} leads · {{ round(($leadStats['this_month'] > 0 ? $kpis['new_subs_month']/$leadStats['this_month']*100 : 0), 1) }}% conv</div>
            </div>
        </div>
    </div>

    {{-- ============== EXPIRY ALERTS BAR ============== --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @php
            $alerts = [
                ['7 days',   $kpis['expiring_7'],  'bg-rose-50 border-rose-300 text-rose-700',     '🔥'],
                ['30 days',  $kpis['expiring_30'], 'bg-amber-50 border-amber-300 text-amber-700',  '⚠'],
                ['3 months', $kpis['expiring_90'], 'bg-sky-50 border-sky-300 text-sky-700',        '⏳'],
                ['Expired',  $kpis['expired_count'], 'bg-slate-100 border-slate-300 text-slate-700','✗'],
            ];
        @endphp
        @foreach($alerts as $a)
            <div class="rounded-xl border-2 {{ $a[2] }} p-3.5">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs uppercase tracking-wider font-semibold opacity-80">Expiring {{ $a[0] }}</span>
                    <span class="text-lg">{{ $a[3] }}</span>
                </div>
                <div class="text-3xl font-bold">{{ $a[1] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ============== TWO-COLUMN: REVENUE TREND + PLAN MIX ============== --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-slate-900">6-month trend</h2>
                    <p class="text-xs text-slate-500">Revenue & new sign-ups</p>
                </div>
            </div>
            @php
                $maxR = max(1, $monthlyTrend->max('rev'));
                $maxS = max(1, $monthlyTrend->max('signups'));
            @endphp
            <div class="grid grid-cols-6 gap-3 h-44">
                @foreach($monthlyTrend as $d)
                    @php
                        $rH = $maxR > 0 ? max(4, ($d['rev'] / $maxR) * 140) : 4;
                        $sH = $maxS > 0 ? max(2, ($d['signups'] / $maxS) * 140) : 2;
                    @endphp
                    <div class="flex flex-col items-center justify-end gap-1 group" title="{{ $d['label'] }}: ₹{{ number_format($d['rev'], 0) }} · {{ $d['signups'] }} signups">
                        <div class="text-[10px] font-mono text-slate-500 mb-1">₹{{ number_format($d['rev']/1000, 0) }}k</div>
                        <div class="flex items-end gap-0.5 h-36">
                            <div class="w-3 bg-gradient-to-t from-emerald-500 to-emerald-300 rounded-t" style="height: {{ $rH }}px"></div>
                            <div class="w-3 bg-gradient-to-t from-violet-500 to-violet-300 rounded-t" style="height: {{ $sH }}px"></div>
                        </div>
                        <div class="text-[11px] text-slate-600">{{ $d['label'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-gradient-to-t from-emerald-500 to-emerald-300"></span>Revenue</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-gradient-to-t from-violet-500 to-violet-300"></span>Sign-ups</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-1">Plan mix</h2>
            <p class="text-xs text-slate-500 mb-3">Active subscriptions by plan</p>
            @if($planMix->isEmpty())
                <div class="text-sm text-slate-500 py-8 text-center">No active subs.</div>
            @else
                @php $palette = ['bg-brand-500','bg-emerald-500','bg-amber-500','bg-rose-500','bg-violet-500','bg-cyan-500']; @endphp
                @foreach($planMix as $i => $p)
                    @php $pct = round(($p->count / $planMixTotal) * 100, 1); @endphp
                    <div class="mb-2.5">
                        <div class="flex items-center justify-between text-xs mb-0.5">
                            <span class="font-medium text-slate-700">{{ $p->plan_name }}</span>
                            <span class="font-mono text-slate-500">{{ $p->count }} · {{ $pct }}%</span>
                        </div>
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full {{ $palette[$i] ?? 'bg-slate-400' }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ============== EXPIRING SOON — 7 DAYS (HOTLIST) ============== --}}
    <div class="bg-white rounded-2xl border border-rose-200 mb-6 overflow-hidden">
        <div class="px-5 py-3 border-b bg-rose-50 flex items-center justify-between">
            <h2 class="font-semibold text-rose-900 flex items-center gap-2">🔥 Expiring within 7 days · {{ $expiringWithin7->count() }}</h2>
            <span class="text-xs text-rose-700">Send renewal reminders below</span>
        </div>
        @if($expiringWithin7->isEmpty())
            <div class="p-8 text-sm text-center text-slate-500">No urgent renewals. </div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr><th class="px-5 py-2 text-left">Tenant</th><th class="px-3 py-2 text-left">Plan</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Expires</th><th class="px-3 py-2 text-left">Reminder</th><th class="px-5 py-2 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($expiringWithin7 as $l)
                <tr class="hover:bg-rose-50/50">
                    <td class="px-5 py-3">
                        <div class="font-semibold text-slate-900">{{ $l->tenant->name }}</div>
                        <div class="text-xs text-slate-500">{{ $l->tenant->owner_email }}</div>
                    </td>
                    <td class="px-3 py-3 text-xs">{{ $l->plan?->name ?? '—' }}</td>
                    <td class="px-3 py-3"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $l->statusBadgeClass() }}">{{ $l->status }}</span></td>
                    <td class="px-3 py-3 text-xs">
                        <div class="font-semibold text-rose-700">{{ $l->expires_at?->format('d M Y') }}</div>
                        <div class="text-[10px] text-slate-500">in {{ $l->daysRemaining() }}d</div>
                    </td>
                    <td class="px-3 py-3 text-xs">
                        @if($l->last_reminder_sent_at)<span class="text-emerald-700">Sent {{ $l->last_reminder_sent_at->diffForHumans() }}</span>@else<span class="text-slate-400">Not yet</span>@endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <button wire:click="sendReminder({{ $l->id }})" class="text-xs bg-rose-600 hover:bg-rose-700 text-white font-semibold px-3 py-1.5 rounded">Send reminder</button>
                        <a href="{{ route('super.tenants.show', $l->tenant_id) }}" class="text-xs ml-1 px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-50 text-slate-700">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ============== EXPIRING 30 / 90 DAYS  + EXPIRED  ============== --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-semibold text-amber-900 mb-3">⚠ Expiring within 30 days · {{ $expiringWithin30->count() }}</h3>
            @if($expiringWithin30->isEmpty())<div class="text-sm text-slate-500 py-4 text-center">None</div>@else
                @foreach($expiringWithin30->take(5) as $l)
                <div class="py-2 border-b border-slate-100 last:border-0 flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-slate-900 truncate">{{ $l->tenant->name }}</div>
                        <div class="text-xs text-slate-500">{{ $l->plan?->name }} · {{ $l->expires_at?->format('d M') }} · <span class="text-amber-700">{{ $l->daysRemaining() }}d</span></div>
                    </div>
                    <button wire:click="sendReminder({{ $l->id }})" class="text-[10px] bg-amber-100 hover:bg-amber-200 text-amber-800 px-2 py-1 rounded font-semibold">Remind</button>
                </div>
                @endforeach
                @if($expiringWithin30->count() > 5)<div class="text-xs text-slate-500 mt-2 text-center">+{{ $expiringWithin30->count() - 5 }} more</div>@endif
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-semibold text-sky-900 mb-3">⏳ Expiring within 3 months · {{ $expiringWithin90->count() }}</h3>
            @if($expiringWithin90->isEmpty())<div class="text-sm text-slate-500 py-4 text-center">None</div>@else
                @foreach($expiringWithin90->take(5) as $l)
                <div class="py-2 border-b border-slate-100 last:border-0">
                    <div class="font-medium text-sm text-slate-900 truncate">{{ $l->tenant->name }}</div>
                    <div class="text-xs text-slate-500">{{ $l->plan?->name }} · {{ $l->expires_at?->format('d M Y') }}</div>
                </div>
                @endforeach
                @if($expiringWithin90->count() > 5)<div class="text-xs text-slate-500 mt-2 text-center">+{{ $expiringWithin90->count() - 5 }} more</div>@endif
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-900 mb-3">✗ Recently expired · {{ $expired->count() }}</h3>
            @if($expired->isEmpty())<div class="text-sm text-slate-500 py-4 text-center">None</div>@else
                @foreach($expired->take(5) as $l)
                <div class="py-2 border-b border-slate-100 last:border-0">
                    <div class="font-medium text-sm text-slate-900 truncate">{{ $l->tenant->name }}</div>
                    <div class="text-xs text-slate-500">Expired {{ $l->expires_at?->diffForHumans() }}</div>
                </div>
                @endforeach
                @if($expired->count() > 5)<div class="text-xs text-slate-500 mt-2 text-center">+{{ $expired->count() - 5 }} more</div>@endif
            @endif
        </div>
    </div>

    {{-- ============== LEADS + RECENT TRANSACTIONS ============== --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        {{-- Lead pipeline --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-slate-900">Lead pipeline</h3>
                <a href="{{ route('super.leads') }}" class="text-xs text-brand-600 font-semibold">All leads →</a>
            </div>
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-sky-50 rounded-lg p-2.5 text-center">
                    <div class="text-2xl font-bold text-sky-700">{{ $leadStats['new'] }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-sky-700">New</div>
                </div>
                <div class="bg-violet-50 rounded-lg p-2.5 text-center">
                    <div class="text-2xl font-bold text-violet-700">{{ $leadStats['qualified'] }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-violet-700">Qualified</div>
                </div>
                <div class="bg-emerald-50 rounded-lg p-2.5 text-center">
                    <div class="text-2xl font-bold text-emerald-700">{{ $leadStats['won'] }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-emerald-700">Won</div>
                </div>
            </div>
            <div class="space-y-1.5 max-h-72 overflow-y-auto">
                @forelse($recentLeads as $lead)
                    <a href="{{ route('super.leads') }}" class="block py-2 px-2.5 hover:bg-slate-50 rounded">
                        <div class="flex items-center justify-between">
                            <div class="font-medium text-sm text-slate-900 truncate">{{ $lead->name }}</div>
                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded {{ $lead->statusBadgeClass() }}">{{ $lead->status }}</span>
                        </div>
                        <div class="text-xs text-slate-500 truncate">{{ $lead->hotel_name ?: $lead->email }} · {{ $lead->created_at->diffForHumans() }}</div>
                    </a>
                @empty
                    <div class="text-sm text-slate-500 py-6 text-center">No leads yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Recent transactions --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Recent transactions</h3>
            </div>
            @if($recentTx->isEmpty())
                <div class="p-8 text-sm text-center text-slate-500">No transactions yet.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Tenant</th><th class="px-4 py-2 text-left">Type</th><th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-left">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($recentTx as $t)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 text-xs">{{ $t->created_at->format('d M H:i') }}</td>
                        <td class="px-4 py-2.5">
                            <div class="font-medium text-sm">{{ $t->tenant?->name ?? '—' }}</div>
                            <div class="text-[10px] text-slate-500 font-mono">{{ $t->invoice_number }}</div>
                        </td>
                        <td class="px-4 py-2.5 text-xs">{{ str_replace('_',' ', $t->type) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-xs">₹{{ number_format($t->amount, 2) }}</td>
                        <td class="px-4 py-2.5"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $t->statusBadgeClass() }}">{{ $t->status }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>
