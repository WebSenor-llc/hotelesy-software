<?php

namespace App\Models\Revenue;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'revenue_forecasts';
    protected $guarded = ['id'];
    protected $casts = [
        'forecast_date' => 'date',
        'stay_date' => 'date',
        'forecast_occupancy_pct' => 'decimal:2',
        'forecast_arr' => 'decimal:2',
        'forecast_revpar' => 'decimal:2',
        'booking_pace' => 'array',
    ];
}
