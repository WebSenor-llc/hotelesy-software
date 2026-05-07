<?php

namespace App\Models\Accounts;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NightAuditLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'night_audit_logs';
    protected $guarded = ['id'];

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $casts = [
        'business_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'arrivals_count' => 'integer',
        'departures_count' => 'integer',
        'in_house_count' => 'integer',
        'no_shows_count' => 'integer',
        'walk_ins_count' => 'integer',
        'room_revenue' => 'decimal:2',
        'food_revenue' => 'decimal:2',
        'beverage_revenue' => 'decimal:2',
        'banquet_revenue' => 'decimal:2',
        'other_revenue' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_collected' => 'decimal:2',
        'outstanding' => 'decimal:2',
        'rooms_sold' => 'integer',
        'rooms_available' => 'integer',
        'occupancy_pct' => 'decimal:2',
        'arr' => 'decimal:2',
        'revpar' => 'decimal:2',
        'exception_log' => 'array',
    ];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function performedBy(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
}
