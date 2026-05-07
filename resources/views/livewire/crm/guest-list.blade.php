<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">Guest CRM</h1>
        <div class="flex gap-2">
            <button type="button" wire:click="toggleDuplicates" class="border border-slate-300 hover:bg-slate-50 font-medium px-4 py-2 rounded-lg text-sm">
                {{ $showDuplicates ? 'Hide duplicates' : 'Find duplicates' }}
            </button>
            <button type="button" wire:click="openAdd" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">+ Add guest</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Profiles, stay history, preferences, loyalty tiers, lifetime value.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    @if($showDuplicates)
        <div class="bg-white rounded-xl border p-5 mb-6">
            <div class="flex items-baseline justify-between mb-3">
                <h2 class="font-semibold text-slate-900">Duplicate guests</h2>
                <span class="text-xs text-slate-500">{{ count($duplicateGroups) }} group(s) — matching by lowercased email OR normalised phone</span>
            </div>
            @if(count($duplicateGroups) === 0)
                <div class="text-sm text-slate-500 py-4 text-center">No duplicate guests detected.</div>
            @else
                <div class="space-y-4">
                    @foreach($duplicateGroups as $group)
                        @php $primaryId = $primaryChoice[$group['key']] ?? ($group['guests'][0]->id ?? null); @endphp
                        <div class="border border-slate-200 rounded-lg overflow-hidden">
                            <div class="bg-slate-50 px-4 py-2 border-b text-xs flex items-center gap-2">
                                <span class="font-mono uppercase tracking-wider text-slate-500">{{ $group['match_type'] }}</span>
                                <span class="font-semibold">{{ $group['match_value'] }}</span>
                                <span class="text-slate-400">· {{ count($group['guests']) }} guests</span>
                            </div>
                            <table class="w-full text-sm">
                                <thead class="bg-white text-left text-[10px] uppercase tracking-wider text-slate-500 border-b">
                                    <tr>
                                        <th class="px-3 py-2 w-8">Primary</th>
                                        <th class="px-3 py-2">Guest</th>
                                        <th class="px-3 py-2">Email</th>
                                        <th class="px-3 py-2">Phone</th>
                                        <th class="px-3 py-2 text-right">Visits</th>
                                        <th class="px-3 py-2 text-right">Lifetime ₹</th>
                                        <th class="px-3 py-2">Last stay</th>
                                        <th class="px-3 py-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($group['guests'] as $cand)
                                        <tr class="{{ $primaryId === $cand->id ? 'bg-emerald-50' : '' }}">
                                            <td class="px-3 py-2"><input type="radio" wire:model.live="primaryChoice.{{ $group['key'] }}" value="{{ $cand->id }}" {{ $primaryId === $cand->id ? 'checked' : '' }}></td>
                                            <td class="px-3 py-2"><a href="{{ route('crm.guest.show', $cand) }}" class="text-brand-600 hover:underline">{{ trim(($cand->first_name ?? '').' '.($cand->last_name ?? '')) }}</a><div class="text-[10px] text-slate-500 font-mono">#{{ $cand->id }}</div></td>
                                            <td class="px-3 py-2 text-xs">{{ $cand->email ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs">{{ $cand->phone ?: '—' }}</td>
                                            <td class="px-3 py-2 text-right text-xs">{{ $cand->total_visits ?? 0 }}</td>
                                            <td class="px-3 py-2 text-right text-xs">₹{{ number_format($cand->lifetime_spend ?? 0, 0) }}</td>
                                            <td class="px-3 py-2 text-xs text-slate-600">{{ $cand->last_stay_date ? \Carbon\Carbon::parse($cand->last_stay_date)->format('d M Y') : '—' }}</td>
                                            <td class="px-3 py-2 text-right text-xs">
                                                @if($primaryId !== $cand->id)
                                                    <button type="button"
                                                        wire:click="mergeDuplicates({{ $primaryId }}, {{ $cand->id }})"
                                                        wire:confirm="Merge guest #{{ $cand->id }} into #{{ $primaryId }}? This moves all reservations / banquet bookings / payments / notes to the primary and soft-deletes the secondary."
                                                        class="bg-brand-600 hover:bg-brand-700 text-white text-[10px] uppercase tracking-wider font-semibold px-2 py-1 rounded">
                                                        Merge into primary
                                                    </button>
                                                @else
                                                    <span class="text-[10px] uppercase tracking-wider text-emerald-700 bg-emerald-100 px-2 py-1 rounded">Primary</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Total guests</div><div class="text-2xl font-bold">{{ $stats['total'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Loyalty members</div><div class="text-2xl font-bold text-amber-600">{{ $stats['loyalty'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Corporate</div><div class="text-2xl font-bold text-indigo-600">{{ $stats['corporate'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Lifetime spend</div><div class="text-2xl font-bold">₹{{ number_format($stats['lifetime_spend'], 0) }}</div></div>
    </div>

    <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name / phone / email…" class="flex-1 min-w-64 px-3 py-2 border rounded-lg text-sm">
        <select wire:model.live="segment" class="px-3 py-2 border rounded-lg text-sm">
            <option value="">All segments</option><option value="walk_in">Walk-in</option><option value="leisure">Leisure</option><option value="corporate">Corporate</option><option value="ota">OTA</option><option value="group">Group</option><option value="vip">VIP</option>
        </select>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr><th class="px-5 py-2.5">Guest</th><th class="px-4 py-2.5">Phone</th><th class="px-4 py-2.5">Segment</th><th class="px-4 py-2.5">Loyalty</th><th class="px-4 py-2.5 text-right">Visits</th><th class="px-4 py-2.5 text-right">Lifetime spend</th><th class="px-4 py-2.5">Last stay</th><th class="px-4 py-2.5"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($guests as $g)
                    @php $resCount = (int) ($reservationCounts[$g->id] ?? 0); @endphp
                    <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('crm.guest.show', $g) }}'">
                        <td class="px-5 py-2.5">
                            <div class="font-medium">{{ trim($g->first_name.' '.$g->last_name) }}@if($g->is_blacklisted)<span class="ml-2 text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Blacklisted</span>@endif</div>
                            <div class="text-xs text-slate-500">{{ $g->email ?: '—' }}</div>
                        </td>
                        <td class="px-4 py-2.5">{{ $g->phone }}</td>
                        <td class="px-4 py-2.5"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100">{{ $g->segment ?: '—' }}</span></td>
                        <td class="px-4 py-2.5 text-xs">{{ $g->loyalty_tier ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $g->total_visits ?? 0 }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($g->lifetime_spend ?? 0, 0) }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ $g->last_stay_date ? \Carbon\Carbon::parse($g->last_stay_date)->format('d M Y') : '—' }}</td>
                        <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation();">
                            @if($resCount === 0)
                                <button type="button"
                                    wire:click.stop="deleteGuest({{ $g->id }})"
                                    wire:confirm="Delete guest '{{ trim($g->first_name.' '.$g->last_name) }}'? This is a soft-delete."
                                    class="text-xs text-rose-600 hover:underline">Delete</button>
                            @else
                                <span class="text-[10px] text-slate-400" title="Has {{ $resCount }} reservation(s)">Has reservations — cannot delete</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No guests yet. <button type="button" wire:click="openAdd" class="text-brand-600 font-medium">Add the first one →</button></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $guests->links() }}</div>

    {{-- Add Guest Modal --}}
    @if($showAddModal)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-4 overflow-y-auto" wire:click.self="closeAdd">
            <form wire:submit.prevent="saveGuest" class="bg-white rounded-xl border w-full max-w-3xl my-8 shadow-xl">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="font-semibold text-lg">New guest</h2>
                    <button type="button" wire:click="closeAdd" class="text-slate-500 hover:text-slate-700 text-xl leading-none">&times;</button>
                </div>
                <div class="p-6 grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Salutation</label>
                        <select wire:model="salutation" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option><option value="Mr">Mr</option><option value="Mrs">Mrs</option><option value="Ms">Ms</option><option value="Dr">Dr</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">First name *</label>
                        <input type="text" wire:model="first_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('first_name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Last name</label>
                        <input type="text" wire:model="last_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Gender</label>
                        <select wire:model="gender" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option><option value="M">Male</option><option value="F">Female</option><option value="O">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Date of birth</label>
                        <input type="date" wire:model="dob" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" wire:model="phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" wire:model="email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('email')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Country (ISO 2)</label>
                        <input type="text" wire:model="country" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Address</label>
                        <input type="text" wire:model="address" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">City</label>
                        <input type="text" wire:model="city" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">ID proof type</label>
                        <select wire:model="id_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option>
                            <option value="aadhaar">Aadhaar</option>
                            <option value="pan">PAN</option>
                            <option value="passport">Passport</option>
                            <option value="driving_license">Driving licence</option>
                            <option value="voter_id">Voter ID</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">ID proof number</label>
                        <input type="text" wire:model="id_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Segment</label>
                        <select wire:model="new_segment" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option><option value="walk_in">Walk-in</option><option value="leisure">Leisure</option><option value="corporate">Corporate</option><option value="ota">OTA</option><option value="group">Group</option><option value="vip">VIP</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 flex items-end gap-6">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="vip" class="rounded">VIP</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_blacklisted" class="rounded">Blacklist</label>
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-slate-50 flex justify-end gap-2">
                    <button type="button" wire:click="closeAdd" class="px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Save guest</button>
                </div>
            </form>
        </div>
    @endif
</div>
