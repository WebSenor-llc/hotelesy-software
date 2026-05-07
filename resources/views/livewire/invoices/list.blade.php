<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tax invoices</h1>
            <p class="text-sm text-slate-500">Government-compliant Rule 46 invoices · GSTR-1 ready</p>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-emerald-700 font-semibold">Issued</div>
            <div class="text-2xl font-bold text-emerald-900">{{ $stats['issued'] }}</div>
            <div class="text-[10px] text-emerald-600">{{ $stats['count'] }} total · {{ $stats['cancelled'] }} cancelled</div>
        </div>
        <div class="bg-brand-50 border border-brand-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-brand-700 font-semibold">Total invoiced</div>
            <div class="text-2xl font-bold text-brand-900">₹{{ number_format($stats['total_value'], 0) }}</div>
        </div>
        <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-violet-700 font-semibold">B2B (with GSTIN)</div>
            <div class="text-2xl font-bold text-violet-900">{{ $stats['b2b'] }}</div>
        </div>
        <div class="bg-sky-50 border border-sky-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-sky-700 font-semibold">CGST + SGST</div>
            <div class="text-2xl font-bold text-sky-900">₹{{ number_format($stats['cgst'] + $stats['sgst'], 0) }}</div>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="text-[10px] uppercase tracking-wider text-amber-700 font-semibold">IGST</div>
            <div class="text-2xl font-bold text-amber-900">₹{{ number_format($stats['igst'], 0) }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 p-3 mb-4 flex flex-wrap items-center gap-2">
        @foreach([['all','All'],['issued','Issued'],['cancelled','Cancelled']] as [$k, $l])
            <button wire:click="$set('statusFilter','{{ $k }}')"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full {{ $statusFilter === $k ? 'bg-brand-600 text-white' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                {{ $l }}
            </button>
        @endforeach

        <div class="ml-2 flex items-center gap-1 text-xs">
            <span class="text-slate-500">B2B/B2C:</span>
            @foreach([['all','All'],['b2b','B2B'],['b2c','B2C']] as [$k, $l])
                <button wire:click="$set('b2bFilter','{{ $k }}')"
                        class="font-semibold px-2 py-1 rounded {{ $b2bFilter === $k ? 'bg-violet-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                    {{ $l }}
                </button>
            @endforeach
        </div>

        <select wire:model.live="fyFilter" class="ml-2 px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
            <option value="">All FY</option>
            @foreach($financialYears as $fy)<option value="{{ $fy }}">FY {{ $fy }}</option>@endforeach
        </select>

        <input wire:model.live.debounce.300ms="search" type="text"
               placeholder="Search invoice #, recipient, GSTIN…"
               class="ml-auto px-3 py-1.5 border border-slate-300 rounded-lg text-sm w-72">
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2.5">Invoice #</th>
                    <th class="px-3 py-2.5">Date</th>
                    <th class="px-3 py-2.5">Recipient</th>
                    <th class="px-3 py-2.5">B2B / GSTIN</th>
                    <th class="px-3 py-2.5">PoS</th>
                    <th class="px-3 py-2.5 text-right">Taxable</th>
                    <th class="px-3 py-2.5 text-right">Tax</th>
                    <th class="px-3 py-2.5 text-right">Total</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-4 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($invoices as $inv)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-mono text-xs font-semibold text-slate-900">
                            {{ $inv->invoice_number }}
                            <div class="text-[10px] text-slate-400">FY {{ $inv->financial_year }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-xs">{{ $inv->invoice_date?->format('d M Y') }}</td>
                        <td class="px-3 py-2.5">
                            <div class="font-medium text-slate-900">{{ $inv->recipient_name }}</div>
                            @if($inv->recipient_state)
                                <div class="text-[10px] text-slate-500">{{ $inv->recipient_state }} ({{ $inv->recipient_state_code ?: '—' }})</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            @if($inv->recipient_gstin)
                                <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-violet-100 text-violet-800 font-bold">B2B</span>
                                <div class="text-[10px] font-mono text-slate-500">{{ $inv->recipient_gstin }}</div>
                            @else
                                <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">B2C</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs">
                            {{ $inv->place_of_supply ?: '—' }}
                            @if($inv->is_inter_state)<div class="text-[10px] text-amber-700">Inter-state · IGST</div>@else<div class="text-[10px] text-slate-500">Intra-state</div>@endif
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs">₹{{ number_format($inv->taxable_amount, 2) }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs">₹{{ number_format($inv->cgst_total + $inv->sgst_total + $inv->igst_total + $inv->cess_total, 2) }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs font-bold">₹{{ number_format($inv->grand_total, 2) }}</td>
                        <td class="px-3 py-2.5">
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $inv->statusBadgeClass() }}">{{ $inv->status }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('invoice.show', $inv->id) }}" class="text-xs bg-brand-600 hover:bg-brand-700 text-white font-semibold px-2.5 py-1 rounded">View</a>
                            <a href="{{ route('invoice.pdf', $inv->id) }}" target="_blank" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-2.5 py-1 rounded">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-5 py-12 text-center text-sm text-slate-500">
                        No invoices yet. Issue your first one from any folio's <strong>⚖ Issue GST invoice</strong> button.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3 border-t bg-slate-50">{{ $invoices->links() }}</div>
    </div>
</div>
