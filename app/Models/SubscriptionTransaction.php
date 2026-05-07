<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionTransaction extends Model
{
    use HasFactory;

    protected $table = 'subscription_transactions';
    protected $guarded = ['id'];

    protected $casts = [
        'amount'             => 'decimal:2',
        'tax_amount'         => 'decimal:2',
        'razorpay_payload'   => 'array',
        'is_mandate'         => 'boolean',
        'paid_at'            => 'datetime',
    ];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED   = 'captured';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_REFUNDED   = 'refunded';
    public const STATUS_CANCELLED  = 'cancelled';

    public const TYPE_MANDATE      = 'mandate_auth';
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_ONE_TIME     = 'one_time';
    public const TYPE_REFUND       = 'refund';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'lead_id');
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_CAPTURED   => 'bg-emerald-100 text-emerald-700',
            self::STATUS_AUTHORIZED => 'bg-sky-100 text-sky-700',
            self::STATUS_PENDING    => 'bg-amber-100 text-amber-700',
            self::STATUS_FAILED     => 'bg-rose-100 text-rose-700',
            self::STATUS_REFUNDED   => 'bg-violet-100 text-violet-700',
            self::STATUS_CANCELLED  => 'bg-slate-100 text-slate-600',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'HTL-' . now()->format('Ym');
        $last = static::where('invoice_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('invoice_number');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $seq);
    }
}
