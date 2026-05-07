<?php

namespace App\Models\Housekeeping;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HousekeepingTask extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'housekeeping_tasks';
    protected $guarded = ['id'];

    public const TYPE_DEPARTURE_CLEAN = 'departure_clean';
    public const TYPE_TURN_DOWN = 'turn_down';
    public const TYPE_STAYOVER = 'stayover_clean';
    public const TYPE_INSPECTION = 'inspection';
    public const TYPE_DEEP_CLEAN = 'deep_clean';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_LOST_FOUND = 'lost_found';
    public const TYPE_MINIBAR = 'minibar_restock';
    public const TYPE_LINEN = 'linen_change';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_ON_HOLD = 'on_hold';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'time_taken_minutes' => 'integer',
        'checklist' => 'array',
    ];

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }

    public function start(?int $userId = null): self
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException("Task is {$this->status}; cannot start.");
        }
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'assigned_to' => $userId ?? $this->assigned_to,
        ]);
        return $this->fresh();
    }

    public function complete(?array $checklist = null): self
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            throw new \DomainException("Task is {$this->status}; cannot complete.");
        }
        $now = now();
        $minutes = $this->started_at ? $this->started_at->diffInMinutes($now) : null;
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => $now,
            'time_taken_minutes' => $minutes,
            'checklist' => $checklist ?? $this->checklist,
        ]);
        return $this->fresh();
    }

    public function verify(int $userId): self
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new \DomainException("Task must be completed before verification.");
        }
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $userId,
        ]);

        // Side effect: room moves from vacant_dirty -> vacant_clean (or similar)
        if ($this->room && in_array($this->task_type, [self::TYPE_DEPARTURE_CLEAN, self::TYPE_STAYOVER, self::TYPE_DEEP_CLEAN], true)) {
            if ($this->room->status === 'vacant_dirty') {
                $this->room->update(['status' => 'vacant_clean']);
            }
        }

        return $this->fresh();
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS])
            ->orderBy('scheduled_date')
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')");
    }
}
