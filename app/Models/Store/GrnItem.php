<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrnItem extends Model
{
    use HasFactory;

    protected $table = 'store_grn_items';
    protected $guarded = ['id'];
    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function item(): BelongsTo { return $this->belongsTo(StoreItem::class, 'item_id'); }
}
