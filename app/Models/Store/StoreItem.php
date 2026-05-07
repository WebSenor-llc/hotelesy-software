<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreItem extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'store_items';
    protected $guarded = ['id'];
    protected $casts = [
        'current_stock' => 'decimal:3',
        'reorder_level' => 'decimal:3',
        'max_stock' => 'decimal:3',
        'average_cost' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'track_expiry' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo { return $this->belongsTo(StoreCategory::class, 'category_id'); }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->reorder_level;
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }
}
