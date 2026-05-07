<?php

namespace App\Models\POS;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuItem extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pos_menu_items';
    protected $guarded = ['id'];
    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'is_combo' => 'boolean',
        'is_taxable' => 'boolean',
        'available' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(Modifier::class, 'pos_menu_item_modifier');
    }
}
