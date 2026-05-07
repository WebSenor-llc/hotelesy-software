<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Tax / GST report</h1>
        <div class="flex items-center gap-2 text-sm">
            <input type="date" wire:model.live="startDate" class="px-3 py-1.5 border border-slate-300 rounded">
            <span class="text-slate-500">→</span>
            <input type="date" wire:model.live="endDate" class="px-3 py-1.5 border border-slate-300 rounded">
            <button onclick="window.print()" class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm">Print</button>
            <button type="button" wire:click="exportCsv" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Export CSV</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">
        GSTR-1 line items grouped by tax slab. {{ $start->format('d M Y') }} – {{ $end->format('d M Y') }} ·
        GSTIN: <code class="font-mono">{{ $property?->gst_number ?: '—' }}</code>
    </p>

    {{-- Totals --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Total invoices</div><div class="text-2xl font-bold">{{ $totals['count'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Taxable value</div><div class="text-2xl font-bold">₹{{ number_format($totals['taxable'], 2) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">CGST</div><div class="text-2xl font-bold text-sky-700">₹{{ number_format($totals['cgst'], 2) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">SGST</div><div class="text-2xl font-bold text-sky-700">₹{{ number_format($totals['sgst'], 2) }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Invoice total</div><div class="text-2xl font-bold">₹{{ number_format($totals['total'], 2) }}</div></div>
    </div>

    {{-- Slab summary --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Summary by tax slab</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2 font-semibold">Tax slab</th>
                    <th class="px-4 py-2 text-right font-semibold">Line items</th>
                    <th class="px-4 py-2 text-right font-semibold">Taxable value</th>
                    <th class="px-4 py-2 text-right font-semibold">CGST (½)</th>
                    <th class="px-4 py-2 text-right font-semibold">SGST (½)</th>
                    <th class="px-4 py-2 text-right font-semibold">Total tax</th>
                    <th class="px-4 py-2 text-right font-semibold">Invoice value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($bySlab as $slab)
                    <tr>
                        <td class="px-5 py-2.5 font-semibold">{{ rtrim(rtrim(number_format($slab['rate'], 2), '0'), '.') }}%</td>
                        <td class="px-4 py-2.5 text-right">{{ $slab['count'] }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($slab['taxable'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($slab['cgst'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($slab['sgst'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ number_format($slab['tax'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ number_format($slab['taxable'] + $slab['tax'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">No tax data in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- B2B vs B2C --}}
    <div class="grid md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border p-5">
            <div class="text-sm font-semibold text-slate-900 mb-2">B2B (registered companies)</div>
            <div class="text-3xl font-bold">{{ $b2bCount }}</div>
            <div class="text-xs text-slate-500">Invoices issued to companies with GSTIN</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-sm font-semibold text-slate-900 mb-2">B2C (consumers)</div>
            <div class="text-3xl font-bold">{{ $b2cCount }}</div>
            <div class="text-xs text-slate-500">Walk-in / individual guests</div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">All taxable line items ({{ $charges->count() }})</div>
        <div class="overflow-x-auto max-h-96">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b sticky top-0">
                <tr>
                    <th class="px-4 py-2 font-semibold">Date</th>
                    <th class="px-4 py-2 font-semibold">Folio</th>
                    <th class="px-4 py-2 font-semibold">Category</th>
                    <th class="px-4 py-2 font-semibold">Description</th>
                    <th class="px-4 py-2 text-right font-semibold">Taxable</th>
                    <th class="px-4 py-2 text-right font-semibold">GST</th>
                    <th class="px-4 py-2 text-right font-semibold">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($charges as $c)
                    <tr>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ \Carbon\Carbon::parse($c->business_date)->format('d M') }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $c->folio?->folio_number }}</td>
                        <td class="px-4 py-2 text-xs"><span class="px-2 py-0.5 bg-slate-100 rounded">{{ str_replace('_',' ',$c->category) }}</span></td>
                        <td class="px-4 py-2 text-xs truncate max-w-xs">{{ $c->description }}</td>
                        <td class="px-4 py-2 text-right text-xs">₹{{ number_format($c->amount, 2) }}</td>
                        <td class="px-4 py-2 text-right text-xs">₹{{ number_format($c->tax_amount, 2) }}</td>
                        <td class="px-4 py-2 text-right text-xs font-medium">₹{{ number_format($c->net_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No taxable charges in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
