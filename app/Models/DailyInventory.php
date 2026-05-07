<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyInventory extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'daily_inventory';

    protected $fillable = [
        'tenant_id', 'property_id', 'room_type_id', 'date',
        'total_rooms', 'rooms_sold', 'rooms_blocked', 'rooms_held',
        'overbooking_allowed', 'stop_sell', 'last_pushed_to_channels_at',
    ];

    protected $casts = [
        'date' => 'date',
        'total_rooms' => 'integer',
        'rooms_sold' => 'integer',
        'rooms_blocked' => 'integer',
        'rooms_held' => 'integer',
        'overbooking_allowed' => 'integer',
        'stop_sell' => 'boolean',
        'last_pushed_to_channels_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function availableRooms(): int
    {
        if ($this->stop_sell) {
            return 0;
        }
        $available = $this->total_rooms + $this->overbooking_allowed
            - $this->rooms_sold - $this->rooms_blocked - $this->rooms_held;
        return max(0, $available);
    }

    public function occupancyPercent(): float
    {
        if ($this->total_rooms === 0) {
            return 0;
        }
        return round(($this->rooms_sold / $this->total_rooms) * 100, 2);
    }
}
