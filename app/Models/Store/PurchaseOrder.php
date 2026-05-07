<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'store_purchase_orders';
    protected $guarded = ['id'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'po_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function vendor(): BelongsTo { return $this->belongsTo(StoreVendor::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function grns(): HasMany { return $this->hasMany(Grn::class); }
}
