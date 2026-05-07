<div class="max-w-4xl" x-data="{}" @print-folio.window="window.print()">
    <style>
        @media print {
            body { background: white !important; }
            nav, aside, header.app-header, .no-print, [data-no-print] { display: none !important; }
            .folio-print-wrap { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .bg-white, .bg-slate-50 { background: white !important; }
            .border, .border-slate-200, .border-b, .border-t { border-color: #ccc !important; }
            button, .no-print-btn { display: none !important; }
            .rounded-xl, .rounded, .rounded-full { border-radius: 0 !important; }
            .shadow, .shadow-sm, .shadow-md { box-shadow: none !important; }
        }
    </style>

    <div class="folio-print-wrap">
        @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm no-print">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm no-print">{{ session('error') }}</div>@endif

        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Folio {{ $folio->folio_number }}</h1>
                <p class="text-sm text-slate-600">{{ $folio->billing_name }} · {{ $folio->reservation?->reservation_number }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full {{ $folio->status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">{{ $folio->status }}</span>
                <button type="button" wire:click="printBill" class="no-print px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded text-sm">Print bill</button>
                <button type="button" wire:click="issueGstInvoice" class="no-print px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm font-semibold" title="Issue government-compliant GST tax invoice">⚖ Issue GST invoice</button>
                <button type="button" wire:click="emailInvoice" class="no-print px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded text-sm">Email invoice</button>
                @if($showEInvoiceButton ?? false)
                    <button type="button" wire:click="generateEInvoice" class="no-print px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded text-sm">Generate e-invoice (IRN)</button>
                @endif
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">Total charges</div>
                <div class="text-xl font-bold">₹{{ number_format($folio->total_charges, 2) }}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">Total paid</div>
                <div class="text-xl font-bold text-emerald-700">₹{{ number_format($folio->total_payments, 2) }}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">Balance</div>
                <div class="text-xl font-bold {{ $folio->balance > 0 ? 'text-rose-600' : 'text-slate-900' }}">₹{{ number_format($folio->balance, 2) }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 font-semibold text-slate-900">Charges</div>
            @if($charges->count())
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50">
                        <tr><th class="px-5 py-2">Date</th><th class="px-4 py-2">Description</th><th class="px-4 py-2 text-right">Amount</th><th class="px-2 py-2 no-print"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($charges as $c)
                            <tr class="{{ $c->is_voided ? 'opacity-50' : '' }}">
                                <td class="px-5 py-2 text-slate-500">{{ $c->created_at->format('d M H:i') }}</td>
                                <td class="px-4 py-2">
                                    {{ $c->description }}
                                    @if($c->is_voided)<span class="ml-2 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">Voided{{ $c->void_reason ? ': '.$c->void_reason : '' }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-right font-medium {{ $c->is_voided ? 'line-through' : '' }}">₹{{ number_format($c->amount ?? $c->total_amount ?? 0, 2) }}</td>
                                <td class="px-2 py-2 text-right no-print">
                                    @if(!$c->is_voided && $folio->status === 'open')
                                        @if($voidingChargeId === $c->id)
                                            <div class="flex items-center gap-1">
                                                <input type="text" wire:model="voidReason" placeholder="Reason" class="px-2 py-1 border rounded text-xs w-32">
                                                <button type="button" wire:click="voidCharge({{ $c->id }}, $wire.voidReason)" class="text-xs text-rose-600">Confirm</button>
                                                <button type="button" wire:click="cancelVoid" class="text-xs text-slate-500">×</button>
                                            </div>
                                        @else
                                            <button type="button" wire:click="startVoid({{ $c->id }})" class="text-xs text-rose-600">Void</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-5 py-8 text-sm text-slate-500 text-center">No charges yet.</div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 font-semibold text-slate-900">Payments</div>
            @if($payments->count())
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50">
                        <tr><th class="px-5 py-2">Receipt</th><th class="px-4 py-2">Mode</th><th class="px-4 py-2">Date</th><th class="px-4 py-2 text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($payments as $p)
                            <tr><td class="px-5 py-2 font-mono text-xs">{{ $p->receipt_number }}</td><td class="px-4 py-2">{{ ucfirst($p->mode) }}</td><td class="px-4 py-2 text-slate-500">{{ $p->payment_date?->format('d M Y') }}</td><td class="px-4 py-2 text-right font-medium text-emerald-700">₹{{ number_format($p->amount, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-5 py-8 text-sm text-slate-500 text-center">No payments recorded.</div>
            @endif
        </div>

        {{-- TDS deductible (company-billed folios with TDS-applicable companies) --}}
        @if(isset($tds) && ($tds['amount'] ?? 0) > 0)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mt-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-amber-800 font-semibold">TDS deductible (Section {{ $tds['section'] }})</div>
                        @if(($tds['rent_base'] ?? 0) > 0)
                            <div class="text-xs text-amber-700">§194I @ 2% on rent base ₹{{ number_format($tds['rent_base'], 2) }} = ₹{{ number_format($tds['amount_194i'] ?? 0, 2) }}</div>
                        @endif
                        @if(($tds['service_base'] ?? 0) > 0)
                            <div class="text-xs text-amber-700">§194J @ 10% on services ₹{{ number_format($tds['service_base'], 2) }} = ₹{{ number_format($tds['amount_194j'] ?? 0, 2) }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-amber-700">Total TDS</div>
                        <div class="text-xl font-bold text-amber-900">₹{{ number_format($tds['amount'], 2) }}</div>
                        <div class="text-xs text-amber-700 mt-1">Net payable: <strong>₹{{ number_format(($folio->total_charges + $folio->total_taxes) - $tds['amount'], 2) }}</strong></div>
                    </div>
                </div>
            </div>
        @endif

        {{-- E-invoice IRN + QR --}}
        @if(isset($eInvoice) && $eInvoice)
            <div class="bg-white rounded-xl border border-slate-200 p-5 mt-4">
                <div class="flex items-start gap-4">
                    <img src="{{ $eInvoice->qrImageUrl() }}" alt="QR" class="w-32 h-32 flex-shrink-0 border rounded">
                    <div class="flex-1">
                        <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">e-Invoice (IRN/QR)</div>
                        <div class="text-sm mt-1">Invoice: <strong class="font-mono">{{ $eInvoice->invoice_number }}</strong></div>
                        <div class="text-xs mt-1 break-all">IRN: <span class="font-mono">{{ $eInvoice->irn }}</span></div>
                        <div class="text-xs">Ack #: <span class="font-mono">{{ $eInvoice->ack_number }}</span> · {{ $eInvoice->ack_date?->format('d M Y H:i') }}</div>
                        @if($eInvoice->status === 'cancelled')
                            <div class="mt-2 text-[10px] uppercase tracking-wider px-2 py-0.5 inline-block rounded-full bg-rose-100 text-rose-700">Cancelled</div>
                        @else
                            <div class="mt-2 text-[10px] uppercase tracking-wider px-2 py-0.5 inline-block rounded-full bg-emerald-100 text-emerald-700">Generated</div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
