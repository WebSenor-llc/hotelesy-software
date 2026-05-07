<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'store_stock_movements';
    protected $guarded = ['id'];

    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_ISSUE = 'issue';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_WASTAGE = 'wastage';
    public const TYPE_RETURN = 'return';

    protected $casts = [
        'movement_date' => 'date',
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function item(): BelongsTo { return $this->belongsTo(StoreItem::class, 'item_id'); }
}
