<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * NOTE: User does NOT use BelongsToTenant trait globally because
     * super-admin users have null tenant_id. Tenant filtering for User
     * is done explicitly in queries / via the property_user pivot.
     */

    protected $fillable = [
        'tenant_id', 'default_property_id', 'name', 'email', 'phone',
        'password', 'employee_code', 'department', 'designation',
        'is_super_admin', 'is_active',
        'can_handle_cash', 'cash_drawer_limit', 'settings',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'password_changed_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',
        'is_active' => 'boolean',
        'can_handle_cash' => 'boolean',
        'force_password_change' => 'boolean',
        'cash_drawer_limit' => 'decimal:2',
        'failed_login_attempts' => 'integer',
        'settings' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function defaultProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'default_property_id');
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_user');
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function recordLogin(?string $ip = null, ?string $device = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'last_login_device' => $device,
            'failed_login_attempts' => 0,
        ]);
    }

    public function recordFailedLogin(): void
    {
        $this->increment('failed_login_attempts');
        if ($this->failed_login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    /**
     * Configure spatie/laravel-permission to use tenant_id as the team scope.
     * This ensures roles/permissions are per-tenant, not global.
     */
    public function getDefaultGuardName(): string
    {
        return 'web';
    }
}
