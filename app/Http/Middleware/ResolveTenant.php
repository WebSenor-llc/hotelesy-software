<?php

namespace App\Http\Middleware;

use App\Models\Property;
use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveTenant — runs on every authenticated request and pins the current
 * tenant + property into TenantContext (singleton).
 *
 * Resolution priority:
 *   1. Authenticated user's tenant_id (the common path).
 *   2. X-Tenant-Slug header (mobile / API clients).
 *   3. Subdomain (e.g. mirajhotels.app.com).
 *
 * Property selection:
 *   - X-Property-Id header takes precedence (multi-property switcher).
 *   - Otherwise user's default_property_id.
 *
 * On failure: 401/403 — never proceed without tenant context.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admins operate cross-tenant — skip tenant pinning entirely.
        // Their components use TenantContext::bypass() for any cross-tenant queries.
        if ($user && $user->is_super_admin) {
            return $next($request);
        }

        $tenant = null;

        if ($user) {
            $tenant = $user->tenant;
        }

        if (! $tenant && $request->hasHeader('X-Tenant-Slug')) {
            $tenant = Tenant::where('slug', $request->header('X-Tenant-Slug'))->first();
        }

        if (! $tenant) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                $tenant = Tenant::where('slug', $parts[0])->first();
            }
        }

        if (! $tenant) {
            // Public routes (login, booking engine landing) won't reach here
            // because they don't apply this middleware.
            abort(401, 'No tenant context.');
        }

        if (! $tenant->isActive()) {
            abort(403, 'Subscription is not active.');
        }

        // Set the tenant first so the Property global tenant scope can resolve it.
        $this->context->set($tenant, null);

        // Property resolution
        $property = null;
        $propertyId = $request->header('X-Property-Id') ?? $user?->default_property_id;
        if ($propertyId) {
            $property = Property::where('tenant_id', $tenant->id)->find($propertyId);
        }

        if ($property) {
            $this->context->setProperty($property);
        }

        return $next($request);
    }
}
