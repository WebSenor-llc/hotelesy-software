<div>
    <div class="flex items-start justify-between mb-1">
        <div>
            <h1 class="text-2xl font-bold">Channel Manager</h1>
            <p class="text-sm text-slate-600 mb-6">OTA mappings, rate &amp; inventory push, booking pull, parity audit. AxisRooms / STAAH / SiteMinder / RateGain pluggable.</p>
        </div>
        <button type="button" wire:click="startNewMapping" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add mapping</button>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded">{{ session('error') }}</div>@endif

    @if($showMappingForm)
        <div class="bg-white rounded-xl border p-5 mb-6">
            <div class="font-semibold mb-3">New OTA mapping</div>
            <div class="grid md:grid-cols-2 gap-3">
                <label class="text-xs font-medium">Channel
                    <select wire:model="m_channel" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">Select…</option>
                        @foreach(['axisrooms','staah','siteminder','booking_com','mmt','agoda','expedia','goibibo'] as $ch)
                            <option value="{{ $ch }}">{{ strtoupper($ch) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium">Room type
                    <select wire:model="m_room_type_id" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">Select…</option>
                        @foreach($roomTypes as $rt)<option value="{{ $rt->id }}">{{ $rt->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs font-medium">OTA room code
                    <input type="text" wire:model="m_external_room_type_id" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm" placeholder="e.g. DLX-KING-OTA">
                </label>
                <label class="text-xs font-medium">Rate plan
                    <select wire:model="m_rate_plan_id" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">Select…</option>
                        @foreach($ratePlans as $rp)<option value="{{ $rp->id }}">{{ $rp->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium">OTA rate plan code
                    <input type="text" wire:model="m_external_rate_plan_id" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm" placeholder="e.g. BAR-NREF">
                </label>
                <label class="text-xs font-medium flex items-center gap-2">
                    <input type="checkbox" wire:model="m_is_active"> Active
                </label>
            </div>
            <div class="flex gap-3 mt-4 pt-3 border-t">
                <button type="button" wire:click="saveMapping" class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save mapping</button>
                <button type="button" wire:click="cancelMapping" class="border border-slate-300 px-5 py-2 rounded-lg text-sm">Cancel</button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Active channels</div><div class="text-2xl font-bold">{{ $stats['channels_active'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Mappings</div><div class="text-2xl font-bold">{{ $stats['mappings'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Recent pushes</div><div class="text-2xl font-bold text-sky-700">{{ $stats['recent_pushes'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Recent pulls</div><div class="text-2xl font-bold text-emerald-700">{{ $stats['recent_pulls'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Errors</div><div class="text-2xl font-bold text-rose-600">{{ $stats['errors'] }}</div></div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        @forelse($channels as $c)
            <div class="bg-white rounded-xl border p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-semibold text-lg uppercase">{{ $c['channel'] }}</div>
                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $c['errors'] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $c['errors'] > 0 ? $c['errors'].' errors' : 'OK' }}</span>
                </div>
                <div class="text-sm space-y-1">
                    <div class="flex justify-between"><span class="text-slate-600">Mappings</span><span class="font-bold">{{ $c['mappings'] }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-600">Last sync</span><span class="text-xs">{{ $c['last_sync'] ? \Carbon\Carbon::parse($c['last_sync'])->diffForHumans() : 'Never' }}</span></div>
                </div>
                <button type="button" wire:click="pushChannel('{{ $c['channel'] }}')" wire:loading.attr="disabled" wire:target="pushChannel" class="mt-3 w-full text-sm bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white px-3 py-1.5 rounded">
                    <span wire:loading.remove wire:target="pushChannel('{{ $c['channel'] }}')">Push rates &amp; inventory</span>
                    <span wire:loading wire:target="pushChannel('{{ $c['channel'] }}')">Pushing…</span>
                </button>
            </div>
        @empty
            <div class="lg:col-span-3 bg-white rounded-xl border p-12 text-center text-sm text-slate-500">No channels configured. Connect AxisRooms / STAAH from Settings → Integrations.</div>
        @endforelse
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="border-b flex">
            @foreach(['mappings'=>'OTA mappings','log'=>'Sync log'] as $k=>$l)
                <button type="button" wire:click="$set('tab','{{ $k }}')" class="px-5 py-3 text-sm font-medium border-b-2 transition {{ $tab === $k ? 'border-brand-600 text-brand-700' : 'border-transparent' }}">{{ $l }}</button>
            @endforeach
        </div>

        @if($tab === 'mappings')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Channel</th><th class="px-4 py-2">Room type</th><th class="px-4 py-2">OTA room code</th><th class="px-4 py-2">Rate plan</th><th class="px-4 py-2">OTA rate code</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Last synced</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($mappings as $m)
                        <tr>
                            <td class="px-5 py-2 uppercase font-medium">{{ $m->channel }}</td>
                            <td class="px-4 py-2 text-xs">{{ $m->roomType?->name ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $m->external_room_type_id ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $m->ratePlan?->name ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $m->external_rate_plan_id ?? '—' }}</td>
                            <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $m->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $m->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="px-4 py-2 text-xs text-slate-600">{{ $m->last_sync_at?->diffForHumans() ?? 'Never' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">No OTA mappings configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Time</th><th class="px-4 py-2">Channel</th><th class="px-4 py-2">Direction</th><th class="px-4 py-2">Operation</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Records</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $l)
                        <tr><td class="px-5 py-2 text-xs text-slate-600">{{ $l->created_at->format('d M H:i:s') }}</td><td class="px-4 py-2 uppercase">{{ $l->channel }}</td><td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $l->direction === 'push' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $l->direction }}</span></td><td class="px-4 py-2 text-xs">{{ $l->operation }}</td><td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $l->status === 'success' ? 'bg-emerald-100 text-emerald-700' : ($l->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100') }}">{{ $l->status }}</span></td><td class="px-4 py-2 text-xs">{{ $l->records_processed ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No sync activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
