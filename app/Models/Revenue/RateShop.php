<?php

namespace App\Models\Revenue;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateShop extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'revenue_rate_shop';
    protected $guarded = ['id'];
    protected $casts = [
        'shop_date' => 'date',
        'stay_date' => 'date',
        'rate' => 'decimal:2',
        'available' => 'boolean',
        'raw_data' => 'array',
    ];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class, 'competitor_id');
    }
}
