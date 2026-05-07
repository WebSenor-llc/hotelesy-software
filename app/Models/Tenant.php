<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'legal_name', 'owner_email', 'owner_phone',
        'country', 'currency', 'timezone', 'locale',
        'plan', 'status', 'trial_ends_at', 'subscription_ends_at',
        'property_limit', 'room_limit', 'user_limit',
        'channel_manager_driver', 'channel_manager_config',
        'features', 'settings',
    ];

    protected $casts = [
        'trial_ends_at' => 'date',
        'subscription_ends_at' => 'date',
        'property_limit' => 'integer',
        'room_limit' => 'integer',
        'user_limit' => 'integer',
        'channel_manager_config' => 'encrypted:array',
        'features' => 'array',
        'settings' => 'array',
    ];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')
            ->withPivot('is_enabled', 'settings', 'enabled_at', 'disabled_at')
            ->withTimestamps();
    }

    public function license(): HasOne
    {
        return $this->hasOne(License::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']);
    }

    public function hasFeature(string $feature): bool
    {
        return ($this->features[$feature] ?? false) === true;
    }
}
