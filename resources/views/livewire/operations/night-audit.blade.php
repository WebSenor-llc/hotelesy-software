<div>
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Night Audit</h1>
    <p class="text-sm text-slate-600 mb-6">Close the business day. Posts nightly room charges, marks no-shows, rolls the business date forward, and writes a snapshot to the audit log.</p>

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200">{{ session('success') }}</div>@endif

    {{-- Current state --}}
    <div class="bg-white rounded-xl border p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">Business date</div>
                <div class="text-2xl font-bold">{{ $bizDate->format('d M Y') }}</div>
                <div class="text-xs text-slate-500">{{ $bizDate->format('l') }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">In-house guests</div>
                <div class="text-2xl font-bold text-emerald-700">{{ $inHouse }}</div>
                <div class="text-xs text-slate-500">Will be charged for tonight</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">Pending arrivals</div>
                <div class="text-2xl font-bold text-amber-600">{{ $expectedNoShows }}</div>
                <div class="text-xs text-slate-500">Will be marked no-show</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">Pending departures</div>
                <div class="text-2xl font-bold text-rose-600">{{ $expectedDepartures }}</div>
                <div class="text-xs text-slate-500">Should be checked out before audit</div>
            </div>
        </div>
    </div>

    {{-- Preview + run --}}
    <div class="bg-white rounded-xl border border-amber-300 p-6 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-2">Run night audit for {{ $bizDate->format('d M Y') }}</h2>
        <p class="text-sm text-slate-600 mb-4">
            This action is irreversible. The business date will roll to <strong>{{ $bizDate->copy()->addDay()->format('d M Y') }}</strong>.
            Use <em>Preview</em> first to see exactly what will be posted.
        </p>
        <div class="mb-3">
            <label class="block text-xs font-medium text-slate-700 mb-1">Audit notes (optional)</label>
            <textarea wire:model="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Any operational notes for the day…"></textarea>
        </div>

        @if($previewMode && !empty($previewSummary))
            <div class="mb-4 p-4 rounded-lg border border-sky-300 bg-sky-50">
                <div class="font-semibold text-sky-900 mb-2">Dry-run preview</div>
                <ul class="text-sm text-sky-900 list-disc pl-5 space-y-1">
                    <li>Would post <strong>{{ $previewSummary['rooms_to_post'] }}</strong> room rent charges across <strong>{{ $previewSummary['reservations_to_charge'] }}</strong> reservations totalling <strong>₹{{ number_format($previewSummary['total_room_charges'], 2) }}</strong> (+ tax ₹{{ number_format($previewSummary['total_tax'], 2) }})</li>
                    <li>Would mark <strong>{{ $previewSummary['no_shows_to_mark'] }}</strong> reservations as no-show</li>
                    <li>Would roll business date from <strong>{{ $previewSummary['business_date'] }}</strong> to <strong>{{ $previewSummary['next_business_date'] }}</strong></li>
                </ul>
                @if(!empty($previewSummary['lines']))
                    <details class="mt-3">
                        <summary class="text-xs text-sky-700 cursor-pointer">Show {{ count($previewSummary['lines']) }} line item(s)</summary>
                        <table class="w-full text-xs mt-2 bg-white rounded border">
                            <thead class="bg-slate-50 border-b text-left text-[10px] uppercase tracking-wider text-slate-500">
                                <tr><th class="px-3 py-1.5">Reservation</th><th class="px-3 py-1.5">Room type</th><th class="px-3 py-1.5 text-right">Rate</th><th class="px-3 py-1.5 text-right">Tax</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($previewSummary['lines'] as $l)
                                    <tr><td class="px-3 py-1 font-mono">{{ $l['reservation'] }}</td><td class="px-3 py-1">{{ $l['room_type'] }}</td><td class="px-3 py-1 text-right">₹{{ number_format($l['rate'], 2) }}</td><td class="px-3 py-1 text-right">₹{{ number_format($l['tax'], 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
                <div class="flex gap-3 items-center mt-4">
                    <button type="button" wire:click="runAudit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-5 py-2.5 rounded-lg">
                        <span wire:loading.remove wire:target="runAudit">Confirm and run</span>
                        <span wire:loading wire:target="runAudit">Processing…</span>
                    </button>
                    <button type="button" wire:click="cancelPreview" class="text-sm text-slate-600 hover:text-slate-900">Discard preview</button>
                </div>
            </div>
        @else
            <label class="flex items-center gap-2 text-xs text-slate-600 mb-3">
                <input type="checkbox" wire:model.live="skipPreview" class="rounded">
                Skip preview (post directly)
            </label>
            @if($confirmRun)
                <div class="flex gap-3 items-center">
                    <button type="button" wire:click="runAudit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-5 py-2.5 rounded-lg">
                        <span wire:loading.remove wire:target="runAudit">Yes, run audit now</span>
                        <span wire:loading wire:target="runAudit">Processing…</span>
                    </button>
                    <button type="button" wire:click="$set('confirmRun', false)" class="text-sm text-slate-600 hover:text-slate-900">Cancel</button>
                </div>
            @elseif($skipPreview)
                <button type="button" wire:click="$set('confirmRun', true)" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">
                    Begin night audit (skip preview)
                </button>
            @else
                <button type="button" wire:click="previewAudit" class="bg-sky-600 hover:bg-sky-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">
                    <span wire:loading.remove wire:target="previewAudit">Preview audit</span>
                    <span wire:loading wire:target="previewAudit">Calculating…</span>
                </button>
            @endif
        @endif
    </div>

    {{-- Previous audit history --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Recent audits</div>
        @if($previousAudits->count())
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                    <tr>
                        <th class="px-5 py-2 font-semibold">Business date</th>
                        <th class="px-4 py-2 font-semibold">Status</th>
                        <th class="px-4 py-2 text-right font-semibold">Reservations</th>
                        <th class="px-4 py-2 text-right font-semibold">Room rev.</th>
                        <th class="px-4 py-2 text-right font-semibold">No-shows</th>
                        <th class="px-4 py-2 text-right font-semibold">Occ %</th>
                        <th class="px-4 py-2 text-right font-semibold">ARR</th>
                        <th class="px-4 py-2 text-right font-semibold">RevPAR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($previousAudits as $a)
                        <tr>
                            <td class="px-5 py-2 font-medium">{{ \Carbon\Carbon::parse($a->business_date)->format('d M Y') }}</td>
                            <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">{{ $a->status }}</span></td>
                            <td class="px-4 py-2 text-right">{{ $a->reservations_processed }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($a->total_room_revenue, 0) }}</td>
                            <td class="px-4 py-2 text-right">{{ $a->no_shows_marked }}</td>
                            <td class="px-4 py-2 text-right">{{ $a->occupancy_percent }}%</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($a->arr, 0) }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($a->revpar, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="px-5 py-8 text-center text-sm text-slate-500">No audits yet. Today will be the first.</div>
        @endif
    </div>
</div>
