<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const STATUS_VACANT_CLEAN = 'vacant_clean';
    public const STATUS_VACANT_DIRTY = 'vacant_dirty';
    public const STATUS_OCCUPIED_CLEAN = 'occupied_clean';
    public const STATUS_OCCUPIED_DIRTY = 'occupied_dirty';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_OUT_OF_ORDER = 'out_of_order';
    public const STATUS_OUT_OF_SERVICE = 'out_of_service';
    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'tenant_id', 'property_id', 'room_type_id',
        'number', 'floor', 'wing', 'view',
        'status', 'fo_status',
        'out_of_order_reason', 'out_of_order_until',
        'is_smoking', 'is_accessible', 'has_extra_bed',
        'has_connecting_room', 'connecting_room_id',
        'amenities', 'housekeeping_notes', 'maintenance_notes',
        'is_active',
    ];

    protected $casts = [
        'is_smoking' => 'boolean',
        'is_accessible' => 'boolean',
        'has_extra_bed' => 'boolean',
        'has_connecting_room' => 'boolean',
        'is_active' => 'boolean',
        'out_of_order_until' => 'date',
        'amenities' => 'array',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(RoomStatusLog::class);
    }

    public function reservationRooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, [
            self::STATUS_VACANT_CLEAN,
            self::STATUS_INSPECTED,
        ]) && $this->fo_status === 'vacant';
    }

    public function isOutOfOrder(): bool
    {
        return in_array($this->status, [
            self::STATUS_OUT_OF_ORDER,
            self::STATUS_OUT_OF_SERVICE,
            self::STATUS_BLOCKED,
        ]);
    }

    public function changeStatus(string $newStatus, ?int $userId = null, ?string $notes = null, ?string $deviceId = null): void
    {
        $oldStatus = $this->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        $this->update(['status' => $newStatus]);

        $this->statusLogs()->create([
            'tenant_id' => $this->tenant_id,
            'property_id' => $this->property_id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by' => $userId,
            'notes' => $notes,
            'device_id' => $deviceId,
            'changed_at' => now(),
        ]);
    }
}
