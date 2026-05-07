<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSyncLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'channel', 'direction', 'operation',
        'date_from', 'date_to', 'payload_sent', 'payload_received',
        'status', 'error_message', 'retry_count', 'next_retry_at',
        'records_processed', 'records_failed',
        'triggered_by', 'started_at', 'completed_at', 'duration_ms',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'payload_sent' => 'array',
        'payload_received' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
