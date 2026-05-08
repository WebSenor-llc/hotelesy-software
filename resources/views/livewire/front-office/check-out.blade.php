<div>
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Check-out</h1>
    <p class="text-sm text-slate-600 mb-4">Settle a guest's folio and free the room.</p>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm flex items-center justify-between gap-3 flex-wrap">
            <span>{{ session('success') }}</span>
            @if($invId = session('issued_invoice_id'))
                <span class="flex items-center gap-2">
                    <a href="{{ route('invoice.show', $invId) }}" target="_blank"
                       class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-3 py-1.5 rounded text-xs">
                       ⚖ View invoice {{ session('issued_invoice_number') }}
                    </a>
                    <a href="{{ route('invoice.pdf', $invId) }}" target="_blank"
                       class="bg-white border border-emerald-300 hover:bg-emerald-50 text-emerald-800 font-semibold px-3 py-1.5 rounded text-xs">
                       PDF
                    </a>
                </span>
            @endif
        </div>
    @endif
    @if(session('warning'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-sm">
            ⚠ {{ session('warning') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            ✗ {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <div class="font-semibold mb-1">Please fix:</div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- IN-HOUSE LIST --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 font-semibold text-slate-900">In-house ({{ $departures->count() }})</div>
            @forelse($departures as $a)
                <button type="button" wire:click="selectReservation({{ $a->id }})"
                        class="w-full text-left px-5 py-3 border-b border-slate-100 hover:bg-brand-50 transition {{ $selectedReservationId === $a->id ? 'bg-brand-50 border-l-4 border-l-brand-500' : '' }}">
                    <div class="flex items-start justify-between mb-1">
                        <div class="font-semibold text-slate-900">{{ $a->guest_name ?: 'Guest' }}</div>
                        <span class="font-mono text-[10px] text-slate-500">{{ $a->reservation_number }}</span>
                    </div>
                    <div class="text-xs text-slate-500">
                        Departure: {{ \Carbon\Carbon::parse($a->departure_date)->format('d M') }} ·
                        Balance:
                        <span class="{{ $a->balance_amount > 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600' }}">
                            ₹{{ number_format($a->balance_amount, 0) }}
                        </span>
                    </div>
                </button>
            @empty
                <div class="px-5 py-12 text-center text-sm text-slate-500">No in-house guests right now.</div>
            @endforelse
        </div>

        {{-- SETTLE PANEL --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            @if($selected)
                <div class="flex items-baseline justify-between mb-1">
                    <h3 class="font-semibold text-slate-900">{{ $selected->guest_name }}</h3>
                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ $selected->status }}</span>
                </div>
                <div class="text-xs text-slate-500 mb-4">
                    {{ $selected->reservation_number }} · {{ $selected->nights }}N ·
                    Departure {{ \Carbon\Carbon::parse($selected->departure_date)->format('d M Y') }}
                </div>

                @if($folio)
                    <div class="bg-slate-50 rounded-lg p-4 mb-4 space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-slate-600">Charges</span><span class="font-mono">₹{{ number_format($folio->total_charges, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-600">Tax</span><span class="font-mono">₹{{ number_format($folio->total_taxes, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-600">Discounts</span><span class="font-mono">−₹{{ number_format($folio->total_discounts, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-600">Paid</span><span class="text-emerald-700 font-mono">−₹{{ number_format($folio->total_payments, 2) }}</span></div>
                        <div class="border-t border-slate-200 pt-1.5 mt-1.5 flex justify-between font-bold text-slate-900">
                            <span>Balance due</span>
                            <span class="font-mono {{ $folio->balance > 0 ? 'text-rose-600' : 'text-emerald-600' }}">₹{{ number_format($folio->balance, 2) }}</span>
                        </div>
                    </div>

                    {{-- LIVE balance preview --}}
                    @php
                        $currentBalance = (float) $folio->balance;
                        $totalEntered = $this->totalPaymentAmount;
                        $remaining = round($currentBalance - $totalEntered, 2);
                        $hasCityLedgerRow = collect($payments)->contains(fn($p) => ($p['mode'] ?? '') === 'company_credit' && (float)($p['amount'] ?? 0) > 0);
                    @endphp
                    @if($totalEntered > 0 || $remaining != $currentBalance)
                        <div class="rounded-lg p-3 mb-3 text-sm border
                            {{ $remaining > 0.01 ? 'bg-rose-50 border-rose-200 text-rose-800'
                              : ($remaining < -0.01 ? 'bg-amber-50 border-amber-200 text-amber-800'
                              : 'bg-emerald-50 border-emerald-200 text-emerald-800') }}">
                            <div class="flex justify-between mb-1">
                                <span class="font-semibold">Total entered:</span>
                                <span class="font-mono">₹{{ number_format($totalEntered, 2) }}</span>
                            </div>
                            @if($remaining > 0.01)
                                After this: ₹{{ number_format($remaining, 2) }} will <strong>still be due</strong>.
                            @elseif($remaining < -0.01)
                                <strong>Overpayment:</strong> ₹{{ number_format(abs($remaining), 2) }} change to return.
                            @else
                                <strong>✓ Folio will settle in full.</strong>
                            @endif
                        </div>
                    @endif
                @endif

                {{-- MULTI-MODE PAYMENT ROWS (split-tender) --}}
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-semibold text-slate-700">Payment(s)</label>
                        <span class="text-[11px] text-slate-500">Split across modes if needed</span>
                    </div>

                    @foreach($payments as $idx => $p)
                        <div class="grid grid-cols-12 gap-2 mb-2 items-center" wire:key="pay-row-{{ $idx }}">
                            <div class="col-span-4">
                                <select wire:model.live="payments.{{ $idx }}.mode"
                                        class="w-full px-2 py-2 border border-slate-300 rounded-lg text-sm">
                                    <option value="cash">💵 Cash</option>
                                    <option value="card">💳 Card</option>
                                    <option value="upi">📱 UPI</option>
                                    <option value="bank_transfer">🏦 Bank / Net banking</option>
                                    <option value="company_credit">📒 City ledger</option>
                                </select>
                            </div>
                            <div class="col-span-3">
                                <input type="number" step="0.01" min="0"
                                       wire:model.live.debounce.300ms="payments.{{ $idx }}.amount"
                                       placeholder="Amount"
                                       class="w-full px-2 py-2 border border-slate-300 rounded-lg text-sm font-mono">
                            </div>
                            <div class="col-span-4">
                                <input type="text"
                                       wire:model="payments.{{ $idx }}.reference"
                                       placeholder="Ref / txn id (optional)"
                                       class="w-full px-2 py-2 border border-slate-300 rounded-lg text-sm">
                            </div>
                            <div class="col-span-1 text-right">
                                @if(count($payments) > 1)
                                    <button type="button"
                                            wire:click="removePaymentRow({{ $idx }})"
                                            title="Remove this payment row"
                                            class="px-2 py-2 text-rose-600 hover:bg-rose-50 rounded text-base leading-none">×</button>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" wire:click="addPaymentRow"
                                class="px-2.5 py-1 bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold rounded border border-brand-200">
                            + Add another payment mode
                        </button>
                        @if($folio && $folio->balance > 0)
                            <button type="button" wire:click="payFullBalance"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded">
                                Pay full ₹{{ number_format($folio->balance, 0) }}
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Open-balance safety toggle --}}
                @if($folio)
                    @php
                        $remainingForToggle = round((float) $folio->balance - (float) $this->totalPaymentAmount, 2);
                        $hasCityLedgerToggle = collect($payments)->contains(fn($p) => ($p['mode'] ?? '') === 'company_credit' && (float)($p['amount'] ?? 0) > 0);
                    @endphp
                    @if($remainingForToggle > 0.01 && ! $hasCityLedgerToggle)
                        <label class="flex items-start gap-2 text-sm bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="allowOpenBalance" class="mt-0.5">
                            <span class="text-amber-900">
                                <strong>Allow check-out with ₹{{ number_format($remainingForToggle, 2) }} balance</strong>
                                <div class="text-xs mt-0.5">The remaining amount will be moved to the city ledger as accounts-receivable. Use this only when the guest has been authorised for a credit settlement.</div>
                            </span>
                        </label>
                    @endif
                @endif

                <button type="button" wire:click="checkOut"
                        class="w-full bg-rose-600 hover:bg-rose-700 disabled:bg-slate-300 text-white font-semibold py-2.5 rounded-lg transition"
                        wire:loading.attr="disabled" wire:target="checkOut">
                    <span wire:loading.remove wire:target="checkOut">Settle & check out</span>
                    <span wire:loading wire:target="checkOut">Processing…</span>
                </button>
            @else
                <div class="text-center py-12 text-sm text-slate-400">Select a guest on the left.</div>
            @endif
        </div>
    </div>
</div>
