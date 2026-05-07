<?php

namespace App\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Optional property-level filtering. Applied when context has a property set.
 * If no property in context, doesn't filter (returns all properties for tenant).
 *
 * Use on operational models (reservations, folios, rooms) where front-desk
 * staff usually work within a single property at a time.
 */
class PropertyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $propertyId = $context->propertyId();
        if ($propertyId === null) {
            return;
        }

        $builder->where($model->getTable() . '.property_id', $propertyId);
    }
}
