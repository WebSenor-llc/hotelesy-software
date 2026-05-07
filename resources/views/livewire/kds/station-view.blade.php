<div @if($autoRefresh) wire:poll.10s @endif>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Kitchen Display System</h1>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                <input type="checkbox" wire:model.live="autoRefresh" class="rounded">
                Auto-refresh ({{ $autoRefresh ? 'on' : 'off' }})
            </label>
            <select wire:model.live="stationId" class="px-3 py-1.5 border border-slate-300 rounded text-sm">
                @foreach($stations as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->type }})</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Active tickets — bump to advance status (queued → started → ready → served). Order status syncs automatically to POS.</p>

    @if(session('kds_success'))
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('kds_success') }}</div>
    @endif

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Queued</div>
            <div class="text-2xl font-bold text-slate-700">{{ $stats['queued'] }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Preparing</div>
            <div class="text-2xl font-bold text-amber-700">{{ $stats['started'] }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Ready to serve</div>
            <div class="text-2xl font-bold text-emerald-700">{{ $stats['ready'] }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Served today</div>
            <div class="text-2xl font-bold text-sky-700">{{ $stats['served_today'] }}</div>
        </div>
    </div>

    @if($tickets->isEmpty())
        <div class="bg-white rounded-xl border p-12 text-center mb-6">
            <div class="text-5xl mb-3">✓</div>
            <div class="font-semibold text-slate-900 mb-1">No active tickets</div>
            <div class="text-sm text-slate-500">All caught up. New orders will appear here.</div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            @foreach($tickets as $t)
                @php
                    $ageSec = (int) abs(now()->diffInSeconds($t->queued_at));
                    $color = $ageSec > 600 ? 'border-rose-500 bg-rose-50' : ($ageSec > 300 ? 'border-amber-500 bg-amber-50' : 'border-emerald-500 bg-emerald-50');
                    $statusBg = match($t->status) {
                        'queued' => 'bg-slate-200 text-slate-800',
                        'started' => 'bg-amber-200 text-amber-900',
                        'ready' => 'bg-emerald-200 text-emerald-900',
                        default => 'bg-slate-100',
                    };
                    $bumpLabel = match($t->status) {
                        'queued' => 'Start preparing →',
                        'started' => 'Mark ready →',
                        'ready' => 'Mark served →',
                        default => 'Bump →',
                    };
                @endphp
                <div class="bg-white border-l-4 {{ $color }} rounded-xl p-4 shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono font-bold">{{ $t->ticket_number }}</span>
                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $statusBg }}">{{ $t->status }}</span>
                    </div>
                    @if($t->order)
                        <div class="text-xs text-slate-700 mb-2">
                            Order <span class="font-mono">{{ $t->order->order_number }}</span>
                            @if($t->order->guest_name) · {{ $t->order->guest_name }}@endif
                            @if($t->order->room_number) · Room {{ $t->order->room_number }}@endif
                        </div>
                    @endif
                    <div class="text-xs text-slate-600 mb-3">
                        Queued {{ $t->queued_at->diffForHumans() }} · {{ floor($ageSec / 60) }}m old · Priority: {{ $t->priority }}
                    </div>
                    @if($t->special_instructions)
                        <div class="text-xs bg-yellow-50 border border-yellow-200 rounded px-2 py-1 mb-3">⚠ {{ $t->special_instructions }}</div>
                    @endif

                    {{-- Items list --}}
                    @if($t->order && $t->order->items->isNotEmpty())
                        <div class="text-xs text-slate-700 bg-slate-50 rounded p-2 mb-3 space-y-0.5">
                            @foreach($t->order->items as $item)
                                <div class="flex justify-between"><span>{{ $item->item_name }}</span><span class="font-bold">×{{ (int)$item->quantity }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    <button type="button" wire:click="bumpTicket({{ $t->id }})" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold py-2 rounded-lg">
                        <span wire:loading.remove wire:target="bumpTicket({{ $t->id }})">{{ $bumpLabel }}</span>
                        <span wire:loading wire:target="bumpTicket({{ $t->id }})">Bumping…</span>
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Recently completed today --}}
    @if($recentlyCompleted->isNotEmpty())
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold flex items-center justify-between">
                <span>Recently served (today)</span>
                <span class="text-xs text-slate-500 font-normal">Last {{ $recentlyCompleted->count() }} of {{ $stats['served_today'] }}</span>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                    <tr><th class="px-5 py-2">Ticket</th><th class="px-4 py-2">Order #</th><th class="px-4 py-2">Guest</th><th class="px-4 py-2">Served at</th><th class="px-4 py-2">Order status</th><th class="px-4 py-2 text-right"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($recentlyCompleted as $t)
                        @php
                            $st = ['open'=>'bg-slate-100','sent_to_kitchen'=>'bg-amber-100 text-amber-800','preparing'=>'bg-orange-100 text-orange-800','ready'=>'bg-emerald-100 text-emerald-800','served'=>'bg-sky-100 text-sky-800','billed'=>'bg-violet-100 text-violet-800','settled'=>'bg-emerald-100 text-emerald-800'][$t->order?->status ?? 'open'] ?? 'bg-slate-100';
                        @endphp
                        <tr>
                            <td class="px-5 py-2 font-mono text-xs">{{ $t->ticket_number }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $t->order?->order_number ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $t->order?->guest_name ?: '—' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $t->served_at?->format('H:i') }} ({{ $t->served_at?->diffForHumans() }})</td>
                            <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $st }}">{{ str_replace('_',' ', $t->order?->status ?? '—') }}</span></td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" wire:click="recallTicket({{ $t->id }})" class="text-xs text-amber-700 hover:underline">Recall</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
