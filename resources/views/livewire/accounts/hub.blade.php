<div>
    <h1 class="text-2xl font-bold mb-1">Financial accounts</h1>
    <p class="text-sm text-slate-600 mb-6">Chart of accounts, vouchers, GST returns, bank reconciliation, ledgers.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Total vouchers</div><div class="text-2xl font-bold">{{ $stats['vouchers'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Posted today</div><div class="text-2xl font-bold text-emerald-600">{{ $stats['posted_today'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Chart accounts</div><div class="text-2xl font-bold">{{ $stats['accounts'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Voucher types</div><div class="text-2xl font-bold">{{ $stats['voucher_types'] }}</div></div>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="border-b flex">
            @foreach(['vouchers'=>'Vouchers','chart'=>'Chart of accounts','types'=>'Voucher types','reconciliation'=>'Bank reconciliation','gst'=>'GST returns'] as $k=>$l)
                <button type="button" wire:click="$set('tab','{{ $k }}')" class="px-5 py-3 text-sm font-medium border-b-2 transition {{ $tab === $k ? 'border-brand-600 text-brand-700' : 'border-transparent' }}">{{ $l }}</button>
            @endforeach
        </div>

        @if($tab === 'vouchers')
            <div class="p-4 border-b bg-slate-50 flex justify-end">
                <button type="button" wire:click="startNewVoucher" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Post voucher</button>
            </div>

            @if($showVoucherForm)
                <div class="p-6 bg-brand-50 border-b">
                    <h3 class="font-semibold mb-3">New voucher (manual journal entry)</h3>
                    <div class="grid md:grid-cols-3 gap-4 mb-4">
                        <div><label class="block text-xs font-medium mb-1">Voucher type *</label>
                            <select wire:model="vt_voucher_type_id" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                <option value="">Select…</option>
                                @foreach($voucherTypes as $vt)<option value="{{ $vt->id }}">{{ $vt->code }} — {{ $vt->name }}</option>@endforeach
                            </select>
                        </div>
                        <div><label class="block text-xs font-medium mb-1">Date *</label><input type="date" wire:model="vt_date" class="w-full px-3 py-2 border rounded-lg text-sm bg-white"></div>
                        <div class="md:col-span-1"><label class="block text-xs font-medium mb-1">Narration *</label><input type="text" wire:model="vt_narration" class="w-full px-3 py-2 border rounded-lg text-sm bg-white" placeholder="What's this for…"></div>
                    </div>
                    <div class="space-y-2 mb-3">
                        <div class="grid grid-cols-12 gap-2 text-[10px] uppercase tracking-wider text-slate-500 font-semibold px-2"><div class="col-span-4">Account</div><div class="col-span-3">Description</div><div class="col-span-2 text-right">Debit (₹)</div><div class="col-span-2 text-right">Credit (₹)</div></div>
                        @foreach($vt_lines as $i => $line)
                            <div class="grid grid-cols-12 gap-2 items-center bg-white p-2 rounded">
                                <div class="col-span-4">
                                    <select wire:model="vt_lines.{{ $i }}.account_id" class="w-full px-2 py-1.5 border rounded text-sm">
                                        <option value="">Select account…</option>
                                        @foreach($allAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-span-3"><input type="text" wire:model="vt_lines.{{ $i }}.description" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                                <div class="col-span-2"><input type="number" step="0.01" wire:model="vt_lines.{{ $i }}.debit" class="w-full px-2 py-1.5 border rounded text-sm text-right"></div>
                                <div class="col-span-2"><input type="number" step="0.01" wire:model="vt_lines.{{ $i }}.credit" class="w-full px-2 py-1.5 border rounded text-sm text-right"></div>
                                <div class="col-span-1 text-right">@if(count($vt_lines) > 2)<button type="button" wire:click="removeVoucherLine({{ $i }})" class="text-rose-600">×</button>@endif</div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addVoucherLine" class="text-sm text-brand-600 mb-3">+ Add line</button>
                    @php $totalD = collect($vt_lines)->sum(fn($l)=>(float)($l['debit']??0)); $totalC = collect($vt_lines)->sum(fn($l)=>(float)($l['credit']??0)); $balanced = round($totalD,2) === round($totalC,2); @endphp
                    <div class="flex justify-between items-center pt-3 border-t border-brand-200">
                        <div class="text-sm">Debits: <strong>₹{{ number_format($totalD, 2) }}</strong> · Credits: <strong>₹{{ number_format($totalC, 2) }}</strong>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full {{ $balanced ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $balanced ? 'Balanced' : 'Out of balance' }}</span>
                        </div>
                        <div class="space-x-2">
                            <button type="button" wire:click="cancelVoucher" class="px-4 py-2 text-sm">Cancel</button>
                            <button type="button" wire:click="saveVoucher" @disabled(!$balanced) class="bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-semibold">Post voucher</button>
                        </div>
                    </div>
                </div>
            @endif

            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Voucher #</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Date</th><th class="px-4 py-2">Reference</th><th class="px-4 py-2">Narration</th><th class="px-4 py-2 text-right">Debit</th><th class="px-4 py-2 text-right">Credit</th><th class="px-4 py-2">Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($vouchers as $v)
                        <tr class="{{ $v->status === 'reversed' ? 'bg-rose-50 line-through text-slate-400' : '' }}">
                            <td class="px-5 py-2 font-mono text-xs">{{ $v->voucher_number }}</td>
                            <td class="px-4 py-2 text-xs">{{ $v->voucherType?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $v->voucher_date?->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-xs">{{ $v->reference_type }} #{{ $v->reference_id }}</td>
                            <td class="px-4 py-2 text-xs truncate max-w-xs">{{ $v->narration }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($v->total_debit, 2) }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($v->total_credit, 2) }}</td>
                            <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $v->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100' }}">{{ $v->status }}</span></td>
                            <td class="px-4 py-2 text-right">
                                @if($v->status === 'posted')
                                    <button type="button" wire:click="reverseVoucher({{ $v->id }})" wire:confirm="Reverse voucher {{ $v->voucher_number }}?" class="text-xs text-rose-600">Reverse</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-8 text-center text-sm text-slate-500">No vouchers posted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($tab === 'chart')
            <div class="p-4 border-b bg-slate-50 flex justify-end">
                <button type="button" wire:click="startNewAccount" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ New account</button>
            </div>

            @if($showAccountForm)
                <div class="p-6 bg-brand-50 border-b grid md:grid-cols-3 gap-4">
                    <h3 class="md:col-span-3 font-semibold">{{ $a_id ? 'Edit' : 'New' }} chart account</h3>
                    <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="a_code" class="w-full px-3 py-2 border rounded-lg text-sm bg-white" placeholder="1100"></div>
                    <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="a_name" class="w-full px-3 py-2 border rounded-lg text-sm bg-white"></div>
                    <div><label class="block text-xs font-medium mb-1">Type *</label>
                        <select wire:model="a_type" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                            <option value="asset">Asset</option><option value="liability">Liability</option>
                            <option value="equity">Equity</option><option value="income">Income</option><option value="expense">Expense</option>
                        </select>
                    </div>
                    <div><label class="block text-xs font-medium mb-1">Subtype</label>
                        <select wire:model="a_subtype" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                            <option value="current_asset">Current asset</option><option value="fixed_asset">Fixed asset</option>
                            <option value="bank">Bank</option><option value="cash">Cash</option>
                            <option value="current_liability">Current liability</option><option value="long_term_liability">Long-term liability</option>
                            <option value="tax_payable">Tax payable</option><option value="capital">Capital</option><option value="reserves">Reserves</option>
                            <option value="sales_revenue">Sales revenue</option><option value="service_revenue">Service revenue</option><option value="other_income">Other income</option>
                            <option value="cost_of_sales">Cost of sales</option><option value="operating_expense">Operating expense</option>
                            <option value="admin_expense">Admin expense</option><option value="tax_expense">Tax expense</option>
                        </select>
                    </div>
                    <div><label class="block text-xs font-medium mb-1">Opening balance</label><input type="number" step="0.01" wire:model="a_opening_balance" class="w-full px-3 py-2 border rounded-lg text-sm bg-white"></div>
                    <div class="md:col-span-3 flex items-center justify-end gap-3 pt-3 border-t border-brand-200">
                        <label class="flex items-center gap-2 text-sm mr-auto"><input type="checkbox" wire:model="a_is_active" class="rounded">Active</label>
                        <button type="button" wire:click="cancelAccount" class="px-4 py-2 text-sm">Cancel</button>
                        <button type="button" wire:click="saveAccount" class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button>
                    </div>
                </div>
            @endif

            <div class="p-6 grid md:grid-cols-2 gap-6">
                @foreach(['asset'=>'Assets','liability'=>'Liabilities','equity'=>'Equity','income'=>'Income','expense'=>'Expenses'] as $type=>$label)
                    <div>
                        <div class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-2">{{ $label }}</div>
                        <div class="border rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($chart[$type] ?? [] as $a)
                                        <tr><td class="px-3 py-1.5 font-mono text-xs">{{ $a->code }}</td><td class="px-3 py-1.5">{{ $a->name }}</td><td class="px-3 py-1.5 text-xs text-slate-500">{{ str_replace('_',' ', $a->subtype) }}</td><td class="px-3 py-1.5 text-right"><button type="button" wire:click="startEditAccount({{ $a->id }})" class="text-[10px] text-brand-600">Edit</button></td></tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-3 text-center text-sm text-slate-500">No accounts.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>

        @elseif($tab === 'types')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Code</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Series prefix</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($voucherTypes as $vt)
                        <tr><td class="px-5 py-2 font-mono text-xs">{{ $vt->code }}</td><td class="px-4 py-2">{{ $vt->name }}</td><td class="px-4 py-2 text-xs">{{ $vt->type }}</td><td class="px-4 py-2 font-mono text-xs">{{ $vt->prefix }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">No voucher types.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($tab === 'reconciliation')
            <div class="p-6">
                <h3 class="font-semibold text-slate-900 mb-3">Bank reconciliation</h3>
                <p class="text-sm text-slate-600 mb-4">Match GL bank-account entries to the bank statement. Upload statement (.csv) or enter the closing balance to start a new reconciliation.</p>

                @if(session('success'))<div class="mb-4 px-4 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="mb-4 px-4 py-2 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded">{{ session('error') }}</div>@endif

                <div class="grid md:grid-cols-3 gap-4 max-w-3xl">
                    <div><label class="block text-xs font-medium mb-1">Bank account</label>
                        <select wire:model="br_account_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">Select…</option>
                            @foreach($bankAccounts as $b)<option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>@endforeach
                        </select>
                        @error('br_account_id')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div><label class="block text-xs font-medium mb-1">Statement date</label><input type="date" wire:model="br_statement_date" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-xs font-medium mb-1">Opening balance (₹)</label><input type="number" step="0.01" wire:model="br_opening_balance" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-xs font-medium mb-1">Statement closing balance (₹)</label><input type="number" step="0.01" wire:model="br_statement_balance" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div class="md:col-span-3 pt-3 border-t flex gap-3 items-center flex-wrap">
                        <button type="button" wire:click="startReconciliation" class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Start reconciliation</button>

                        <label class="border border-slate-300 px-5 py-2 rounded-lg text-sm cursor-pointer hover:bg-slate-50">
                            <span wire:loading.remove wire:target="br_statement_file">Upload statement (.csv)</span>
                            <span wire:loading wire:target="br_statement_file">Parsing…</span>
                            <input type="file" wire:model="br_statement_file" accept=".csv,text/csv" class="hidden">
                        </label>
                        @error('br_statement_file')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

                        @if($br_active_statement_id)
                            <span class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded">Active rec: #{{ $br_active_statement_id }}</span>
                        @endif
                    </div>
                </div>

                @if(!empty($br_preview_lines))
                    <div class="mt-6 max-w-3xl">
                        <div class="font-semibold text-slate-900 mb-2">Preview ({{ count($br_pending_lines) }} lines, first 5 shown)</div>
                        <table class="w-full text-sm bg-white rounded-xl border overflow-hidden">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                                <tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Description</th><th class="px-4 py-2">Type</th><th class="px-4 py-2 text-right">Amount</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($br_preview_lines as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-xs">{{ $row['date'] ?? '—' }}</td>
                                        <td class="px-4 py-2 text-xs">{{ $row['description'] ?? '' }}</td>
                                        <td class="px-4 py-2 text-xs uppercase">{{ $row['type'] ?? '' }}</td>
                                        <td class="px-4 py-2 text-xs text-right font-medium">₹{{ number_format((float)($row['amount'] ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="flex gap-3 mt-3">
                            <button type="button" wire:click="confirmStatementImport" class="bg-emerald-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Confirm import ({{ count($br_pending_lines) }})</button>
                            <button type="button" wire:click="cancelStatementImport" class="border border-slate-300 px-5 py-2 rounded-lg text-sm">Discard</button>
                        </div>
                    </div>
                @endif

                @if($activeStatementLines->count())
                    <div class="mt-8 max-w-4xl" x-data="{ openMatch: null, openManual: null, manualReason: '' }">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-slate-900">Statement lines (rec #{{ $br_active_statement_id }})</div>
                            @if($activeStatement && ($activeStatement->status ?? 'open') !== 'closed')
                                <button type="button"
                                    wire:click="closeReconciliation({{ $br_active_statement_id }}, {{ (float) ($activeStatement->closing_balance ?? 0) }})"
                                    wire:confirm="Close reconciliation #{{ $br_active_statement_id }}?"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-4 py-1.5 rounded">
                                    Close reconciliation
                                </button>
                            @else
                                <span class="text-xs uppercase tracking-wider px-2 py-1 rounded bg-slate-200 text-slate-700">Closed</span>
                            @endif
                        </div>
                        <table class="w-full text-sm bg-white rounded-xl border overflow-hidden">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                                <tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Description</th><th class="px-4 py-2">Type</th><th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">Action</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($activeStatementLines as $tx)
                                    <tr>
                                        <td class="px-4 py-2 text-xs">{{ \Carbon\Carbon::parse($tx->transaction_date)->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-xs">{{ $tx->description }}</td>
                                        <td class="px-4 py-2 text-xs uppercase">{{ $tx->type }}</td>
                                        <td class="px-4 py-2 text-xs text-right font-medium">₹{{ number_format((float) $tx->amount, 2) }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            @if($tx->match_status === 'unmatched')
                                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-amber-100 text-amber-700">Unmatched</span>
                                            @elseif($tx->match_status === 'manual_matched')
                                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-sky-100 text-sky-700">Manual ✓</span>
                                            @else
                                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">✓ {{ str_replace('_',' ', $tx->match_status) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-xs text-right relative">
                                            @if($tx->match_status === 'unmatched')
                                                @php $cands = $matchCandidates[$tx->id] ?? collect(); @endphp
                                                <div class="inline-block relative">
                                                    <button type="button"
                                                        @click="openMatch === {{ $tx->id }} ? openMatch = null : (openMatch = {{ $tx->id }}, openManual = null)"
                                                        class="text-brand-600 hover:underline">Match ▾</button>
                                                    <div x-show="openMatch === {{ $tx->id }}" x-cloak @click.outside="openMatch = null"
                                                        class="absolute right-0 mt-1 w-72 bg-white border rounded-lg shadow-lg z-10 p-2 text-left">
                                                        <div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1 px-1">Candidates (±3 days, ±₹0.5)</div>
                                                        @forelse($cands as $p)
                                                            <button type="button"
                                                                wire:click="matchTransaction({{ $tx->id }}, {{ $p->id }})"
                                                                class="w-full px-2 py-1.5 hover:bg-brand-50 rounded text-xs flex justify-between items-center">
                                                                <span class="font-mono">{{ $p->receipt_number }}</span>
                                                                <span class="text-slate-500">{{ \Carbon\Carbon::parse($p->payment_date)->format('d M') }} · {{ $p->mode }}</span>
                                                                <span class="font-semibold">₹{{ number_format((float)$p->amount, 2) }}</span>
                                                            </button>
                                                        @empty
                                                            <div class="text-xs text-slate-400 px-2 py-2">No payment within ±3 days / ±₹0.5.</div>
                                                        @endforelse
                                                        <div class="mt-2 pt-2 border-t">
                                                            <button type="button"
                                                                @click="openManual = {{ $tx->id }}; openMatch = null"
                                                                class="text-xs text-slate-600 hover:text-slate-900 px-2">Mark manually (no payment row)…</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div x-show="openManual === {{ $tx->id }}" x-cloak @click.outside="openManual = null"
                                                    class="absolute right-0 mt-1 w-72 bg-white border rounded-lg shadow-lg z-10 p-3 text-left">
                                                    <div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Reason (e.g. bank fee)</div>
                                                    <input type="text" x-model="manualReason" placeholder="Bank fee / interest / refund…" class="w-full px-2 py-1.5 border rounded text-xs">
                                                    <div class="flex gap-2 mt-2 justify-end">
                                                        <button type="button" @click="openManual = null" class="text-xs px-2">Cancel</button>
                                                        <button type="button"
                                                            @click="$wire.markManualMatch({{ $tx->id }}, manualReason); manualReason = ''; openManual = null;"
                                                            class="bg-brand-600 text-white text-xs font-semibold px-3 py-1 rounded">Save</button>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-emerald-600 text-base mr-1">✓</span>
                                                <button type="button"
                                                    wire:click="unmatchTransaction({{ $tx->id }})"
                                                    wire:confirm="Clear match?"
                                                    class="text-[10px] text-rose-600 hover:underline">Unmatch</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($bankStatements->count())
                    <div class="mt-8 max-w-4xl">
                        <div class="font-semibold text-slate-900 mb-2">Recent reconciliations</div>
                        <table class="w-full text-sm bg-white rounded-xl border overflow-hidden">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                                <tr><th class="px-4 py-2">Rec #</th><th class="px-4 py-2">Statement date</th><th class="px-4 py-2 text-right">Opening</th><th class="px-4 py-2 text-right">Closing</th><th class="px-4 py-2">Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($bankStatements as $bs)
                                    <tr>
                                        <td class="px-4 py-2 text-xs font-mono">#{{ $bs->id }}</td>
                                        <td class="px-4 py-2 text-xs">{{ \Carbon\Carbon::parse($bs->statement_date)->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-xs text-right">₹{{ number_format((float) $bs->opening_balance, 2) }}</td>
                                        <td class="px-4 py-2 text-xs text-right">₹{{ number_format((float) $bs->closing_balance, 2) }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ ($bs->status ?? 'open') === 'closed' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-700' }}">{{ $bs->status ?? 'open' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        @else
            <div class="p-6">
                <div class="flex items-baseline justify-between mb-3">
                    <div>
                        <h3 class="font-semibold">GST returns</h3>
                        <p class="text-sm text-slate-600">Generate GSTR-1 / GSTR-3B drafts from folio charges, mark them filed once submitted on the GST portal, and keep an auditable filing log per property.</p>
                    </div>
                    <a href="{{ route('reports.tax') }}" class="text-xs text-brand-600 hover:underline whitespace-nowrap">View detailed report →</a>
                </div>

                <div class="bg-brand-50 border border-brand-200 rounded-xl p-4 mb-5">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1">Return period (YYYY-MM)</label>
                            <input type="month" wire:model="gst_period" class="px-3 py-2 border rounded-lg text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Return type</label>
                            <select wire:model="gst_type" class="px-3 py-2 border rounded-lg text-sm bg-white">
                                <option value="GSTR-1">GSTR-1 (outward supplies)</option>
                                <option value="GSTR-3B">GSTR-3B (summary)</option>
                                <option value="GSTR-9">GSTR-9 (annual)</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="generateReturn(gst_period, 'GSTR-1')" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Generate GSTR-1</button>
                            <button type="button" wire:click="generateReturn(gst_period, 'GSTR-3B')" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Generate GSTR-3B</button>
                            <button type="button" wire:click="generateGstReturn" class="border border-slate-300 px-4 py-2 rounded-lg text-sm">Generate selected</button>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-2">Generation aggregates non-voided <code>folio_charges</code> for the period and creates a draft filing row with the JSON payload. Marking filed records the GST portal acknowledgement number and locks the row.</p>
                </div>

                <div class="bg-white rounded-xl border overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                            <tr>
                                <th class="px-4 py-2.5">Period</th>
                                <th class="px-4 py-2.5">Type</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Taxable (₹)</th>
                                <th class="px-4 py-2.5 text-right">Tax (₹)</th>
                                <th class="px-4 py-2.5">ACK #</th>
                                <th class="px-4 py-2.5">Filed on</th>
                                <th class="px-4 py-2.5">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($gstFilings as $f)
                                @php
                                    $statusCls = match($f->status) {
                                        'draft'     => 'bg-slate-100 text-slate-700',
                                        'generated' => 'bg-amber-100 text-amber-700',
                                        'filed'     => 'bg-emerald-100 text-emerald-700',
                                        'revised'   => 'bg-sky-100 text-sky-700',
                                        default     => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $f->return_period }}</td>
                                    <td class="px-4 py-2 text-xs font-medium">{{ $f->return_type }}</td>
                                    <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $statusCls }}">{{ $f->status }}</span></td>
                                    <td class="px-4 py-2 text-xs text-right">₹{{ number_format((float) $f->taxable_value, 2) }}</td>
                                    <td class="px-4 py-2 text-xs text-right">₹{{ number_format((float) $f->total_tax, 2) }}</td>
                                    <td class="px-4 py-2 text-xs font-mono">{{ $f->ack_number ?: '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-600">{{ $f->filed_at ? \Carbon\Carbon::parse($f->filed_at)->format('d M Y, H:i') : '—' }}</td>
                                    <td class="px-4 py-2 text-xs whitespace-nowrap">
                                        @if($f->status !== 'filed')
                                            <div class="flex items-center gap-1">
                                                <input type="text" wire:model="gst_ack_inputs.{{ $f->id }}" placeholder="ACK #" class="px-2 py-1 border rounded text-xs w-28">
                                                <button type="button"
                                                    wire:click="markFiled({{ $f->id }}, gst_ack_inputs.{{ $f->id }} ?? '')"
                                                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] uppercase tracking-wider font-semibold px-2 py-1 rounded">Mark filed</button>
                                            </div>
                                        @else
                                            <button type="button"
                                                wire:click="revertToDraft({{ $f->id }})"
                                                wire:confirm="Revert this filed return to draft? This clears the ACK number."
                                                class="text-rose-600 hover:underline">Revert</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No GST returns generated yet. Pick a period and click Generate.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
