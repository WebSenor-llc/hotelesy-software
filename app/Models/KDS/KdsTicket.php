<?php

namespace App\Models\KDS;

use App\Models\Concerns\BelongsToTenant;
use App\Models\POS\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KdsTicket extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'kds_tickets';
    protected $guarded = ['id'];

    public const STATUS_QUEUED = 'queued';
    public const STATUS_STARTED = 'started';
    public const STATUS_READY = 'ready';
    public const STATUS_SERVED = 'served';
    public const STATUS_RECALLED = 'recalled';
    public const STATUS_VOIDED = 'voided';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_RUSH = 'rush';

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
        'target_prep_seconds' => 'integer',
        'actual_prep_seconds' => 'integer',
        'is_overdue' => 'boolean',
        'is_recall' => 'boolean',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(KdsStation::class, 'station_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KdsTicketItem::class, 'ticket_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(KdsEvent::class, 'ticket_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUEUED,
            self::STATUS_STARTED,
            self::STATUS_READY,
        ], true);
    }

    /**
     * Age in seconds since queued. Powers the KDS color escalation logic.
     */
    public function ageSeconds(): int
    {
        $endpoint = $this->ready_at ?? $this->served_at ?? now();
        return $this->queued_at->diffInSeconds($endpoint);
    }

    /**
     * Color tier for KDS UI: 'fresh' < 'warning' < 'overdue' < 'critical'
     */
    public function urgencyTier(): string
    {
        $age = $this->ageSeconds();
        $target = $this->target_prep_seconds ?? ($this->station?->default_prep_minutes ?? 15) * 60;

        if ($age < $target * 0.6) return 'fresh';
        if ($age < $target) return 'warning';
        if ($age < $target * 1.5) return 'overdue';
        return 'critical';
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_QUEUED,
            self::STATUS_STARTED,
            self::STATUS_READY,
        ]);
    }

    public function scopeForStation(Builder $q, int $stationId): Builder
    {
        return $q->where('station_id', $stationId);
    }
}
