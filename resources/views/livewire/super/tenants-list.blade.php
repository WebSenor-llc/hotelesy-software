<div>
    <div class="flex items-baseline justify-between mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Tenants</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('super.dashboard') }}" class="text-sm text-slate-600">← Super dashboard</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[200px]">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, slug, or owner email…" class="w-full px-3 py-2 border rounded-lg text-sm">
        </div>
        <select wire:model.live="statusFilter" class="px-3 py-2 border rounded-lg text-sm">
            @foreach($statuses as $s)
                <option value="{{ $s }}">{{ $s === 'all' ? 'All statuses' : ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
        <button type="button" wire:click="clearFilters" class="text-xs text-slate-600 px-3 py-2">Clear</button>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2">Tenant</th>
                    <th class="px-4 py-2">Owner</th>
                    <th class="px-4 py-2">Plan</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Expires</th>
                    <th class="px-4 py-2 text-right">Days left</th>
                    <th class="px-4 py-2 text-right">Props</th>
                    <th class="px-4 py-2 text-right">Rooms</th>
                    <th class="px-4 py-2 text-right">Users</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($tenants as $t)
                    <tr class="hover:bg-slate-50 cursor-pointer" wire:key="ten-{{ $t->id }}">
                        <td class="px-5 py-2">
                            <a href="{{ route('super.tenants.show', $t->id) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $t->name }}</a>
                            <div class="text-xs text-slate-500 font-mono">{{ $t->slug }}</div>
                        </td>
                        <td class="px-4 py-2 text-xs">{{ $t->owner_email }}</td>
                        <td class="px-4 py-2 text-xs">{{ $t->license?->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if($t->license)
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $t->license->statusBadgeClass() }}">{{ $t->license->status }}</span>
                            @else
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-slate-100 text-slate-600">none</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-right">{{ $t->license?->expires_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs text-right font-semibold {{ ($t->license?->daysRemaining() ?? 0) <= 7 ? 'text-rose-600' : 'text-slate-700' }}">
                            {{ $t->license ? $t->license->daysRemaining() . 'd' : '—' }}
                        </td>
                        <td class="px-4 py-2 text-xs text-right">{{ $t->properties_count }}</td>
                        <td class="px-4 py-2 text-xs text-right">{{ $roomCounts[$t->id] ?? 0 }}</td>
                        <td class="px-4 py-2 text-xs text-right">{{ $userCounts[$t->id] ?? 0 }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('super.tenants.show', $t->id) }}" class="text-xs text-brand-600 hover:underline">Manage →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-5 py-12 text-center text-sm text-slate-500">No tenants match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tenants->links() }}</div>
</div>
