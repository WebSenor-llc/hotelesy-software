<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'description', 'category', 'is_core',
        'depends_on', 'available_in_plans', 'addon_price_inr',
        'icon', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'depends_on' => 'array',
        'available_in_plans' => 'array',
        'addon_price_inr' => 'decimal:2',
        'is_core' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_modules')
            ->withPivot('is_enabled', 'settings', 'enabled_at', 'disabled_at')
            ->withTimestamps();
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_modules')
            ->withPivot('is_enabled', 'settings')
            ->withTimestamps();
    }

    public function isAvailableInPlan(?string $plan): bool
    {
        if (empty($this->available_in_plans)) {
            return true;
        }
        return $plan !== null && in_array($plan, $this->available_in_plans, true);
    }
}
