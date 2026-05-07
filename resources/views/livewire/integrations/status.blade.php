<div>
    <h1 class="text-2xl font-bold mb-1">Integrations</h1>
    <p class="text-sm text-slate-600 mb-6">Channel manager, payment gateway, door locks, ID scanner, WhatsApp/SMS, accounting export, reviews, kiosk, GDS.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    @if($activeEntry)
        <form wire:submit.prevent="saveConfigure" class="bg-white rounded-xl border border-brand-200 p-6 grid md:grid-cols-2 gap-4 mb-8">
            <div class="md:col-span-2 flex items-baseline justify-between border-b pb-2 mb-2">
                <h2 class="font-semibold">Configure {{ $activeEntry['name'] }}</h2>
                <span class="text-xs text-slate-500">{{ $activeEntry['group'] }}</span>
            </div>
            @foreach($activeEntry['fields'] as $f)
                <div class="{{ in_array($f['type'], ['url']) ? 'md:col-span-2' : '' }}">
                    <label class="block text-xs font-medium mb-1">{{ $f['label'] }}</label>
                    <input
                        type="{{ $f['type'] === 'password' ? 'password' : ($f['type'] === 'url' ? 'url' : 'text') }}"
                        wire:model="configFields.{{ $f['key'] }}"
                        class="w-full px-3 py-2 border rounded-lg text-sm font-mono"
                        autocomplete="off"
                    >
                </div>
            @endforeach
            <label class="md:col-span-2 flex items-center gap-2 text-sm pt-3 border-t">
                <input type="checkbox" wire:model="configIsActive" class="rounded">
                Mark this integration as active
            </label>
            <div class="md:col-span-2 flex justify-end gap-2 pt-3 border-t">
                <button type="button" wire:click="cancelConfigure" class="px-4 py-2 text-sm">Cancel</button>
                <button type="button" wire:click="testConnection('{{ $configureKey }}')" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg text-sm font-semibold">Test connection</button>
                <button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button>
            </div>
        </form>
    @endif

    @foreach($byGroup as $group => $items)
        <div class="mb-6">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">{{ $group }}</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($items as $i)
                    <div class="bg-white rounded-xl border p-4">
                        <div class="flex items-start justify-between mb-1.5">
                            <div class="font-semibold">{{ $i['name'] }}</div>
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $i['configured'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $i['configured'] ? 'Configured' : 'Not configured' }}</span>
                        </div>
                        <div class="text-xs text-slate-500 mb-3">{{ $i['desc'] }}</div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="startConfigure('{{ $i['key'] }}')" class="text-xs px-2.5 py-1 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded font-semibold">Configure</button>
                            <button type="button" wire:click="testConnection('{{ $i['key'] }}')" class="text-xs px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded font-semibold">Test</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="bg-white rounded-xl border overflow-hidden mt-8">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold">Recent integration calls</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Time</th><th class="px-4 py-2">Integration</th><th class="px-4 py-2">Operation</th><th class="px-4 py-2">Reference</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">Duration</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $l)
                    <tr><td class="px-5 py-2 text-xs text-slate-600">{{ \Carbon\Carbon::parse($l->called_at)->format('d M H:i:s') }}</td><td class="px-4 py-2 text-xs uppercase">{{ $l->integration }}</td><td class="px-4 py-2 text-xs">{{ $l->operation }}</td><td class="px-4 py-2 text-xs">{{ $l->reference_type }} #{{ $l->reference_id }}</td><td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $l->success ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $l->success ? 'OK' : 'Failed' }}</span></td><td class="px-4 py-2 text-right text-xs">{{ $l->duration_ms }}ms</td></tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No integration calls yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
