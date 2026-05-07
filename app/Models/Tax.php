<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tax extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'code', 'name', 'type', 'rate',
        'is_inclusive', 'is_compoundable',
        'threshold_min', 'threshold_max',
        'applies_to_room', 'applies_to_food', 'applies_to_other',
        'is_active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'rate' => 'decimal:3',
        'is_inclusive' => 'boolean',
        'is_compoundable' => 'boolean',
        'threshold_min' => 'decimal:2',
        'threshold_max' => 'decimal:2',
        'applies_to_room' => 'boolean',
        'applies_to_food' => 'boolean',
        'applies_to_other' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function appliesToAmount(float $amount): bool
    {
        if (!$this->is_active) return false;
        if ($this->threshold_min !== null && $amount < $this->threshold_min) return false;
        if ($this->threshold_max !== null && $amount > $this->threshold_max) return false;
        return true;
    }
}
