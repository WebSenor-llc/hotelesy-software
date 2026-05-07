<div>
    <div class="flex items-baseline justify-between mb-2">
        <h1 class="text-2xl font-bold text-slate-900">GST Management</h1>
        <div class="flex items-center gap-2">
            <button wire:click="exportCsv" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm font-medium">↓ Export CSV</button>
            <button onclick="window.print()" class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm">Print</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-4">
        GSTR-1 summary, HSN-wise breakdown, and output tax liability for
        <span class="font-medium">{{ $start->format('d M Y') }} – {{ $end->format('d M Y') }}</span> ·
        GSTIN: <code class="font-mono">{{ $property?->gst_number ?: '—' }}</code>
    </p>

    {{-- Date range filter --}}
    <div class="bg-white border rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
        @php
            $btn = function($key, $label, $current) {
                $active = $current === $key;
                $cls = $active
                    ? 'bg-brand-600 text-white border-brand-600'
                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50';
                return [$cls, $label];
            };
        @endphp
        @foreach (['today'=>'Today','yesterday'=>'Yesterday','month'=>'This month','last_month'=>'Last month','custom'=>'Custom'] as $k => $label)
            @php [$cls, $lbl] = $btn($k, $label, $preset); @endphp
            <button wire:click="applyPreset('{{ $k }}')" class="px-3 py-1.5 text-sm border rounded-md {{ $cls }}">{{ $lbl }}</button>
        @endforeach

        <div class="flex items-center gap-2 ml-auto text-sm">
            <input type="date" wire:model.live="startDate" class="px-3 py-1.5 border border-slate-300 rounded">
            <span class="text-slate-500">→</span>
            <input type="date" wire:model.live="endDate" class="px-3 py-1.5 border border-slate-300 rounded">
        </div>
    </div>

    {{-- Headline KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Line items</div>
            <div class="text-2xl font-bold">{{ $totals['count'] }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Taxable value</div>
            <div class="text-2xl font-bold">₹{{ number_format($totals['taxable'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">CGST</div>
            <div class="text-2xl font-bold text-sky-700">₹{{ number_format($totals['cgst'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">SGST</div>
            <div class="text-2xl font-bold text-sky-700">₹{{ number_format($totals['sgst'], 2) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <div class="text-xs text-slate-500">Invoice value</div>
            <div class="text-2xl font-bold">₹{{ number_format($totals['invoice_value'], 2) }}</div>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-amber-700 text-white rounded-xl p-4">
            <div class="text-xs uppercase opacity-80">Output tax payable</div>
            <div class="text-2xl font-bold">₹{{ number_format($totals['output_tax_payable'], 2) }}</div>
        </div>
    </div>

    {{-- GSTR-1 summary by slab --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">GSTR-1 summary by tax slab</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2 font-semibold">Slab</th>
                    <th class="px-4 py-2 text-right font-semibold">Line items</th>
                    <th class="px-4 py-2 text-right font-semibold">Taxable</th>
                    <th class="px-4 py-2 text-right font-semibold">CGST</th>
                    <th class="px-4 py-2 text-right font-semibold">SGST</th>
                    <th class="px-4 py-2 text-right font-semibold">IGST</th>
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
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($slab['igst'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ number_format($slab['tax'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ number_format($slab['taxable'] + $slab['tax'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">No taxable activity in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- B2B vs B2C --}}
    <div class="grid md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border p-5">
            <div class="text-sm font-semibold text-slate-900 mb-2">B2B (registered)</div>
            <div class="grid grid-cols-3 gap-3">
                <div><div class="text-xs text-slate-500">Line items</div><div class="text-xl font-bold">{{ $b2bCount }}</div></div>
                <div><div class="text-xs text-slate-500">Taxable</div><div class="text-xl font-bold">₹{{ number_format($b2bTaxable, 0) }}</div></div>
                <div><div class="text-xs text-slate-500">Tax</div><div class="text-xl font-bold">₹{{ number_format($b2bTax, 0) }}</div></div>
            </div>
            <div class="text-xs text-slate-500 mt-2">Folios with company GSTIN.</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-sm font-semibold text-slate-900 mb-2">B2C (consumers)</div>
            <div class="grid grid-cols-3 gap-3">
                <div><div class="text-xs text-slate-500">Line items</div><div class="text-xl font-bold">{{ $b2cCount }}</div></div>
                <div><div class="text-xs text-slate-500">Taxable</div><div class="text-xl font-bold">₹{{ number_format($b2cTaxable, 0) }}</div></div>
                <div><div class="text-xs text-slate-500">Tax</div><div class="text-xl font-bold">₹{{ number_format($b2cTax, 0) }}</div></div>
            </div>
            <div class="text-xs text-slate-500 mt-2">Walk-in / individual guests.</div>
        </div>
    </div>

    {{-- HSN summary --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">HSN summary</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2 font-semibold">HSN</th>
                    <th class="px-4 py-2 font-semibold">Description</th>
                    <th class="px-4 py-2 text-right font-semibold">Rate %</th>
                    <th class="px-4 py-2 text-right font-semibold">Qty</th>
                    <th class="px-4 py-2 text-right font-semibold">Taxable</th>
                    <th class="px-4 py-2 text-right font-semibold">CGST</th>
                    <th class="px-4 py-2 text-right font-semibold">SGST</th>
                    <th class="px-4 py-2 text-right font-semibold">Total tax</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($hsn as $row)
                    <tr>
                        <td class="px-5 py-2.5 font-mono text-xs">{{ $row['hsn'] }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $row['desc'] }}</td>
                        <td class="px-4 py-2.5 text-right">{{ rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}%</td>
                        <td class="px-4 py-2.5 text-right">{{ rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($row['taxable'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($row['cgst'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ number_format($row['sgst'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ number_format($row['tax'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">No HSN activity to summarise.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Line items --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Taxable line items ({{ $charges->count() }})</div>
        <div class="overflow-x-auto max-h-96">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b sticky top-0">
                <tr>
                    <th class="px-4 py-2 font-semibold">Date</th>
                    <th class="px-4 py-2 font-semibold">Folio</th>
                    <th class="px-4 py-2 font-semibold">Category</th>
                    <th class="px-4 py-2 font-semibold">Description</th>
                    <th class="px-4 py-2 text-right font-semibold">Taxable</th>
                    <th class="px-4 py-2 text-right font-semibold">CGST</th>
                    <th class="px-4 py-2 text-right font-semibold">SGST</th>
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
                        <td class="px-4 py-2 text-right text-xs">₹{{ number_format($c->tax_amount / 2, 2) }}</td>
                        <td class="px-4 py-2 text-right text-xs">₹{{ number_format($c->tax_amount / 2, 2) }}</td>
                        <td class="px-4 py-2 text-right text-xs font-medium">₹{{ number_format($c->net_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No taxable charges in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
