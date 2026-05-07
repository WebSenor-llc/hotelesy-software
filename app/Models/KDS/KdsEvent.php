<?php

namespace App\Models\KDS;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KdsEvent extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'kds_events';
    protected $guarded = ['id'];
    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KdsTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
