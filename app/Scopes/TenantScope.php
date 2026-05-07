<?php

namespace App\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applies WHERE tenant_id = X to every query on a TenantScopedModel.
 *
 * Fail-closed: if no tenant in context AND not bypassed, query returns empty
 * by injecting `WHERE 1 = 0`. This prevents accidental cross-tenant leaks
 * (e.g. console scripts forgetting to set context).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $tenantId = $context->tenantId();

        if ($tenantId === null) {
            // Fail closed.
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', $tenantId);
    }
}
