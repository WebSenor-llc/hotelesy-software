<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $tenant->name }}</h1>
            <div class="text-xs text-slate-500 font-mono">{{ $tenant->slug }} · {{ $tenant->owner_email }}</div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('super.tenants') }}" class="text-sm text-slate-600">&larr; All tenants</a>
            <a href="{{ route('super.dashboard') }}" class="text-sm text-slate-600">/super</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if($newKey)
        <div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 text-sm">
            <div class="font-semibold mb-1">License key issued — copy now (shown once)</div>
            <code class="font-mono text-base">{{ $newKey }}</code>
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs uppercase tracking-wider text-slate-500">Status</div>
            @if($tenant->license)
                <span class="inline-block mt-2 text-[11px] uppercase tracking-wider px-2 py-1 rounded {{ $tenant->license->statusBadgeClass() }}">{{ $tenant->license->status }}</span>
            @else
                <span class="inline-block mt-2 text-[11px] uppercase tracking-wider px-2 py-1 rounded bg-slate-100 text-slate-600">No license</span>
            @endif
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs uppercase tracking-wider text-slate-500">Plan</div>
            <div class="text-lg font-semibold text-slate-900 mt-1">{{ $tenant->license?->plan?->name ?? '—' }}</div>
            <div class="text-xs text-slate-500">{{ $tenant->license?->billing_cycle ?? '—' }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs uppercase tracking-wider text-slate-500">Expires</div>
            <div class="text-lg font-semibold text-slate-900 mt-1">{{ $tenant->license?->expires_at?->format('d M Y') ?? '—' }}</div>
            <div class="text-xs {{ ($tenant->license?->daysRemaining() ?? 0) <= 7 ? 'text-rose-600' : 'text-slate-500' }}">
                {{ $tenant->license ? $tenant->license->daysRemaining() . ' days left' : '' }}
            </div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs uppercase tracking-wider text-slate-500">Footprint</div>
            <div class="text-sm font-semibold text-slate-900 mt-1">{{ $properties->count() }} props · {{ $roomsCount }} rooms · {{ $usersCount }} users</div>
        </div>
    </div>

    {{-- License key + actions --}}
    <div class="bg-white rounded-xl border p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-slate-900">Current license</h2>
            <div class="flex items-center gap-2">
                @if($tenant->license)
                    <button wire:click="startExtend(7)"  class="text-xs px-3 py-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700">+7 days</button>
                    <button wire:click="startExtend(30)" class="text-xs px-3 py-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700">+30 days</button>
                    @if($tenant->license->status === \App\Models\License::STATUS_SUSPENDED || $tenant->license->status === \App\Models\License::STATUS_CANCELLED)
                        <button wire:click="reactivate" class="text-xs px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white">Reactivate</button>
                    @else
                        <button wire:click="startSuspend" class="text-xs px-3 py-1.5 rounded bg-amber-600 hover:bg-amber-700 text-white">Suspend</button>
                        <button wire:click="startCancel"  class="text-xs px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700 text-white">Cancel</button>
                    @endif
                @endif
                <button wire:click="startIssue" class="text-xs px-3 py-1.5 rounded bg-brand-600 hover:bg-brand-700 text-white">{{ $tenant->license ? 'Re-issue' : 'Issue license' }}</button>
            </div>
        </div>

        @if($tenant->license)
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-xs text-slate-500">Key</dt><dd class="font-mono text-xs">{{ Str::mask($tenant->license->license_key, '•', 5, 14) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Issued</dt><dd>{{ $tenant->license->issued_at?->format('d M Y') }}</dd></div>
                <div><dt class="text-xs text-slate-500">Starts</dt><dd>{{ $tenant->license->starts_at?->format('d M Y') }}</dd></div>
                <div><dt class="text-xs text-slate-500">Last validated</dt><dd>{{ $tenant->license->last_validated_at?->diffForHumans() ?? '—' }}</dd></div>
            </dl>
            @if($tenant->license->suspended_reason)
                <div class="mt-3 text-xs text-rose-700">Suspended reason: {{ $tenant->license->suspended_reason }}</div>
            @endif
        @else
            <div class="text-sm text-slate-500">No license issued. Click <strong>Issue license</strong> to provision.</div>
        @endif
    </div>

    {{-- Action form --}}
    @if($showForm)
    <div class="bg-white rounded-xl border p-5 mb-6">
        <h3 class="font-semibold text-slate-900 mb-3">
            @switch($formMode)
                @case('issue')   {{ $tenant->license ? 'Re-issue license' : 'Issue license' }} @break
                @case('extend')  Extend license @break
                @case('suspend') Suspend license @break
                @case('cancel')  Cancel license @break
            @endswitch
        </h3>

        <div class="space-y-3 text-sm">
            @if($formMode === 'issue')
                @php
                    $selectedPlan = $plans->firstWhere('id', (int) $planId);
                    $isTrialPlan = $selectedPlan && $selectedPlan->isTrial();
                    $trialDaysOnPlan = (int) ($selectedPlan->trial_days ?? 14);
                @endphp
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Plan</label>
                    <select wire:model.live="planId" class="w-full px-3 py-2 border rounded-lg">
                        @foreach($plans as $p)
                            <option value="{{ $p->id }}">
                                {{ $p->name }}
                                @if($p->isTrial())
                                    — Trial ({{ $p->trial_days ?: 14 }} days)
                                @else
                                    — ₹{{ number_format($p->price_monthly) }}/mo
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('planId')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Billing cycle</label>
                    <select wire:model="billingCycle" class="w-full px-3 py-2 border rounded-lg">
                        @if($isTrialPlan)
                            <option value="trial">Trial ({{ $trialDaysOnPlan }} days)</option>
                        @endif
                        <option value="monthly">Monthly (30d)</option>
                        <option value="yearly">Yearly (365d)</option>
                        <option value="lifetime">Lifetime (50y)</option>
                    </select>
                    @if($isTrialPlan)
                        <p class="mt-1 text-xs text-amber-700">⚡ This is a trial plan — duration will be {{ $trialDaysOnPlan }} days regardless of cycle selection.</p>
                    @endif
                </div>
            @elseif($formMode === 'extend')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Days to extend</label>
                    <input type="number" wire:model="extendDays" min="1" max="365" class="w-full px-3 py-2 border rounded-lg">
                    @error('extendDays')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    {{ in_array($formMode, ['suspend','cancel']) ? 'Reason (required)' : 'Notes (optional)' }}
                </label>
                <textarea wire:model="reason" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                @error('reason')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button wire:click="save" class="px-4 py-2 rounded bg-brand-600 hover:bg-brand-700 text-white text-sm">Save</button>
                <button wire:click="cancelForm" type="button" class="px-4 py-2 rounded text-slate-600 hover:bg-slate-100 text-sm">Cancel</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Properties --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b font-semibold text-slate-900">Properties</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr><th class="px-4 py-2">Code</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">City</th><th class="px-4 py-2 text-right">Rooms</th><th class="px-4 py-2">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($properties as $p)
                    <tr><td class="px-4 py-2 font-mono text-xs">{{ $p->code }}</td><td class="px-4 py-2">{{ $p->name }}</td><td class="px-4 py-2 text-xs">{{ $p->city }}</td><td class="px-4 py-2 text-xs text-right">{{ $p->rooms_count }}</td><td class="px-4 py-2 text-xs">{{ $p->status }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No properties.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- License history --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-3 border-b font-semibold text-slate-900">License history</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr><th class="px-4 py-2">Issued</th><th class="px-4 py-2">Plan</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Expires</th><th class="px-4 py-2">By</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($licenseHistory as $l)
                    <tr>
                        <td class="px-4 py-2 text-xs">{{ $l->issued_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-2 text-xs">{{ $l->plan?->name }}</td>
                        <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $l->statusBadgeClass() }}">{{ $l->status }}</span></td>
                        <td class="px-4 py-2 text-xs">{{ $l->expires_at?->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-xs">{{ $l->issuedBy?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No history.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
