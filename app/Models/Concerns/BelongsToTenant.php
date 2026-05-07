<?php

namespace App\Models\Concerns;

use App\Scopes\TenantScope;
use App\Services\TenantContext;

/**
 * Apply to any model with a tenant_id column.
 * - Adds the global TenantScope on boot.
 * - Auto-fills tenant_id on creation from TenantContext.
 *
 * Usage:
 *   class Reservation extends Model {
 *       use BelongsToTenant;
 *   }
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = app(TenantContext::class)->tenantId();
                if ($tenantId === null && !app(TenantContext::class)->isBypassed()) {
                    throw new \RuntimeException(
                        'Cannot create ' . static::class . ' without tenant context. ' .
                        'Either set tenant explicitly via TenantContext or bypass for super-admin operations.'
                    );
                }
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
