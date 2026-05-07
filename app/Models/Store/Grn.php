<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grn extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'store_grns';
    protected $guarded = ['id'];
    protected $casts = [
        'grn_date' => 'date',
        'vendor_invoice_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function vendor(): BelongsTo { return $this->belongsTo(StoreVendor::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function items(): HasMany { return $this->hasMany(GrnItem::class, 'grn_id'); }
}
