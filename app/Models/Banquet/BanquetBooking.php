<?php

namespace App\Models\Banquet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BanquetBooking extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'banquet_bookings';
    protected $guarded = ['id'];

    public const STATUS_ENQUIRY = 'enquiry';
    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'event_date' => 'date',
        'event_start_time' => 'string',
        'event_end_time' => 'string',
        'expected_pax' => 'integer',
        'actual_pax' => 'integer',
        'hall_rent' => 'decimal:2',
        'food_amount' => 'decimal:2',
        'beverage_amount' => 'decimal:2',
        'decor_amount' => 'decimal:2',
        'av_amount' => 'decimal:2',
        'other_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'advance_received' => 'decimal:2',
    ];

    public function hall(): BelongsTo { return $this->belongsTo(BanquetHall::class, 'hall_id'); }
    public function package(): BelongsTo { return $this->belongsTo(BanquetPackage::class); }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function salesOwner(): BelongsTo { return $this->belongsTo(User::class, 'foliosales_owner_id'); }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->advance_received);
    }

    public function recomputeTotals(): void
    {
        $subtotal = (float) $this->hall_rent
            + (float) $this->food_amount
            + (float) $this->beverage_amount
            + (float) $this->decor_amount
            + (float) $this->av_amount
            + (float) $this->other_amount;

        // GST on banquet: 18% (services) by default; food portion gets 5% under restaurant supply.
        // Simple version: 18% on hall+decor+av+other; 5% on food+beverage.
        $serviceTax = round((($this->hall_rent + $this->decor_amount + $this->av_amount + $this->other_amount) * 0.18), 2);
        $foodTax = round((($this->food_amount + $this->beverage_amount) * 0.05), 2);
        $tax = $serviceTax + $foodTax;

        $this->update([
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $tax,
            'total_amount' => round($subtotal + $tax, 2),
        ]);
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->whereDate('event_date', '>=', now()->toDateString())
            ->whereIn('status', [self::STATUS_TENTATIVE, self::STATUS_CONFIRMED]);
    }
}
