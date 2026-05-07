<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $table = 'store_purchase_order_items';
    protected $guarded = ['id'];
    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function item(): BelongsTo { return $this->belongsTo(StoreItem::class, 'item_id'); }
}
