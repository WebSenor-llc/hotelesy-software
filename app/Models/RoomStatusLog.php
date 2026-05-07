<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomStatusLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'room_id',
        'from_status', 'to_status', 'changed_by', 'notes', 'device_id', 'changed_at',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
