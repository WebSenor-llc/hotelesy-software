<div x-data="{}" @print-tds-report.window="window.print()">
    <style>
        @media print {
            body { background: white !important; }
            nav, aside, header.app-header, .no-print, [data-no-print] { display: none !important; }
            .print-wrap { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .bg-white, .bg-slate-50 { background: white !important; }
            .border, .border-slate-200 { border-color: #444 !important; }
            button, .no-print-btn { display: none !important; }
            .rounded-xl, .rounded { border-radius: 0 !important; }
            .shadow { box-shadow: none !important; }
        }
    </style>

    <div class="print-wrap">
        <div class="flex items-baseline justify-between mb-1">
            <h1 class="text-2xl font-bold text-slate-900">TDS report — Section 194I / 194J deductions</h1>
        </div>
        <p class="text-sm text-slate-600 mb-4 no-print">Tax deducted at source on company-billed payments. 2% on rent (room/package) under §194I; 10% on banquet/conference under §194J.</p>

        <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap gap-3 items-end no-print">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">From</label>
                <input type="date" wire:model.live="fromDate" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">To</label>
                <input type="date" wire:model.live="toDate" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Company</label>
                <select wire:model.live="companyId" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">All companies</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Section</label>
                <select wire:model.live="sectionFilter" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">All</option>
                    <option value="194I">§194I (rent)</option>
                    <option value="194J">§194J (services)</option>
                </select>
            </div>
            <div class="flex-1"></div>
            <button type="button" wire:click="printNow" class="px-3 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded text-sm">Print</button>
            <button type="button" wire:click="exportCsv" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Download CSV</button>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">Total entries</div>
                <div class="text-xl font-bold">{{ count($rows) }}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">Total gross</div>
                <div class="text-xl font-bold">₹{{ number_format($totalGross, 2) }}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">Total TDS deducted</div>
                <div class="text-xl font-bold text-rose-700">₹{{ number_format($totalTds, 2) }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl border overflow-hidden">
            @if(count($rows))
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Company</th>
                            <th class="px-4 py-3">GSTIN / PAN</th>
                            <th class="px-4 py-3">Receipt</th>
                            <th class="px-4 py-3">Section</th>
                            <th class="px-4 py-3 text-right">Gross</th>
                            <th class="px-4 py-3 text-right">Rate</th>
                            <th class="px-4 py-3 text-right">TDS</th>
                            <th class="px-4 py-3 text-right">Net paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rows as $r)
                            <tr>
                                <td class="px-4 py-2 text-slate-600">{{ $r['date'] }}</td>
                                <td class="px-4 py-2 font-semibold text-slate-900">{{ $r['company'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs">
                                    <div>{{ $r['gstin'] ?: '—' }}</div>
                                    <div class="text-slate-500">{{ $r['pan'] ?: '' }}</div>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $r['receipt'] }}</td>
                                <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-slate-100">§{{ $r['section'] }}</span></td>
                                <td class="px-4 py-2 text-right">₹{{ number_format($r['gross'], 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ $r['rate'] }}%</td>
                                <td class="px-4 py-2 text-right text-rose-700 font-semibold">₹{{ number_format($r['tds_amount'], 2) }}</td>
                                <td class="px-4 py-2 text-right">₹{{ number_format($r['net'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-5 py-12 text-sm text-slate-500 text-center">No TDS deductions in this period.</div>
            @endif
        </div>
    </div>
</div>
