<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationRoom extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'reservation_id',
        'room_type_id', 'rate_plan_id', 'room_id',
        'arrival_date', 'departure_date', 'nights',
        'adults', 'children', 'extra_beds',
        'guest_name', 'guest_id',
        'average_rate', 'total_rate', 'total_tax', 'total_amount',
        'status', 'checked_in_at', 'checked_out_at',
        'checked_in_by', 'checked_out_by',
        'key_card_number', 'special_requests',
    ];

    protected $casts = [
        'arrival_date' => 'date',
        'departure_date' => 'date',
        'nights' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'extra_beds' => 'integer',
        'average_rate' => 'decimal:2',
        'total_rate' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function nightsBreakdown(): HasMany
    {
        return $this->hasMany(ReservationRoomNight::class);
    }
}
