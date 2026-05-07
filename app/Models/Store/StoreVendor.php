<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreVendor extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'store_vendors';
    protected $guarded = ['id'];
    protected $casts = [
        'credit_limit' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'is_active' => 'boolean',
    ];
}
