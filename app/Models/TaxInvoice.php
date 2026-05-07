<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxInvoice extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date'    => 'date',
        'due_date'        => 'date',
        'gross_amount'    => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount'  => 'decimal:2',
        'cgst_total'      => 'decimal:2',
        'sgst_total'      => 'decimal:2',
        'igst_total'      => 'decimal:2',
        'cess_total'      => 'decimal:2',
        'tcs_amount'      => 'decimal:2',
        'round_off'       => 'decimal:2',
        'grand_total'     => 'decimal:2',
        'amount_paid'     => 'decimal:2',
        'balance_due'     => 'decimal:2',
        'exchange_rate'   => 'decimal:4',
        'is_inter_state'  => 'boolean',
        'is_reverse_charge'=> 'boolean',
        'is_export'       => 'boolean',
        'is_sez'          => 'boolean',
        'e_invoice_required'=> 'boolean',
        'irn_generated_at'=> 'datetime',
        'issued_at'       => 'datetime',
        'cancelled_at'    => 'datetime',
        'signed_qr'       => 'array',
        'metadata'        => 'array',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ISSUED    = 'issued';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_AMENDED   = 'amended';

    public const TYPE_TAX_INVOICE   = 'tax_invoice';
    public const TYPE_BILL_OF_SUPPLY= 'bill_of_supply';
    public const TYPE_CREDIT_NOTE   = 'credit_note';
    public const TYPE_DEBIT_NOTE    = 'debit_note';
    public const TYPE_PROFORMA      = 'proforma';

    public function lines(): HasMany
    {
        return $this->hasMany(TaxInvoiceLine::class)->orderBy('line_no');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ISSUED    => 'bg-emerald-100 text-emerald-700',
            self::STATUS_DRAFT     => 'bg-amber-100 text-amber-700',
            self::STATUS_CANCELLED => 'bg-rose-100 text-rose-700',
            self::STATUS_AMENDED   => 'bg-violet-100 text-violet-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Convert numeric amount to Indian-numbering English words
     * (Lakh / Crore convention required on tax invoices).
     */
    public static function amountToWords(float $amount): string
    {
        $amount = round($amount, 2);
        $rupees = (int) floor($amount);
        $paise  = (int) round(($amount - $rupees) * 100);

        $words  = self::indianNumberToWords($rupees);
        $result = ucfirst(trim($words)) . ' Rupees';
        if ($paise > 0) {
            $result .= ' and ' . trim(self::indianNumberToWords($paise)) . ' Paise';
        }
        return $result . ' Only';
    }

    private static function indianNumberToWords(int $n): string
    {
        if ($n === 0) return 'Zero';
        $units = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
        $tens  = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

        $w = function ($n) use (&$w, $units, $tens) {
            if ($n < 20) return $units[$n];
            if ($n < 100) return $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $units[$n % 10] : '');
            return $units[intdiv($n, 100)] . ' Hundred' . ($n % 100 ? ' ' . $w($n % 100) : '');
        };

        $crore = intdiv($n, 10000000);   $n %= 10000000;
        $lakh  = intdiv($n, 100000);     $n %= 100000;
        $thou  = intdiv($n, 1000);       $n %= 1000;

        $parts = [];
        if ($crore) $parts[] = $w($crore) . ' Crore';
        if ($lakh)  $parts[] = $w($lakh)  . ' Lakh';
        if ($thou)  $parts[] = $w($thou)  . ' Thousand';
        if ($n)     $parts[] = $w($n);

        return implode(' ', $parts);
    }
}
