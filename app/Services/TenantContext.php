<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Tenant;

/**
 * TenantContext is a singleton holding the current request's tenant + property.
 *
 * Resolved by ResolveTenantMiddleware on every request based on:
 *   1. Authenticated user's tenant_id (most common)
 *   2. Subdomain (for guest-facing booking engine)
 *   3. X-Tenant-ID header (API)
 *
 * Every TenantScopedModel auto-applies tenant_id from this context.
 * If null, the global scope blocks all queries (fail-closed).
 */
class TenantContext
{
    private ?Tenant $tenant = null;
    private ?Property $property = null;
    private bool $bypassed = false;

    public function set(?Tenant $tenant, ?Property $property = null): void
    {
        $this->tenant = $tenant;
        $this->property = $property;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    public function property(): ?Property
    {
        return $this->property;
    }

    public function propertyId(): ?int
    {
        return $this->property?->id;
    }

    public function setProperty(?Property $property): void
    {
        if ($property && $this->tenant && $property->tenant_id !== $this->tenant->id) {
            throw new \RuntimeException('Property does not belong to current tenant.');
        }
        $this->property = $property;
    }

    public function isSet(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Bypass tenant scoping. Use ONLY for super-admin / SaaS console operations.
     * Wrap in try/finally to guarantee restoration.
     */
    public function bypass(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;
        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->property = null;
        $this->bypassed = false;
    }
}
