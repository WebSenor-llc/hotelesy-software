<?php

namespace App\Models\Revenue;

use App\Models\Concerns\BelongsToTenant;
use App\Models\RatePlan;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'revenue_pricing_rules';
    protected $guarded = ['id'];

    public const TYPE_OCCUPANCY = 'occupancy_based';
    public const TYPE_DAYS_TO_ARRIVAL = 'days_to_arrival';
    public const TYPE_DAY_OF_WEEK = 'day_of_week';
    public const TYPE_SEASON = 'season';
    public const TYPE_EVENT = 'event';
    public const TYPE_COMPSET = 'compset_position';
    public const TYPE_FLOOR = 'min_max_floor';

    public const ACTION_INC_PCT = 'increase_percent';
    public const ACTION_DEC_PCT = 'decrease_percent';
    public const ACTION_SET_TO = 'set_to';
    public const ACTION_INC_FIXED = 'increase_fixed';
    public const ACTION_DEC_FIXED = 'decrease_fixed';

    protected $casts = [
        'conditions' => 'array',
        'value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->where(function ($qq) {
            $qq->whereNull('valid_from')->orWhere('valid_from', '<=', now()->toDateString());
        })->where(function ($qq) {
            $qq->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
        });
    }

    /**
     * Apply the rule's action to a base rate, returning the adjusted rate.
     */
    public function applyTo(float $baseRate): float
    {
        return match ($this->action) {
            self::ACTION_INC_PCT => round($baseRate * (1 + ((float) $this->value / 100)), 2),
            self::ACTION_DEC_PCT => round($baseRate * (1 - ((float) $this->value / 100)), 2),
            self::ACTION_INC_FIXED => round($baseRate + (float) $this->value, 2),
            self::ACTION_DEC_FIXED => round(max(0, $baseRate - (float) $this->value), 2),
            self::ACTION_SET_TO => round((float) $this->value, 2),
            default => $baseRate,
        };
    }
}
