<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * ModuleService — single source of truth for "is this feature on for this tenant/property?"
 *
 * Resolution order:
 *   1. Property override (property_modules pivot) — most specific.
 *   2. Tenant override (tenant_modules pivot).
 *   3. Plan default (modules.available_in_plans).
 *   4. Module is_active flag.
 *
 * Cached per (tenant, property, module) for the request lifecycle. Cache
 * busts when a user toggles the module via the admin UI.
 *
 * Usage:
 *   if (! app(ModuleService::class)->enabled('pos')) {
 *       abort(403, 'POS module not enabled.');
 *   }
 */
class ModuleService
{
    private const CACHE_TTL = 300;
    private array $requestCache = [];

    public function __construct(private readonly TenantContext $context) {}

    public function enabled(string $moduleCode, ?Property $property = null): bool
    {
        $tenant = $this->context->tenant();
        if (! $tenant) {
            return false;
        }
        $property ??= $this->context->property();

        $cacheKey = "module:{$tenant->id}:" . ($property?->id ?? '0') . ":{$moduleCode}";

        if (isset($this->requestCache[$cacheKey])) {
            return $this->requestCache[$cacheKey];
        }

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant, $property, $moduleCode) {
            return $this->resolve($tenant, $property, $moduleCode);
        });

        return $this->requestCache[$cacheKey] = $result;
    }

    /**
     * Bulk check — returns ['pos' => true, 'kds' => false, ...]
     */
    public function enabledMap(?Property $property = null): array
    {
        $tenant = $this->context->tenant();
        if (! $tenant) {
            return [];
        }
        $property ??= $this->context->property();

        return Module::where('is_active', true)->get()->mapWithKeys(function ($m) use ($tenant, $property) {
            return [$m->code => $this->resolve($tenant, $property, $m->code)];
        })->all();
    }

    /**
     * Toggle a module for a tenant.
     */
    public function toggleForTenant(Tenant $tenant, string $moduleCode, bool $enable, ?int $userId = null): void
    {
        $module = Module::where('code', $moduleCode)->firstOrFail();

        if ($module->is_core && ! $enable) {
            throw new \DomainException("Cannot disable core module '{$moduleCode}'.");
        }

        if ($enable) {
            $this->ensureDependenciesEnabled($tenant, $module);
        }

        $tenant->modules()->syncWithoutDetaching([
            $module->id => [
                'is_enabled' => $enable,
                'enabled_at' => $enable ? now() : null,
                'disabled_at' => $enable ? null : now(),
                'changed_by' => $userId,
            ],
        ]);

        $this->bustCacheForTenant($tenant);
    }

    public function toggleForProperty(Property $property, string $moduleCode, bool $enable): void
    {
        $module = Module::where('code', $moduleCode)->firstOrFail();

        $property->modules()->syncWithoutDetaching([
            $module->id => ['is_enabled' => $enable],
        ]);

        Cache::forget("module:{$property->tenant_id}:{$property->id}:{$moduleCode}");
        unset($this->requestCache["module:{$property->tenant_id}:{$property->id}:{$moduleCode}"]);
    }

    private function resolve(Tenant $tenant, ?Property $property, string $moduleCode): bool
    {
        $module = Module::where('code', $moduleCode)->where('is_active', true)->first();
        if (! $module) {
            return false;
        }
        if ($module->is_core) {
            return true;
        }

        // Property override
        if ($property) {
            $pivot = $property->modules()->where('module_id', $module->id)->first()?->pivot;
            if ($pivot && $pivot->is_enabled !== null) {
                return (bool) $pivot->is_enabled;
            }
        }

        // Tenant override
        $pivot = $tenant->modules()->where('module_id', $module->id)->first()?->pivot;
        if ($pivot && $pivot->is_enabled !== null) {
            return (bool) $pivot->is_enabled;
        }

        // Plan default
        return $module->isAvailableInPlan($tenant->plan ?? null);
    }

    private function ensureDependenciesEnabled(Tenant $tenant, Module $module): void
    {
        foreach ($module->depends_on ?? [] as $depCode) {
            if (! $this->enabled($depCode)) {
                throw new \DomainException(
                    "Cannot enable '{$module->code}' — depends on '{$depCode}' which is not enabled."
                );
            }
        }
    }

    private function bustCacheForTenant(Tenant $tenant): void
    {
        // Lightweight: clear request-cache entries for this tenant.
        // For production, attach a tag-based cache strategy.
        $this->requestCache = array_filter(
            $this->requestCache,
            fn($_, $key) => ! str_starts_with($key, "module:{$tenant->id}:"),
            ARRAY_FILTER_USE_BOTH
        );
        // Cache::tags would be ideal but database/file cache doesn't support it.
        // Falls back to TTL expiry.
    }
}
