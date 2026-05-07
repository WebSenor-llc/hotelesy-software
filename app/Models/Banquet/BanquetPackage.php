<?php

namespace App\Models\Banquet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BanquetPackage extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'banquet_packages';
    protected $guarded = ['id'];
    protected $casts = [
        'per_pax_rate' => 'decimal:2',
        'min_pax' => 'integer',
        'inclusions' => 'array',
        'is_active' => 'boolean',
    ];
}
