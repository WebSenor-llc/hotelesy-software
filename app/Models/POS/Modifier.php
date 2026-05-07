<?php

namespace App\Models\POS;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modifier extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pos_modifiers';
    protected $guarded = ['id'];
    protected $casts = [
        'price_delta' => 'decimal:2',
    ];
}
