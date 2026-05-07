<?php

namespace App\Models\Store;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreCategory extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'store_categories';
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];
}
