<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Rate calendar</h1>
        <div class="flex items-center gap-2 text-sm">
            <button type="button" wire:click="startBulk" class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded font-semibold">Bulk update</button>
            <button type="button" wire:click="shift(-7)" class="px-3 py-1.5 border border-slate-300 rounded">← Prev</button>
            <input type="date" wire:model.live="startDate" class="px-3 py-1.5 border border-slate-300 rounded">
            <button type="button" wire:click="shift(7)" class="px-3 py-1.5 border border-slate-300 rounded">Next →</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Set room rates and inventory by date. Click any cell to edit, or use Bulk update to set rates for a range. {{ $start->format('d M') }} – {{ $end->format('d M Y') }}</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    @if($showBulk)
        <form wire:submit.prevent="applyBulk" class="bg-brand-50 border border-brand-200 rounded-xl p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-brand-900">Bulk rate update</h2>
                <button type="button" wire:click="cancelBulk" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-2">Room types *</label>
                    <div class="bg-white border border-slate-300 rounded-lg p-3 max-h-48 overflow-y-auto space-y-1">
                        @foreach($roomTypes as $rt)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="bulkRoomTypeIds" value="{{ $rt->id }}" class="rounded">
                                <span>{{ $rt->name }} <span class="text-xs text-slate-500">({{ $rt->code }})</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('bulkRoomTypeIds')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Start date *</label>
                        <input type="date" wire:model="bulkStart" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('bulkStart')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">End date *</label>
                        <input type="date" wire:model="bulkEnd" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('bulkEnd')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">New rate (₹) *</label>
                        <input type="number" step="0.01" wire:model="bulkRate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="3500">
                        @error('bulkRate')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="bulkApplyWeekend" class="rounded">
                            Apply weekend modifier (Sat/Sun)
                        </label>
                        @if($bulkApplyWeekend)
                            <div class="mt-1 flex items-center gap-2">
                                <span class="text-xs text-slate-600">Weekend %</span>
                                <input type="number" step="0.5" wire:model="bulkWeekendPct" class="w-24 px-2 py-1 border border-slate-300 rounded text-sm text-right">
                                <span class="text-xs text-slate-500">e.g. 20 = +20% on Sat/Sun</span>
                            </div>
                        @endif
                    </div>
                    <div class="col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="bulkStopSell" class="rounded">
                            Stop-sell flag (block these days from being sold)
                        </label>
                    </div>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-brand-200 flex justify-end gap-2">
                <button type="button" wire:click="cancelBulk" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Apply</button>
            </div>
        </form>
    @endif

    @if($selectedDate)
        <div class="bg-brand-50 border border-brand-200 rounded-xl p-5 mb-4">
            <div class="text-sm font-semibold text-brand-900 mb-3">Edit rate · {{ \Carbon\Carbon::parse($selectedDate)->format('D, d M Y') }}</div>
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Rate (₹)</label>
                    <input type="number" step="0.01" wire:model="editRate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-700">
                        <input type="checkbox" wire:model="editStopSell" class="rounded border-slate-300"> Stop sell
                    </label>
                </div>
                <button type="button" wire:click="saveRate" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Save</button>
                <button type="button" wire:click="$set('selectedDate', null)" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-900">Cancel</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b sticky top-0">
                <tr>
                    <th class="px-3 py-3 text-left font-semibold text-slate-700 sticky left-0 bg-slate-50 min-w-48">Room type / Rate plan</th>
                    @foreach($dates as $d)
                        <th class="px-2 py-3 text-center min-w-20 {{ $d->isToday() ? 'bg-brand-50 text-brand-700' : 'text-slate-700' }}">
                            <div class="font-bold">{{ $d->format('d') }}</div>
                            <div class="text-[10px] text-slate-500 font-normal">{{ $d->format('D M') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($roomTypes as $rt)
                    @php $total = $totalByType[$rt->id] ?? 0; @endphp
                    <tr class="bg-slate-50 border-y border-slate-200">
                        <td class="px-3 py-2 sticky left-0 bg-slate-50">
                            <div class="font-bold text-slate-900">{{ $rt->name }}</div>
                            <div class="text-xs text-slate-500">{{ $total }} rooms</div>
                        </td>
                        @foreach($dates as $d)
                            @php
                                $key = $rt->id.'|'.$d->toDateString();
                                $occ = $sold[$key] ?? 0;
                                $avail = max(0, $total - $occ);
                                $bg = $avail === 0 ? 'bg-rose-100' : ($avail < $total/3 ? 'bg-amber-100' : 'bg-emerald-50');
                            @endphp
                            <td class="px-2 py-2 text-center {{ $bg }}">
                                <div class="text-xs font-bold">{{ $avail }}</div>
                                <div class="text-[10px] text-slate-500">avail</div>
                            </td>
                        @endforeach
                    </tr>
                    @foreach($ratePlans[$rt->id] ?? [] as $rp)
                        <tr>
                            <td class="px-3 py-2 sticky left-0 bg-white border-r border-slate-100">
                                <div class="text-sm font-medium text-slate-700">↳ {{ $rp->name }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">{{ $rp->code }} · {{ $rp->meal_plan }}</div>
                            </td>
                            @foreach($dates as $d)
                                @php
                                    $rkey = $rt->id.'|'.$rp->id.'|'.$d->toDateString();
                                    $row = $rates[$rkey] ?? null;
                                    $rate = $row?->rate ?? $rp->base_rate;
                                    $isStopSell = $row?->stop_sell ?? false;
                                    $isSelected = $selectedRoomTypeId === $rt->id && $selectedRatePlanId === $rp->id && $selectedDate === $d->toDateString();
                                @endphp
                                <td class="px-1 py-1 text-center">
                                    <button type="button" wire:click="pickCell({{ $rt->id }}, {{ $rp->id }}, '{{ $d->toDateString() }}', {{ $rate }})"
                                        class="w-full px-2 py-1.5 rounded text-xs hover:ring-2 hover:ring-brand-300 transition
                                        {{ $isStopSell ? 'bg-rose-50 text-rose-700 line-through' : ($row ? 'bg-brand-50 text-brand-700' : 'bg-white text-slate-700') }}
                                        {{ $isSelected ? 'ring-2 ring-brand-500' : 'border border-slate-200' }}">
                                        ₹{{ number_format($rate, 0) }}
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex gap-4 text-xs text-slate-600 flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-50 border border-emerald-200"></span>Available</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-100 border border-amber-200"></span>Tight (&lt;33% available)</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-100 border border-rose-200"></span>Sold out</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-brand-50 border border-brand-200"></span>Custom rate set</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-50 border border-rose-200"></span>Stop sell</span>
    </div>
</div>
