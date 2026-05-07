<?php

namespace App\Models\KDS;

use App\Models\Concerns\BelongsToTenant;
use App\Models\POS\MenuCategory;
use App\Models\POS\Outlet;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KdsStation extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'kds_stations';
    protected $guarded = ['id'];
    protected $casts = [
        'default_prep_minutes' => 'integer',
        'display_config' => 'array',
        'is_active' => 'boolean',
    ];

    public const TYPE_HOT_KITCHEN = 'hot_kitchen';
    public const TYPE_COLD_KITCHEN = 'cold_kitchen';
    public const TYPE_BAR = 'bar';
    public const TYPE_TANDOOR = 'tandoor';
    public const TYPE_PIZZA = 'pizza';
    public const TYPE_GRILL = 'grill';
    public const TYPE_PICKUP = 'pickup_window';
    public const TYPE_EXPO = 'expo';

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            MenuCategory::class,
            'kds_station_routes',
            'station_id',
            'menu_category_id'
        )->withPivot('priority')->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(KdsTicket::class, 'station_id');
    }

    public function activeTickets()
    {
        return $this->tickets()->whereIn('status', [
            KdsTicket::STATUS_QUEUED,
            KdsTicket::STATUS_STARTED,
            KdsTicket::STATUS_READY,
        ]);
    }
}
