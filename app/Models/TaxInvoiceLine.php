<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxInvoiceLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity'        => 'decimal:3',
        'rate'            => 'decimal:2',
        'gross_amount'    => 'decimal:2',
        'discount_pct'    => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount'  => 'decimal:2',
        'cgst_rate'       => 'decimal:3',
        'cgst_amount'     => 'decimal:2',
        'sgst_rate'       => 'decimal:3',
        'sgst_amount'     => 'decimal:2',
        'igst_rate'       => 'decimal:3',
        'igst_amount'     => 'decimal:2',
        'cess_rate'       => 'decimal:3',
        'cess_amount'     => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'metadata'        => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TaxInvoice::class, 'tax_invoice_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'tax_rule_id');
    }
}
