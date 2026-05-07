<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRoomNight extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'reservation_id', 'reservation_room_id',
        'room_type_id', 'rate_plan_id',
        'night_date', 'rate', 'extra_adult_charge', 'extra_child_charge',
        'extra_bed_charge', 'discount_amount', 'tax_amount', 'net_amount',
        'is_posted', 'posted_at',
    ];

    protected $casts = [
        'night_date' => 'date',
        'rate' => 'decimal:2',
        'extra_adult_charge' => 'decimal:2',
        'extra_child_charge' => 'decimal:2',
        'extra_bed_charge' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'is_posted' => 'boolean',
        'posted_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reservationRoom(): BelongsTo
    {
        return $this->belongsTo(ReservationRoom::class);
    }
}
