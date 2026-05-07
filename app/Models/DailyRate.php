<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'room_type_id', 'rate_plan_id',
        'date', 'rate', 'extra_adult_rate', 'extra_child_rate',
        'min_stay', 'closed_to_arrival', 'closed_to_departure', 'stop_sell',
        'updated_by', 'last_pushed_to_channels_at',
    ];

    protected $casts = [
        'date' => 'date',
        'rate' => 'decimal:2',
        'extra_adult_rate' => 'decimal:2',
        'extra_child_rate' => 'decimal:2',
        'min_stay' => 'integer',
        'closed_to_arrival' => 'boolean',
        'closed_to_departure' => 'boolean',
        'stop_sell' => 'boolean',
        'last_pushed_to_channels_at' => 'datetime',
    ];

    public function ratePlan(): BelongsTo { return $this->belongsTo(RatePlan::class); }
    public function roomType(): BelongsTo { return $this->belongsTo(RoomType::class); }
}
