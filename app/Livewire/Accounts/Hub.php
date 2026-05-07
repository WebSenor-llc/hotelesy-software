<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\ChartAccount;
use App\Models\Accounts\Voucher;
use App\Models\Accounts\VoucherEntry;
use App\Models\Accounts\VoucherType;
use App\Models\Payment;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app-shell')]
class Hub extends Component
{
    use WithFileUploads;

    public string $tab = 'vouchers';

    // Post-voucher form
    public bool $showVoucherForm = false;
    public ?int $vt_voucher_type_id = null;
    public string $vt_date = '';
    public string $vt_narration = '';
    public array $vt_lines = [];

    // Account form
    public bool $showAccountForm = false;
    public ?int $a_id = null;
    public string $a_code = ''; public string $a_name = '';
    public string $a_type = 'asset'; public string $a_subtype = 'current_asset';
    public float $a_opening_balance = 0; public bool $a_is_active = true;

    // Bank rec
    public ?int $br_account_id = null;
    public string $br_statement_date = '';
    public float $br_statement_balance = 0;
    public float $br_opening_balance = 0;
    public ?int $br_active_statement_id = null;
    public $br_statement_file = null; // Livewire upload
    public array $br_preview_lines = [];
    public array $br_pending_lines = []; // full parse

    // GST returns
    public string $gst_period = '';        // YYYY-MM
    public string $gst_type = 'GSTR-1';    // GSTR-1 | GSTR-3B | GSTR-9
    public array $gst_ack_inputs = [];     // [filingId => ackNumber]

    public function mount(): void
    {
        $this->vt_date = today()->toDateString();
        $this->br_statement_date = today()->toDateString();
        $this->gst_period = today()->copy()->startOfMonth()->subMonth()->format('Y-m');
        $this->resetVoucherLines();
    }

    public function resetVoucherLines(): void
    {
        $this->vt_lines = [
            ['account_id' => null, 'description' => '', 'debit' => 0, 'credit' => 0],
            ['account_id' => null, 'description' => '', 'debit' => 0, 'credit' => 0],
        ];
    }
    public function addVoucherLine(): void { $this->vt_lines[] = ['account_id' => null, 'description' => '', 'debit' => 0, 'credit' => 0]; }
    public function removeVoucherLine(int $i): void { unset($this->vt_lines[$i]); $this->vt_lines = array_values($this->vt_lines); }

    public function startNewVoucher(): void { $this->resetVoucherLines(); $this->vt_narration=''; $this->showVoucherForm = true; }
    public function cancelVoucher(): void { $this->showVoucherForm = false; }

    public function saveVoucher(): void
    {
        $data = $this->validate([
            'vt_voucher_type_id' => 'required|exists:accounts_voucher_types,id',
            'vt_date'            => 'required|date',
            'vt_narration'       => 'required|string|max:500',
        ]);

        $debit = collect($this->vt_lines)->sum(fn($l) => (float)($l['debit'] ?? 0));
        $credit = collect($this->vt_lines)->sum(fn($l) => (float)($l['credit'] ?? 0));
        if (round($debit, 2) !== round($credit, 2)) {
            session()->flash('error', "Debits (₹{$debit}) must equal credits (₹{$credit}). Voucher not balanced.");
            return;
        }
        if ($debit <= 0) {
            session()->flash('error', 'Voucher has no amounts.');
            return;
        }

        $ctx = app(TenantContext::class);
        $vt = VoucherType::findOrFail($data['vt_voucher_type_id']);
        $date = \Carbon\Carbon::parse($data['vt_date']);

        DB::transaction(function () use ($ctx, $vt, $data, $date, $debit, $credit) {
            $voucher = Voucher::create([
                'tenant_id' => $ctx->tenantId(),
                'property_id' => $ctx->propertyId(),
                'voucher_type_id' => $vt->id,
                'voucher_number' => $vt->prefix.'-'.$date->format('ymd').'-'.str_pad((string) (Voucher::where('voucher_type_id', $vt->id)->count() + 1), 3, '0', STR_PAD_LEFT),
                'voucher_date' => $date,
                'business_date' => $date,
                'reference_type' => 'manual',
                'narration' => $data['vt_narration'],
                'total_debit' => $debit,
                'total_credit' => $credit,
                'status' => 'posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            foreach ($this->vt_lines as $line) {
                if (!($line['account_id'] ?? null)) continue;
                if ((float)($line['debit'] ?? 0) <= 0 && (float)($line['credit'] ?? 0) <= 0) continue;
                VoucherEntry::create([
                    'tenant_id' => $ctx->tenantId(),
                    'property_id' => $ctx->propertyId(),
                    'voucher_id' => $voucher->id,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? '',
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }
        });

        $this->showVoucherForm = false;
        session()->flash('success', 'Voucher posted.');
    }

    public function reverseVoucher(int $id): void
    {
        $v = Voucher::findOrFail($id);
        if ($v->status !== 'posted') return;
        $v->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
        session()->flash('success', "Voucher {$v->voucher_number} reversed.");
    }

    // Account CRUD
    public function startNewAccount(): void { $this->reset(['a_id','a_code','a_name','a_opening_balance']); $this->a_type='asset'; $this->a_subtype='current_asset'; $this->a_is_active=true; $this->showAccountForm = true; }
    public function startEditAccount(int $id): void {
        $a = ChartAccount::findOrFail($id); $this->a_id = $id;
        foreach (['code','name','type','subtype','opening_balance','is_active'] as $f) $this->{'a_'.$f} = $a->$f;
        $this->showAccountForm = true;
    }
    public function cancelAccount(): void { $this->showAccountForm = false; }

    public function saveAccount(): void
    {
        $data = $this->validate([
            'a_code'=>'required|string|max:20','a_name'=>'required|string|max:255',
            'a_type'=>'required|in:asset,liability,equity,income,expense',
            'a_subtype'=>'nullable|string',
            'a_opening_balance'=>'numeric','a_is_active'=>'boolean',
        ]);
        $ctx = app(TenantContext::class);
        $payload = [
            'code'=>$data['a_code'],'name'=>$data['a_name'],
            'type'=>$data['a_type'],'subtype'=>$data['a_subtype'],
            'opening_balance'=>$data['a_opening_balance'],
            'is_active'=>$data['a_is_active'],
            'tenant_id'=>$ctx->tenantId(),'property_id'=>$ctx->propertyId(),
        ];
        if ($this->a_id) ChartAccount::findOrFail($this->a_id)->update($payload);
        else ChartAccount::create($payload);
        $this->showAccountForm = false;
        session()->flash('success', 'Account saved.');
    }

    // ===================== Bank Reconciliation =====================

    public function startReconciliation(): void
    {
        $data = $this->validate([
            'br_account_id' => 'required|exists:accounts_chart,id',
            'br_statement_date' => 'required|date',
            'br_statement_balance' => 'numeric',
            'br_opening_balance' => 'numeric',
        ]);

        $ctx = app(TenantContext::class);
        $id = DB::table('accounts_bank_statements')->insertGetId([
            'tenant_id' => $ctx->tenantId(),
            'property_id' => $ctx->propertyId(),
            'account_id' => $data['br_account_id'],
            'statement_date' => $data['br_statement_date'],
            'opening_balance' => $data['br_opening_balance'] ?? 0,
            'closing_balance' => $data['br_statement_balance'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->br_active_statement_id = (int) $id;
        session()->flash('success', "Reconciliation #{$id} started. Upload statement CSV to add lines.");
    }

    public function updatedBrStatementFile(): void
    {
        $this->parseUploadedCsv();
    }

    protected function parseUploadedCsv(): void
    {
        $this->br_preview_lines = [];
        $this->br_pending_lines = [];

        if (! $this->br_statement_file) return;

        $path = $this->br_statement_file->getRealPath();
        if (! is_readable($path)) return;

        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            $header = fgetcsv($h);
            // Tolerate optional header. We expect: date, description, amount, type
            $headerLooksLikeData = is_array($header) && count($header) >= 3 && strtotime($header[0] ?? '') !== false;
            if ($headerLooksLikeData) {
                rewind($h);
                $header = ['date', 'description', 'amount', 'type'];
            } else {
                $header = array_map(fn($s) => strtolower(trim((string) $s)), $header ?: []);
            }
            while (($row = fgetcsv($h)) !== false) {
                if (count(array_filter($row, fn($c) => $c !== null && $c !== '')) === 0) continue;
                $assoc = [];
                foreach ($row as $i => $val) {
                    $key = $header[$i] ?? "col_{$i}";
                    $assoc[$key] = is_string($val) ? trim($val) : $val;
                }
                $rows[] = [
                    'date'        => $assoc['date'] ?? ($assoc['transaction_date'] ?? null),
                    'description' => $assoc['description'] ?? ($assoc['narration'] ?? ''),
                    'amount'      => (float) preg_replace('/[^0-9.\-]/', '', (string)($assoc['amount'] ?? 0)),
                    'type'        => strtolower($assoc['type'] ?? ((float)($assoc['amount'] ?? 0) < 0 ? 'debit' : 'credit')),
                ];
            }
            fclose($h);
        }

        $this->br_pending_lines = $rows;
        $this->br_preview_lines = array_slice($rows, 0, 5);
    }

    public function confirmStatementImport(): void
    {
        if (! $this->br_active_statement_id) {
            session()->flash('error', 'Start a reconciliation before importing.');
            return;
        }
        if (empty($this->br_pending_lines)) {
            session()->flash('error', 'No statement lines parsed.');
            return;
        }

        $now = now();
        $insert = [];
        foreach ($this->br_pending_lines as $row) {
            $date = $row['date'] ?: now()->toDateString();
            try { $date = \Carbon\Carbon::parse($date)->toDateString(); } catch (\Throwable) { $date = now()->toDateString(); }
            $amount = abs((float)($row['amount'] ?? 0));
            $type = in_array($row['type'] ?? '', ['debit','credit']) ? $row['type'] : ((float)($row['amount'] ?? 0) < 0 ? 'debit' : 'credit');
            $insert[] = [
                'statement_id'    => $this->br_active_statement_id,
                'transaction_date'=> $date,
                'type'            => $type,
                'amount'          => $amount,
                'reference'       => null,
                'description'     => substr((string)($row['description'] ?? ''), 0, 255),
                'match_status'    => 'unmatched',
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('accounts_bank_transactions')->insert($insert);
        $count = count($insert);
        $this->br_pending_lines = [];
        $this->br_preview_lines = [];
        $this->br_statement_file = null;
        session()->flash('success', "Imported {$count} statement lines.");
    }

    public function cancelStatementImport(): void
    {
        $this->br_pending_lines = [];
        $this->br_preview_lines = [];
        $this->br_statement_file = null;
    }

    public function matchTransaction(int $transactionId, int $paymentId): void
    {
        $tx = DB::table('accounts_bank_transactions')->where('id', $transactionId)->first();
        if (!$tx) { session()->flash('error', 'Transaction not found.'); return; }
        $payment = Payment::find($paymentId);
        if (!$payment) { session()->flash('error', 'Payment not found.'); return; }

        DB::table('accounts_bank_transactions')->where('id', $transactionId)->update([
            'matched_payment_id' => $paymentId,
            'match_status'       => 'manual_matched',
            'matched_at'         => now(),
            'matched_by'         => auth()->id(),
            'updated_at'         => now(),
        ]);
        session()->flash('success', "Transaction matched to payment {$payment->receipt_number}.");
    }

    public function unmatchTransaction(int $transactionId): void
    {
        DB::table('accounts_bank_transactions')->where('id', $transactionId)->update([
            'matched_payment_id' => null,
            'matched_voucher_id' => null,
            'match_status'       => 'unmatched',
            'matched_at'         => null,
            'matched_by'         => null,
            'match_reason'       => null,
            'updated_at'         => now(),
        ]);
        session()->flash('success', 'Match cleared.');
    }

    public function markManualMatch(int $transactionId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') { session()->flash('error', 'A reason is required for manual match.'); return; }
        DB::table('accounts_bank_transactions')->where('id', $transactionId)->update([
            'match_status' => 'manual_matched',
            'matched_at'   => now(),
            'matched_by'   => auth()->id(),
            'match_reason' => substr($reason, 0, 255),
            'updated_at'   => now(),
        ]);
        session()->flash('success', 'Marked as manually matched.');
    }

    public function closeReconciliation(int $statementId, float $closingBalance): void
    {
        DB::table('accounts_bank_statements')->where('id', $statementId)->update([
            'closing_balance' => $closingBalance,
            'status'          => 'closed',
            'closed_at'       => now(),
            'closed_by'       => auth()->id(),
            'updated_at'      => now(),
        ]);
        if ($this->br_active_statement_id === $statementId) {
            $this->br_active_statement_id = null;
        }
        session()->flash('success', "Reconciliation #{$statementId} closed.");
    }

    // ===================== GST returns =====================

    public function generateReturn(string $period, string $type): void
    {
        $period = trim($period);
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            session()->flash('error', 'Period must be in YYYY-MM format (e.g. 2026-04).');
            return;
        }
        if (!in_array($type, ['GSTR-1', 'GSTR-3B', 'GSTR-9'], true)) {
            session()->flash('error', 'Unsupported return type.');
            return;
        }

        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $tenantId   = $ctx->tenantId();

        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        } catch (\Throwable $e) {
            session()->flash('error', 'Invalid period.');
            return;
        }
        $end = $start->copy()->endOfMonth();

        // Aggregate from folio_charges in the period (skip voided)
        $rows = DB::table('folio_charges')
            ->where('property_id', $propertyId)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_voided', false)
            ->get(['id', 'folio_id', 'category', 'amount', 'discount_amount', 'tax_amount', 'net_amount', 'tax_breakdown', 'business_date']);

        $taxableValue = 0.0;
        $totalTax = 0.0;
        $byCategory = [];
        $cgst = 0.0;
        $sgst = 0.0;
        $igst = 0.0;

        foreach ($rows as $r) {
            if ($r->category === 'tax') {
                continue; // tax lines are summarised via tax_amount on revenue rows
            }
            $taxable = (float) $r->amount - (float) $r->discount_amount;
            $tax     = (float) $r->tax_amount;
            $taxableValue += $taxable;
            $totalTax     += $tax;
            $byCategory[$r->category] = ($byCategory[$r->category] ?? 0) + $taxable;

            if (!empty($r->tax_breakdown)) {
                $bd = is_array($r->tax_breakdown) ? $r->tax_breakdown : json_decode((string) $r->tax_breakdown, true);
                if (is_array($bd)) {
                    $cgst += (float) ($bd['cgst'] ?? 0);
                    $sgst += (float) ($bd['sgst'] ?? 0);
                    $igst += (float) ($bd['igst'] ?? 0);
                }
            }
        }

        $payload = [
            'period'        => $period,
            'type'          => $type,
            'period_start'  => $start->toDateString(),
            'period_end'    => $end->toDateString(),
            'charge_count'  => $rows->count(),
            'taxable_value' => round($taxableValue, 2),
            'cgst'          => round($cgst, 2),
            'sgst'          => round($sgst, 2),
            'igst'          => round($igst, 2),
            'total_tax'     => round($totalTax, 2),
            'by_category'   => $byCategory,
            'generated_at'  => now()->toIso8601String(),
        ];

        DB::table('gst_filings')->insert([
            'tenant_id'     => $tenantId,
            'property_id'   => $propertyId,
            'return_period' => $period,
            'return_type'   => $type,
            'status'        => 'generated',
            'generated_at'  => now(),
            'generated_by'  => auth()->id(),
            'taxable_value' => round($taxableValue, 2),
            'total_tax'     => round($totalTax, 2),
            'json_payload'  => json_encode($payload),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        session()->flash('success', "{$type} draft generated for {$period}: ₹" . number_format($taxableValue, 2) . " taxable, ₹" . number_format($totalTax, 2) . " tax.");
    }

    public function markFiled(int $filingId, string $ackNumber): void
    {
        $ackNumber = trim($ackNumber);
        if ($ackNumber === '') {
            session()->flash('error', 'Acknowledgement number is required to mark as filed.');
            return;
        }

        $row = DB::table('gst_filings')->where('id', $filingId)->first();
        if (!$row) { session()->flash('error', 'Filing not found.'); return; }

        DB::table('gst_filings')->where('id', $filingId)->update([
            'status'     => 'filed',
            'ack_number' => substr($ackNumber, 0, 50),
            'filed_at'   => now(),
            'filed_by'   => auth()->id(),
            'updated_at' => now(),
        ]);

        unset($this->gst_ack_inputs[$filingId]);
        session()->flash('success', "{$row->return_type} for {$row->return_period} marked as filed (ACK: {$ackNumber}).");
    }

    public function revertToDraft(int $filingId): void
    {
        $row = DB::table('gst_filings')->where('id', $filingId)->first();
        if (!$row) { session()->flash('error', 'Filing not found.'); return; }

        DB::table('gst_filings')->where('id', $filingId)->update([
            'status'     => 'draft',
            'filed_at'   => null,
            'filed_by'   => null,
            'ack_number' => null,
            'updated_at' => now(),
        ]);

        session()->flash('success', "{$row->return_type} for {$row->return_period} reverted to draft.");
    }

    public function generateGstReturn(): void
    {
        $this->generateReturn($this->gst_period, $this->gst_type);
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $vouchers = Voucher::where('property_id', $propertyId)->with('voucherType')->orderByDesc('voucher_date')->limit(40)->get();
        $chart    = ChartAccount::where('property_id', $propertyId)->where('is_active', true)->orderBy('type')->orderBy('code')->get()->groupBy('type');
        $voucherTypes = VoucherType::where('property_id', $propertyId)->get();
        $allAccounts = ChartAccount::where('property_id', $propertyId)->where('is_active', true)->orderBy('code')->get();
        $bankAccounts = $allAccounts->where('subtype', 'bank');

        $stats = [
            'vouchers'      => Voucher::where('property_id', $propertyId)->count(),
            'posted_today'  => Voucher::where('property_id', $propertyId)->whereDate('voucher_date', today())->count(),
            'accounts'      => ChartAccount::where('property_id', $propertyId)->count(),
            'voucher_types' => $voucherTypes->count(),
        ];

        $bankStatements = DB::table('accounts_bank_statements')
            ->where('property_id', $propertyId)
            ->orderByDesc('statement_date')
            ->limit(10)
            ->get();

        $activeStatementLines = $this->br_active_statement_id
            ? DB::table('accounts_bank_transactions')
                ->where('statement_id', $this->br_active_statement_id)
                ->orderBy('transaction_date')
                ->get()
            : collect();

        // Active statement meta (status + closing balance) for the close button
        $activeStatement = $this->br_active_statement_id
            ? DB::table('accounts_bank_statements')->where('id', $this->br_active_statement_id)->first()
            : null;

        // For each unmatched line, find candidate payments within ±3 days and amount within ±0.5
        $matchCandidates = [];
        foreach ($activeStatementLines as $tx) {
            if ($tx->match_status !== 'unmatched') continue;
            $txDate = \Carbon\Carbon::parse($tx->transaction_date);
            $amt = (float) $tx->amount;
            $candidates = Payment::where('property_id', $propertyId)
                ->whereDate('payment_date', '>=', $txDate->copy()->subDays(3)->toDateString())
                ->whereDate('payment_date', '<=', $txDate->copy()->addDays(3)->toDateString())
                ->whereBetween('amount', [$amt - 0.5, $amt + 0.5])
                ->where('status', 'completed')
                ->orderBy('payment_date')
                ->limit(10)
                ->get(['id', 'receipt_number', 'amount', 'payment_date', 'mode']);
            $matchCandidates[$tx->id] = $candidates;
        }

        $gstFilings = DB::table('gst_filings')
            ->where('property_id', $propertyId)
            ->orderByDesc('return_period')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('livewire.accounts.hub', compact('vouchers','chart','stats','voucherTypes','allAccounts','bankAccounts','bankStatements','activeStatementLines','activeStatement','matchCandidates','gstFilings'));
    }
}
