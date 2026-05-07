<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">E-invoices (IRN/QR)</h1>
    </div>
    <p class="text-sm text-slate-600 mb-4">Mandatory IRN/QR-coded invoices for B2B transactions when annual turnover ≥ ₹5cr (NIC IRP).</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    @if(!$property->einvoice_required)
        <div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
            <strong>E-invoicing not enabled for this property.</strong>
            Enable "Annual turnover ≥ ₹5cr" on the property settings to start generating IRN/QR codes for B2B invoices.
        </div>
    @endif

    <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">From</label>
            <input type="date" wire:model.live="fromDate" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">To</label>
            <input type="date" wire:model.live="toDate" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
            <select wire:model.live="statusFilter" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">All</option>
                <option value="generated">Generated</option>
                <option value="cancelled">Cancelled</option>
                <option value="draft">Draft</option>
            </select>
        </div>
    </div>

    @if($eligibleFolios->count() && $property->einvoice_required)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
            <div class="text-sm font-semibold text-amber-900 mb-2">{{ $eligibleFolios->count() }} eligible folio(s) without an e-invoice yet</div>
            <div class="space-y-1">
                @foreach($eligibleFolios as $f)
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <div>
                            <span class="font-mono text-xs">{{ $f->folio_number }}</span> ·
                            {{ $f->company?->name }} ·
                            <span class="text-slate-600">₹{{ number_format($f->total_charges, 2) }}</span>
                        </div>
                        <button type="button" wire:click="generateForFolio({{ $f->id }})" class="text-xs px-2 py-1 bg-brand-600 hover:bg-brand-700 text-white rounded">Generate IRN</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden">
        @if($rows->count())
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Generated</th>
                        <th class="px-4 py-3">Invoice / Folio</th>
                        <th class="px-4 py-3">Buyer</th>
                        <th class="px-4 py-3">IRN</th>
                        <th class="px-4 py-3">Ack</th>
                        <th class="px-4 py-3 text-right">Value</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($rows as $row)
                        @php $canCancel = $row->status === 'generated' && $row->generated_at && $row->generated_at->diffInHours(now()) <= 24; @endphp
                        <tr>
                            <td class="px-4 py-2 text-slate-600 text-xs">{{ $row->generated_at?->format('d M H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">
                                <div class="font-semibold">{{ $row->invoice_number }}</div>
                                <div class="text-slate-500">{{ $row->folio?->folio_number }}</div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="font-semibold">{{ $row->folio?->company?->name ?? '—' }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $row->folio?->company?->gst_number ?? '' }}</div>
                            </td>
                            <td class="px-4 py-2 font-mono text-[10px] break-all max-w-[12rem]">{{ $row->irn ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->ack_number ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format(($row->folio?->total_charges ?? 0) + ($row->folio?->total_taxes ?? 0), 2) }}</td>
                            <td class="px-4 py-2">
                                @if($row->status === 'generated')
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Generated</span>
                                @elseif($row->status === 'cancelled')
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Cancelled</span>
                                @else
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ $row->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($row->folio_id)
                                        <a href="{{ route('folio.show', $row->folio_id) }}" class="text-xs px-2 py-1 border border-slate-300 rounded hover:bg-slate-50">Open folio</a>
                                    @endif
                                    @if($canCancel)
                                        <button type="button" wire:click="startCancel({{ $row->id }})" class="text-xs px-2 py-1 border border-rose-300 text-rose-700 rounded hover:bg-rose-50">Cancel IRN</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="px-5 py-12 text-sm text-slate-500 text-center">No e-invoices in this period.</div>
        @endif
    </div>

    @if($cancellingId)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-4 overflow-y-auto" wire:click.self="cancelCancel">
            <form wire:submit.prevent="confirmCancel" class="bg-white rounded-xl border w-full max-w-lg my-8 shadow-xl">
                <div class="px-6 py-4 border-b">
                    <h2 class="font-semibold text-lg">Cancel e-invoice (within 24h window)</h2>
                </div>
                <div class="p-6 space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Reason</label>
                        <textarea wire:model="cancelReason" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Duplicate / Data entry error / Order cancelled / Other"></textarea>
                    </div>
                    <p class="text-xs text-rose-600">Once cancelled, the IRN cannot be reused. A fresh invoice with a new number will be required for any reissue.</p>
                </div>
                <div class="px-6 py-4 border-t bg-slate-50 flex justify-end gap-2">
                    <button type="button" wire:click="cancelCancel" class="px-4 py-2 text-sm">Keep IRN</button>
                    <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Cancel IRN</button>
                </div>
            </form>
        </div>
    @endif
</div>
