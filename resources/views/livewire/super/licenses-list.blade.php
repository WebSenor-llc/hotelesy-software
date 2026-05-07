<div>
    <div class="flex items-baseline justify-between mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Licenses</h1>
        <a href="{{ route('super.dashboard') }}" class="text-sm text-slate-600">&larr; Super dashboard</a>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[200px]">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by key, tenant name, slug, or owner email…" class="w-full px-3 py-2 border rounded-lg text-sm">
        </div>
        <select wire:model.live="statusFilter" class="px-3 py-2 border rounded-lg text-sm">
            @foreach($statuses as $s)
                <option value="{{ $s }}">{{ $s === 'all' ? 'All statuses' : ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
        <select wire:model.live="planFilter" class="px-3 py-2 border rounded-lg text-sm">
            <option value="all">All plans</option>
            @foreach($plans as $p)
                <option value="{{ $p->code }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <button type="button" wire:click="clearFilters" class="text-xs text-slate-600 px-3 py-2">Clear</button>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2">Tenant</th>
                    <th class="px-4 py-2">Key</th>
                    <th class="px-4 py-2">Plan</th>
                    <th class="px-4 py-2">Cycle</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Issued</th>
                    <th class="px-4 py-2">Expires</th>
                    <th class="px-4 py-2 text-right">Days left</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($licenses as $l)
                    <tr class="hover:bg-slate-50" wire:key="lic-{{ $l->id }}">
                        <td class="px-5 py-2">
                            <div class="font-semibold text-slate-900">{{ $l->tenant?->name ?? '—' }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ $l->tenant?->slug }}</div>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">{{ Str::mask($l->license_key, '•', 5, 14) }}</td>
                        <td class="px-4 py-2 text-xs">{{ $l->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs">{{ $l->billing_cycle }}</td>
                        <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $l->statusBadgeClass() }}">{{ $l->status }}</span></td>
                        <td class="px-4 py-2 text-xs">{{ $l->issued_at?->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-xs">{{ $l->expires_at?->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-xs text-right font-semibold {{ $l->daysRemaining() <= 7 ? 'text-rose-600' : 'text-slate-700' }}">{{ $l->daysRemaining() }}d</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('super.tenants.show', $l->tenant_id) }}" class="text-xs text-brand-600 hover:underline">Manage &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">No licenses match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $licenses->links() }}</div>
</div>
