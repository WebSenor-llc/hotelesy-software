<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioCharge extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'folio_id',
        'charge_date', 'charge_time', 'business_date',
        'category', 'description', 'reference',
        'quantity', 'rate', 'amount',
        'discount_amount', 'tax_amount', 'net_amount',
        'tax_breakdown',
        'is_voided', 'voided_at', 'voided_by', 'void_reason',
        'posted_by', 'device_id',
    ];

    protected $casts = [
        'charge_date' => 'date',
        'charge_time' => 'string',
        'business_date' => 'date',
        'quantity' => 'decimal:3',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tax_breakdown' => 'array',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
