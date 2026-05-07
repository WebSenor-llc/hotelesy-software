<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * License — represents a tenant's subscription rights.
 *
 * NOTE: Licenses are managed by super admins ACROSS tenants. We deliberately do
 * NOT use the BelongsToTenant trait here — the EnforceLicense middleware and
 * the super-admin console need to read licenses without tenant scoping.
 *
 * Always scope manually (tenant_id) when querying as a tenant user.
 */
class License extends Model
{
    use HasFactory, SoftDeletes;

    public const GRACE_DAYS = 3;

    public const STATUS_TRIAL     = 'trial';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAST_DUE  = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'subscription_plan_id',
        'license_key', 'license_key_hash',
        'status', 'starts_at', 'expires_at', 'billing_cycle',
        'last_validated_at', 'issued_by', 'issued_at',
        'suspended_at', 'suspended_reason', 'cancelled_at', 'notes',
    ];

    protected $casts = [
        'starts_at'         => 'datetime',
        'expires_at'        => 'datetime',
        'last_validated_at' => 'datetime',
        'issued_at'         => 'datetime',
        'suspended_at'      => 'datetime',
        'cancelled_at'      => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE], true)
            && $this->expires_at instanceof Carbon
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return true;
        }
        return $this->expires_at instanceof Carbon
            ? $this->expires_at->lte(now())
            : true;
    }

    public function daysRemaining(): int
    {
        if (! $this->expires_at instanceof Carbon) {
            return 0;
        }
        $diff = (int) round(now()->diffInDays($this->expires_at, false));
        return max(0, $diff);
    }

    public function gracePeriodEnds(): Carbon
    {
        return ($this->expires_at ?? now())->copy()->addDays(self::GRACE_DAYS);
    }

    public function inGracePeriod(): bool
    {
        if (in_array($this->status, [self::STATUS_SUSPENDED, self::STATUS_CANCELLED], true)) {
            return false;
        }
        return $this->isExpired() && now()->lt($this->gracePeriodEnds());
    }

    public function gracePeriodDaysLeft(): int
    {
        if (! $this->inGracePeriod()) {
            return 0;
        }
        return max(0, (int) round(now()->diffInDays($this->gracePeriodEnds(), false)));
    }

    /**
     * Generate a HTLY-XXXX-XXXX-XXXX-XXXX uppercase alphanumeric license key.
     */
    public static function generateKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // omit ambiguous I, O, 0, 1
        $chunks = [];
        for ($i = 0; $i < 4; $i++) {
            $chunk = '';
            for ($j = 0; $j < 4; $j++) {
                $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $chunks[] = $chunk;
        }
        return 'HTLY-' . implode('-', $chunks);
    }

    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Status badge class helpers — used by views.
     */
    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE    => 'bg-emerald-100 text-emerald-700',
            self::STATUS_TRIAL     => 'bg-sky-100 text-sky-700',
            self::STATUS_PAST_DUE  => 'bg-amber-100 text-amber-700',
            self::STATUS_SUSPENDED => 'bg-rose-100 text-rose-700',
            self::STATUS_EXPIRED   => 'bg-slate-200 text-slate-700',
            self::STATUS_CANCELLED => 'bg-slate-300 text-slate-800',
            default                => 'bg-slate-100 text-slate-600',
        };
    }
}
