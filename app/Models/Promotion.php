<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $guarded = ['id'];
    protected $casts = [
        'valid_from' => 'date', 'valid_to' => 'date',
        'value' => 'decimal:2', 'min_amount' => 'decimal:2',
        'target_room_type_ids' => 'array', 'target_rate_plan_ids' => 'array',
        'available_direct' => 'boolean', 'available_ota' => 'boolean',
        'available_corporate' => 'boolean', 'is_stackable' => 'boolean',
        'is_active' => 'boolean', 'is_public' => 'boolean',
    ];

    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->valid_from && $this->valid_from->isFuture()) return false;
        if ($this->valid_to && $this->valid_to->isPast()) return false;
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) return false;
        return true;
    }
}
