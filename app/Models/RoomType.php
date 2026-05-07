<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomType extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'code', 'name', 'description',
        'base_occupancy', 'max_occupancy', 'max_adults', 'max_children',
        'extra_bed_capacity',
        'base_rate', 'extra_adult_rate', 'extra_child_rate', 'extra_bed_rate',
        'size_sqft', 'bed_type', 'amenities', 'photos',
        'display_order', 'is_active', 'sell_on_channels', 'channel_mappings',
        'allow_overbook', 'overbook_limit',
    ];

    protected $casts = [
        'base_occupancy' => 'integer',
        'max_occupancy' => 'integer',
        'max_adults' => 'integer',
        'max_children' => 'integer',
        'extra_bed_capacity' => 'integer',
        'base_rate' => 'decimal:2',
        'extra_adult_rate' => 'decimal:2',
        'extra_child_rate' => 'decimal:2',
        'extra_bed_rate' => 'decimal:2',
        'size_sqft' => 'decimal:2',
        'is_active' => 'boolean',
        'sell_on_channels' => 'boolean',
        'amenities' => 'array',
        'photos' => 'array',
        'channel_mappings' => 'array',
        'allow_overbook' => 'boolean',
        'overbook_limit' => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class);
    }

    public function dailyInventory(): HasMany
    {
        return $this->hasMany(DailyInventory::class);
    }
}
