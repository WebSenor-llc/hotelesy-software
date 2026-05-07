<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelMapping extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'channel',
        'external_hotel_id', 'external_property_code',
        'room_type_id', 'rate_plan_id',
        'external_room_type_id', 'external_rate_plan_id', 'external_meal_plan_code',
        'push_inventory', 'push_rates', 'pull_bookings',
        'last_sync_at', 'last_sync_status', 'last_sync_error',
        'is_active',
    ];

    protected $casts = [
        'push_inventory' => 'boolean',
        'push_rates' => 'boolean',
        'pull_bookings' => 'boolean',
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function roomType(): BelongsTo { return $this->belongsTo(RoomType::class); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(RatePlan::class); }
}
