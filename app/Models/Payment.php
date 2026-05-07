<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const MODE_CASH = 'cash';
    public const MODE_CARD = 'card';
    public const MODE_UPI = 'upi';
    public const MODE_BANK_TRANSFER = 'bank_transfer';
    public const MODE_CHEQUE = 'cheque';
    public const MODE_COMPANY_CREDIT = 'company_credit';
    public const MODE_OTA_COLLECT = 'ota_collect';

    protected $fillable = [
        'tenant_id', 'property_id', 'folio_id', 'reservation_id',
        'receipt_number', 'payment_date', 'business_date',
        'mode', 'amount', 'tds_amount', 'tds_section', 'currency',
        'card_last4', 'card_brand', 'card_holder_name', 'approval_code',
        'upi_reference', 'transaction_reference', 'gateway_payment_id',
        'cheque_number', 'cheque_date', 'bank_name',
        'company_id', 'status', 'notes',
        'received_by', 'device_id',
        'payable_type', 'payable_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'business_date' => 'date',
        'cheque_date' => 'date',
        'amount' => 'decimal:2',
        'tds_amount' => 'decimal:2',
    ];

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
