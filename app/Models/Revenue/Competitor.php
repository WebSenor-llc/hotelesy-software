<?php

namespace App\Models\Revenue;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competitor extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'revenue_competitors';
    protected $guarded = ['id'];
    protected $casts = [
        'distance_km' => 'decimal:2',
        'is_primary_compset' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rateShops(): HasMany
    {
        return $this->hasMany(RateShop::class, 'competitor_id');
    }
}
