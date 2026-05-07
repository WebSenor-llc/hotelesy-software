<div x-data @print-handover.window="setTimeout(() => window.print(), 350)">
    @verbatim
    <style>
        @media print {
            aside, header, nav, .no-print { display: none !important; }
            body, html { background: white !important; }
            main, .max-w-7xl, [class*="ml-64"], [class*="lg:ml-64"] { margin-left: 0 !important; padding: 0 !important; }
            .print-only { display: block !important; }
            .print-hide-block { display: none !important; }
        }
        .print-only { display: none; }
    </style>
    @endverbatim

    <div class="print-only" id="handover-sheet">
        <div style="padding: 24px; font-family: ui-sans-serif, system-ui, sans-serif; color: #111;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #111; padding-bottom:8px; margin-bottom:16px;">
                <div>
                    <div style="font-size:20px; font-weight:700;">Cashier Handover Sheet</div>
                    <div style="font-size:12px; color:#444;">Shift date: {{ $d->format('l, d M Y') }}</div>
                </div>
                <div style="font-size:11px; text-align:right;">
                    <div>Generated: {{ now()->format('d M Y H:i') }}</div>
                    <div>Attendant: <strong>{{ $attendantName ?: '—' }}</strong></div>
                </div>
            </div>

            <table style="width:100%; font-size:12px; margin-bottom:16px; border-collapse:collapse;">
                <tr><td style="padding:4px 8px; border:1px solid #ccc;">Shift open time</td><td style="padding:4px 8px; border:1px solid #ccc;">{{ $shiftOpenedAt }}</td>
                    <td style="padding:4px 8px; border:1px solid #ccc;">Opening cash</td><td style="padding:4px 8px; border:1px solid #ccc;">₹{{ number_format($openingCash, 2) }}</td></tr>
                <tr><td style="padding:4px 8px; border:1px solid #ccc;">Cash collected</td><td style="padding:4px 8px; border:1px solid #ccc;">₹{{ number_format($totals['cash'], 2) }}</td>
                    <td style="padding:4px 8px; border:1px solid #ccc;">Card / UPI total</td><td style="padding:4px 8px; border:1px solid #ccc;">₹{{ number_format($totals['card'] + $totals['upi'], 2) }}</td></tr>
                <tr><td style="padding:4px 8px; border:1px solid #ccc;">Expected close balance</td><td style="padding:4px 8px; border:1px solid #ccc;"><strong>₹{{ number_format($expectedClose, 2) }}</strong></td>
                    <td style="padding:4px 8px; border:1px solid #ccc;">Counted cash</td><td style="padding:4px 8px; border:1px solid #ccc;">₹{{ number_format($currentCash, 2) }}</td></tr>
            </table>

            <div style="font-size:13px; font-weight:600; margin-bottom:6px;">By mode</div>
            <table style="width:100%; font-size:12px; border-collapse:collapse; margin-bottom:16px;">
                <thead><tr><th style="text-align:left; padding:4px 8px; border-bottom:1px solid #999;">Mode</th><th style="text-align:right; padding:4px 8px; border-bottom:1px solid #999;">Count</th><th style="text-align:right; padding:4px 8px; border-bottom:1px solid #999;">Amount</th></tr></thead>
                <tbody>
                    @forelse($byMode as $b)
                        <tr><td style="padding:3px 8px; text-transform:capitalize;">{{ str_replace('_',' ',$b['mode']) }}</td><td style="padding:3px 8px; text-align:right;">{{ $b['count'] }}</td><td style="padding:3px 8px; text-align:right;">₹{{ number_format($b['amount'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="3" style="padding:6px; text-align:center;">No receipts.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div style="font-size:13px; font-weight:600; margin-bottom:6px;">All receipts ({{ $payments->count() }})</div>
            <table style="width:100%; font-size:11px; border-collapse:collapse; margin-bottom:16px;">
                <thead><tr><th style="text-align:left; padding:4px 8px; border-bottom:1px solid #999;">Receipt #</th><th style="text-align:left; padding:4px 8px; border-bottom:1px solid #999;">Time</th><th style="text-align:left; padding:4px 8px; border-bottom:1px solid #999;">Mode</th><th style="text-align:right; padding:4px 8px; border-bottom:1px solid #999;">Amount</th></tr></thead>
                <tbody>
                    @forelse($payments as $p)
                        <tr><td style="padding:3px 8px; font-family:monospace;">{{ $p->receipt_number }}</td><td style="padding:3px 8px;">{{ $p->created_at->format('H:i') }}</td><td style="padding:3px 8px; text-transform:capitalize;">{{ str_replace('_',' ',$p->mode) }}</td><td style="padding:3px 8px; text-align:right;">₹{{ number_format($p->amount, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" style="padding:6px; text-align:center;">No receipts on this shift.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div style="display:flex; gap:24px; margin-top:48px; font-size:12px;">
                <div style="flex:1; border-top:1px solid #111; padding-top:6px;">Cashier signature</div>
                <div style="flex:1; border-top:1px solid #111; padding-top:6px;">Receiving manager signature</div>
            </div>
        </div>
    </div>

    <div class="flex items-baseline justify-between mb-1 print-hide-block">
        <h1 class="text-2xl font-bold">Cashier shift</h1>
        <input type="date" wire:model.live="shiftDate" class="px-3 py-1.5 border rounded text-sm">
    </div>
    <p class="text-sm text-slate-600 mb-6 print-hide-block">Receipts handled by you on {{ $d->format('l, d M Y') }}.</p>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6 print-hide-block">
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-800 text-white rounded-xl p-4">
            <div class="text-xs opacity-80">Total collected</div>
            <div class="text-2xl font-bold">₹{{ number_format($totals['total'], 0) }}</div>
            <div class="text-xs opacity-80">{{ $totals['count'] }} receipts</div>
        </div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Cash</div><div class="text-2xl font-bold">₹{{ number_format($totals['cash'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Card</div><div class="text-2xl font-bold">₹{{ number_format($totals['card'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">UPI</div><div class="text-2xl font-bold">₹{{ number_format($totals['upi'], 0) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Other</div><div class="text-2xl font-bold">₹{{ number_format($totals['other'], 0) }}</div></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 print-hide-block">
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 font-semibold">Receipts ({{ $payments->count() }})</div>
            <div class="overflow-y-auto max-h-96">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse($payments as $p)
                        <tr><td class="px-5 py-2 font-mono text-xs">{{ $p->receipt_number }}</td><td class="px-4 py-2 text-xs">{{ $p->created_at->format('H:i') }}</td><td class="px-4 py-2 text-xs">{{ ucfirst(str_replace('_',' ',$p->mode)) }}</td><td class="px-4 py-2 text-right font-medium text-emerald-700">₹{{ number_format($p->amount, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">No receipts on this shift.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-xl border overflow-hidden">
                <div class="px-5 py-3 border-b bg-slate-50 font-semibold">By mode</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse($byMode as $b)
                            <tr><td class="px-5 py-2 capitalize">{{ str_replace('_',' ',$b['mode']) }}</td><td class="px-4 py-2 text-right">{{ $b['count'] }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($b['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-4 text-center text-sm text-slate-500">No receipts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                <div class="font-semibold text-amber-900 mb-1">End-of-shift handover</div>
                <p class="text-sm text-amber-800 mb-3">Reconcile your cash drawer against the total above. Settle credit-card slips with the back office.</p>
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <label class="text-xs text-amber-900">Opening cash (₹)
                        <input type="number" step="0.01" wire:model.live="openingCash" class="mt-1 w-full px-2 py-1 border rounded text-sm bg-white">
                    </label>
                    <label class="text-xs text-amber-900">Counted cash now (₹)
                        <input type="number" step="0.01" wire:model.live="currentCash" class="mt-1 w-full px-2 py-1 border rounded text-sm bg-white">
                    </label>
                    <label class="text-xs text-amber-900 col-span-2">Attendant
                        <input type="text" wire:model.live="attendantName" class="mt-1 w-full px-2 py-1 border rounded text-sm bg-white">
                    </label>
                </div>
                <div class="flex items-center justify-between mb-3 text-sm text-amber-900">
                    <span>Expected close balance:</span>
                    <span class="font-bold">₹{{ number_format($expectedClose, 2) }}</span>
                </div>
                <button type="button" wire:click="printHandover" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2 rounded text-sm">Generate handover sheet</button>
            </div>
        </div>
    </div>
</div>
