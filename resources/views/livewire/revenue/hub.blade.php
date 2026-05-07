<div>
    <h1 class="text-2xl font-bold mb-1">Revenue Management</h1>
    <p class="text-sm text-slate-600 mb-6">Rate shopper, dynamic pricing rules, demand forecast, OTA parity, BAR.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="border-b flex">
            @foreach(['shopper'=>'Rate shopper','pricing'=>'Pricing rules','forecast'=>'Forecast','competitors'=>'Competitors'] as $k=>$l)
                <button type="button" wire:click="$set('tab','{{ $k }}')" class="px-5 py-3 text-sm font-medium border-b-2 transition {{ $tab === $k ? 'border-brand-600 text-brand-700' : 'border-transparent' }}">{{ $l }}</button>
            @endforeach
        </div>

        @if($tab === 'shopper')
            <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b">
                <div class="text-xs text-slate-500">Last 7 days · {{ $rateShop->count() }} snapshots</div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="shopNow" wire:confirm="Run a shop now? This will scrape rates for the next 7 days." class="text-sm font-medium px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-white">Run shop now</button>
                    <button type="button" wire:click="startCreateRateShop" class="text-sm font-medium px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white">+ Add rate snapshot</button>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Shop date</th><th class="px-4 py-2">Stay date</th><th class="px-4 py-2">Competitor</th><th class="px-4 py-2">Channel</th><th class="px-4 py-2">Room</th><th class="px-4 py-2 text-right">Rate</th><th class="px-4 py-2">Available</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rateShop as $r)
                        <tr><td class="px-5 py-2 text-xs">{{ \Carbon\Carbon::parse($r->shop_date)->format('d M') }}</td><td class="px-4 py-2 text-xs">{{ \Carbon\Carbon::parse($r->stay_date)->format('d M') }}</td><td class="px-4 py-2 text-xs">{{ $r->competitor_name ?? '—' }}</td><td class="px-4 py-2 text-xs">{{ $r->source }}</td><td class="px-4 py-2 text-xs">{{ $r->room_type_label ?? '—' }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($r->rate, 0) }}</td><td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $r->available ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $r->available ? 'avail' : 'sold out' }}</span></td></tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">No rate-shop data yet. Configure competitors in the Competitors tab, then click <em>Run shop now</em>.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($showRateShopForm)
                <form wire:submit.prevent="saveRateShop" class="border-t bg-slate-50 p-5 space-y-4">
                    <h3 class="font-semibold text-sm">New rate snapshot</h3>
                    <div class="grid md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Competitor *</label>
                            <select wire:model="rsCompetitorId" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                                <option value="">Select…</option>
                                @foreach($activeCompetitors as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('rsCompetitorId')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Room type label</label>
                            <input type="text" wire:model="rsRoomLabel" placeholder="Deluxe King" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Rate (₹) *</label>
                            <input type="number" step="0.01" wire:model="rsRate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                            @error('rsRate')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Stay date *</label>
                            <input type="date" wire:model="rsStayDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                            @error('rsStayDate')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Source *</label>
                            <select wire:model="rsSource" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                                <option value="booking.com">Booking.com</option>
                                <option value="mmt">MakeMyTrip</option>
                                <option value="agoda">Agoda</option>
                                <option value="goibibo">Goibibo</option>
                                <option value="expedia">Expedia</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
                        <button type="button" wire:click="cancelRateShopForm" class="px-4 py-2 text-sm">Cancel</button>
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Save snapshot</button>
                    </div>
                </form>
            @endif

        @elseif($tab === 'pricing')
            <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b">
                <div class="text-xs text-slate-500">{{ $pricingRules->count() }} rule{{ $pricingRules->count() === 1 ? '' : 's' }}</div>
                <button type="button" wire:click="startCreateRule" class="text-sm font-medium px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white">+ Create rule</button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Rule name</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Room type</th><th class="px-4 py-2">Action</th><th class="px-4 py-2 text-right">Value</th><th class="px-4 py-2">Validity</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pricingRules as $pr)
                        <tr>
                            <td class="px-5 py-2 font-medium">{{ $pr->name }}</td>
                            <td class="px-4 py-2 text-xs">{{ str_replace('_',' ',$pr->rule_type) }}</td>
                            <td class="px-4 py-2 text-xs">{{ $pr->room_type_name ?? 'All' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $pr->action }}</td>
                            <td class="px-4 py-2 text-right">{{ $pr->value }}</td>
                            <td class="px-4 py-2 text-xs">@if($pr->valid_from){{ \Carbon\Carbon::parse($pr->valid_from)->format('d M') }} – {{ $pr->valid_to ? \Carbon\Carbon::parse($pr->valid_to)->format('d M Y') : '∞' }}@else Always @endif</td>
                            <td class="px-4 py-2"><button type="button" wire:click="toggleRule({{ $pr->id }})" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $pr->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100' }}">{{ $pr->is_active ? 'Active' : 'Off' }}</button></td>
                            <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                <button type="button" wire:click="startEditRule({{ $pr->id }})" class="text-xs text-brand-600 font-medium">Edit</button>
                                <button type="button" wire:click="deleteRule({{ $pr->id }})" wire:confirm="Delete rule '{{ $pr->name }}'?" class="text-xs text-rose-600 font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">No pricing rules. Common rules: weekend +15%, last-minute -10%, occupancy &gt;80% +20%.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($showRuleForm)
                <form wire:submit.prevent="saveRule" class="border-t bg-slate-50 p-5 space-y-4">
                    <h3 class="font-semibold text-sm">{{ $editingRule ? 'Edit rule' : 'New pricing rule' }}</h3>
                    <div class="grid md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                            <input type="text" wire:model="ruleName" placeholder="Weekend +15%" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                            @error('ruleName')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Priority</label>
                            <input type="number" wire:model="rulePriority" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Rule type *</label>
                            <select wire:model="ruleType" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                                <option value="day_of_week">Day of week</option>
                                <option value="days_to_arrival">Days to arrival</option>
                                <option value="season">Season</option>
                                <option value="occupancy_based">Occupancy based</option>
                                <option value="compset_position">Compset position</option>
                                <option value="event">Event</option>
                                <option value="min_max_floor">Min/max floor</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Room type</label>
                            <select wire:model="ruleRoomTypeId" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                                <option value="">All room types</option>
                                @foreach($roomTypes as $rt)
                                    <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Adjustment *</label>
                            <select wire:model="ruleAction" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                                <option value="increase_percent">Increase %</option>
                                <option value="decrease_percent">Decrease %</option>
                                <option value="increase_fixed">Increase ₹</option>
                                <option value="decrease_fixed">Decrease ₹</option>
                                <option value="set_to">Set to ₹</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Value *</label>
                            <input type="number" step="0.01" wire:model="ruleValue" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                            @error('ruleValue')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Valid from</label>
                            <input type="date" wire:model="ruleValidFrom" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Valid to</label>
                            <input type="date" wire:model="ruleValidTo" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Conditions (JSON) *</label>
                            <textarea wire:model="ruleConditionsJson" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono bg-white" placeholder='{"days":["fri","sat"]}'></textarea>
                            @error('ruleConditionsJson')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                            <p class="text-[11px] text-slate-500 mt-1">Examples: <code>{"days":["fri","sat"]}</code> · <code>{"min_days":0,"max_days":3}</code> · <code>{"occupancy_min":80}</code></p>
                        </div>
                        <div class="md:col-span-3">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="ruleIsActive" class="rounded">Active</label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
                        <button type="button" wire:click="cancelRuleForm" class="px-4 py-2 text-sm">Cancel</button>
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Save rule</button>
                    </div>
                </form>
            @endif

        @elseif($tab === 'forecast')
            <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b">
                <div class="text-xs text-slate-500">
                    Pickup pace · {{ $pickupSummary['days'] }} days · {{ $pickupSummary['on_books'] }} room-nights on books · {{ $pickupSummary['pickup_7d'] >= 0 ? '+' : '' }}{{ $pickupSummary['pickup_7d'] }} in last 7 days
                </div>
                <button type="button" wire:click="runForecast" wire:confirm="Regenerate forecast for next 30 days?" class="text-sm font-medium px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white">Run forecast</button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Stay date</th><th class="px-4 py-2 text-right">Forecast occupancy</th><th class="px-4 py-2 text-right">Forecast ARR</th><th class="px-4 py-2 text-right">Forecast RevPAR</th><th class="px-4 py-2 text-right">On books</th><th class="px-4 py-2 text-right">Pickup 7d</th><th class="px-4 py-2">Model</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($forecasts as $f)
                        @php $bp = is_string($f->booking_pace) ? json_decode($f->booking_pace, true) : $f->booking_pace; @endphp
                        <tr>
                            <td class="px-5 py-2 text-xs">{{ \Carbon\Carbon::parse($f->stay_date)->format('D, d M') }}</td>
                            <td class="px-4 py-2 text-right">{{ $f->forecast_occupancy_pct }}%</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($f->forecast_arr ?? 0, 0) }}</td>
                            <td class="px-4 py-2 text-right font-medium">₹{{ number_format($f->forecast_revpar ?? 0, 0) }}</td>
                            <td class="px-4 py-2 text-right text-xs">{{ $bp['on_books'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-right text-xs {{ ($bp['pickup_7d'] ?? 0) > 0 ? 'text-emerald-700' : (($bp['pickup_7d'] ?? 0) < 0 ? 'text-rose-700' : '') }}">{{ isset($bp['pickup_7d']) ? (($bp['pickup_7d'] > 0 ? '+' : '').$bp['pickup_7d']) : '—' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $f->model_version ?? 'baseline' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">No forecast data. Click <em>Run forecast</em> to generate.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @else
            <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b">
                <div class="text-xs text-slate-500">{{ $competitors->count() }} competitor{{ $competitors->count() === 1 ? '' : 's' }}</div>
                <button type="button" wire:click="startCreateCompetitor" class="text-sm font-medium px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white">+ Add competitor</button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Competitor</th><th class="px-4 py-2">Distance</th><th class="px-4 py-2">Booking.com</th><th class="px-4 py-2">MMT</th><th class="px-4 py-2">TripAdvisor</th><th class="px-4 py-2">Compset</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($competitors as $c)
                        <tr>
                            <td class="px-5 py-2 font-medium">{{ $c->name }}</td>
                            <td class="px-4 py-2 text-xs">{{ $c->distance_km ?? '—' }} km</td>
                            <td class="px-4 py-2 text-xs">@if($c->booking_com_url)<a href="{{ $c->booking_com_url }}" target="_blank" class="text-brand-600">↗ link</a>@else — @endif</td>
                            <td class="px-4 py-2 text-xs">@if($c->mmt_url)<a href="{{ $c->mmt_url }}" target="_blank" class="text-brand-600">↗ link</a>@else — @endif</td>
                            <td class="px-4 py-2 text-xs">@if($c->tripadvisor_url)<a href="{{ $c->tripadvisor_url }}" target="_blank" class="text-brand-600">↗ link</a>@else — @endif</td>
                            <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $c->is_primary_compset ? 'bg-amber-100 text-amber-700' : 'bg-slate-100' }}">{{ $c->is_primary_compset ? 'Primary' : 'Watching' }}</span></td>
                            <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $c->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                <button type="button" wire:click="startEditCompetitor({{ $c->id }})" class="text-xs text-brand-600 font-medium">Edit</button>
                                <button type="button" wire:click="deactivateCompetitor({{ $c->id }})" class="text-xs {{ $c->is_active ? 'text-rose-600' : 'text-emerald-600' }} font-medium">{{ $c->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">No competitors configured. <button type="button" wire:click="startCreateCompetitor" class="text-brand-600 font-medium">Add one →</button></td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($showCompetitorForm)
                <form wire:submit.prevent="saveCompetitor" class="border-t bg-slate-50 p-5 space-y-4">
                    <h3 class="font-semibold text-sm">{{ $editingCompetitor ? 'Edit competitor' : 'New competitor' }}</h3>
                    <div class="grid md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                            <input type="text" wire:model="compName" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                            @error('compName')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Distance (km)</label>
                            <input type="number" step="0.01" wire:model="compDistance" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Booking.com URL</label>
                            <input type="url" wire:model="compBookingUrl" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">MMT URL</label>
                            <input type="url" wire:model="compMmtUrl" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">TripAdvisor URL</label>
                            <input type="url" wire:model="compTripadvisorUrl" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        </div>
                        <div class="md:col-span-3 flex items-center gap-4">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="compIsPrimary" class="rounded">Primary compset</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="compIsActive" class="rounded">Active</label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
                        <button type="button" wire:click="cancelCompetitorForm" class="px-4 py-2 text-sm">Cancel</button>
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Save</button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</div>
