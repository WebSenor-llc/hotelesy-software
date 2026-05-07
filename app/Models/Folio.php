<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Folio extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'tenant_id', 'property_id', 'reservation_id', 'reservation_room_id',
        'folio_number', 'type',
        'guest_id', 'company_id',
        'billing_name', 'billing_address', 'billing_gst',
        'total_charges', 'total_taxes', 'total_discounts',
        'total_payments', 'balance', 'currency',
        'status', 'closed_at', 'closed_by',
        'invoice_number', 'invoice_generated_at',
        'notes',
    ];

    protected $casts = [
        'total_charges' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'total_discounts' => 'decimal:2',
        'total_payments' => 'decimal:2',
        'balance' => 'decimal:2',
        'closed_at' => 'datetime',
        'invoice_generated_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reservationRoom(): BelongsTo
    {
        return $this->belongsTo(ReservationRoom::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(FolioCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isSettled(): bool
    {
        return $this->balance == 0 && $this->status === self::STATUS_SETTLED;
    }

    /**
     * Recompute totals from charges + payments.
     * Should be called inside a transaction after any charge/payment change.
     */
    public function recomputeTotals(): void
    {
        $charges = $this->charges()->where('is_voided', false);
        $totalCharges = $charges->sum('amount');
        $totalDiscounts = $charges->sum('discount_amount');
        $totalTaxes = $charges->sum('tax_amount');

        $totalPayments = $this->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $charges = $this->charges()->where('is_voided', false)->get();
        $netCharges = $charges->sum('net_amount');

        $this->update([
            'total_charges' => $totalCharges,
            'total_discounts' => $totalDiscounts,
            'total_taxes' => $totalTaxes,
            'total_payments' => $totalPayments,
            'balance' => $netCharges - $totalPayments,
        ]);
    }
}
