<?php

namespace App\Models\POS;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'pos_outlets';
    protected $guarded = ['id'];
    protected $casts = [
        'service_charge_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'open_time' => 'string',
        'close_time' => 'string',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(PosTable::class, 'outlet_id');
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class, 'outlet_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'outlet_id');
    }
}
