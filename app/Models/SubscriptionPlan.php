<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name',
        'price_monthly', 'price_yearly', 'billing_currency',
        'max_properties', 'max_rooms', 'max_users',
        'features', 'trial_days', 'is_active',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly'  => 'decimal:2',
        'features'      => 'array',
        'is_active'     => 'boolean',
        'trial_days'    => 'integer',
        'max_properties'=> 'integer',
        'max_rooms'     => 'integer',
        'max_users'     => 'integer',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function isTrial(): bool
    {
        return $this->code === 'trial' || $this->trial_days > 0 && (float) $this->price_monthly === 0.0;
    }

    public function hasFeature(string $key): bool
    {
        return ($this->features[$key] ?? false) === true;
    }
}
