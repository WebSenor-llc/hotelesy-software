<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RatePlan extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'room_type_id',
        'code', 'name', 'description',
        'meal_plan', 'pricing_mode', 'base_rate', 'rate_modifier', 'tax_mode',
        'min_stay', 'max_stay', 'advance_booking_days',
        'closed_to_arrival', 'closed_to_departure',
        'refundable', 'cancellation_hours', 'cancellation_charge_percent',
        'is_corporate', 'is_promotional', 'valid_from', 'valid_to',
        'is_active', 'sell_on_channels', 'channel_mappings',
    ];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'rate_modifier' => 'decimal:2',
        'cancellation_charge_percent' => 'decimal:2',
        'min_stay' => 'integer',
        'max_stay' => 'integer',
        'advance_booking_days' => 'integer',
        'cancellation_hours' => 'integer',
        'closed_to_arrival' => 'boolean',
        'closed_to_departure' => 'boolean',
        'refundable' => 'boolean',
        'is_corporate' => 'boolean',
        'is_promotional' => 'boolean',
        'is_active' => 'boolean',
        'sell_on_channels' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'channel_mappings' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Compute rate for a given date - applies pricing_mode logic.
     * Falls back to base_rate; daily_rates table overrides this.
     */
    public function computeRate(?float $baseRoomRate = null): float
    {
        $base = $baseRoomRate ?? $this->roomType?->base_rate ?? 0;

        return match ($this->pricing_mode) {
            'fixed' => (float) $this->base_rate,
            'percentage_of_base' => $base * ((float) $this->rate_modifier / 100),
            'amount_off_base' => max(0, $base - (float) $this->rate_modifier),
            'amount_added' => $base + (float) $this->rate_modifier,
            default => (float) $this->base_rate,
        };
    }

    /**
     * Return the per-night rate for the given RoomType after applying this rate plan's
     * pricing_mode and rate_modifier. Defensive: returns base_rate if anything is off.
     *
     * pricing_mode values (from migration enum):
     *   - 'fixed'              => use rate plan's own base_rate as the absolute price
     *   - 'percentage_of_base' => base * (1 + rate_modifier/100)  (positive = markup, negative = discount)
     *   - 'amount_off_base'    => base - rate_modifier
     *   - 'amount_added'       => base + rate_modifier
     */
    public function effectiveRate(RoomType $rt): float
    {
        $base = (float) ($rt->base_rate ?? 0);
        $modifier = (float) ($this->rate_modifier ?? 0);

        $rate = match ($this->pricing_mode) {
            'fixed' => (float) $this->base_rate,
            'percentage_of_base' => $base * (1 + $modifier / 100),
            'amount_off_base' => $base - $modifier,
            'amount_added' => $base + $modifier,
            default => $base,
        };

        return max(0.0, round($rate, 2));
    }
}
