<?php

namespace App\Http\Middleware;

use App\Models\License;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceLicense — runs after auth + tenant + property.
 *
 * Behavior:
 *  - Super admins bypass entirely.
 *  - Routes named license.* and logout always pass.
 *  - No license            => redirect to /license/expired ("No license found").
 *  - status cancelled       => redirect to /license/locked   ("License cancelled").
 *  - status suspended       => redirect to /license/locked   ("License suspended").
 *  - Expired + NOT in grace => redirect to /license/expired.
 *  - Expired + in grace     => allow READ-only (block POST/PATCH/PUT/DELETE).
 *                              Flash session var `license_grace` with days_left.
 *                              Share view variable `licenseGrace` for banner.
 *  - Otherwise              => share `licenseStatus` view variable for banners.
 */
class EnforceLicense
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName() ?? '';

        // 1. Super admins always bypass
        if ($user?->is_super_admin) {
            return $next($request);
        }

        // 2. Pass-through routes
        if ($routeName === 'logout' || str_starts_with($routeName, 'license.')) {
            return $next($request);
        }

        $tenant = $this->context->tenant();
        if (! $tenant) {
            return $next($request);
        }

        /** @var License|null $license */
        $license = $tenant->license()->with('plan')->first();

        if (! $license) {
            return redirect()->route('license.expired')
                ->with('warning', 'No license found for your tenant. Contact support.');
        }

        if ($license->status === License::STATUS_CANCELLED) {
            return redirect()->route('license.locked')
                ->with('warning', 'Your license has been cancelled.');
        }

        if ($license->status === License::STATUS_SUSPENDED) {
            return redirect()->route('license.locked')
                ->with('warning', 'Your license is suspended. ' . ($license->suspended_reason ?? ''));
        }

        if ($license->isExpired()) {
            if ($license->inGracePeriod()) {
                // Read-only mode during grace period.
                $isWrite = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
                $isExempt = $routeName === 'logout' || str_starts_with($routeName, 'license.');

                // Always share banner data.
                view()->share('licenseGrace', [
                    'days_left'  => $license->gracePeriodDaysLeft(),
                    'expired_at' => $license->expires_at,
                ]);

                if ($isWrite && ! $isExempt) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'License expired (grace period). Read-only access — renew to continue.',
                        ], 402);
                    }
                    return redirect()->route('license.expired')
                        ->with('warning', 'License expired — read-only mode. Renew within ' . $license->gracePeriodDaysLeft() . ' days.');
                }
            } else {
                // Past grace period — full lockout.
                $license->forceFill(['status' => License::STATUS_EXPIRED])->saveQuietly();
                return redirect()->route('license.expired')
                    ->with('warning', 'Your license has expired.');
            }
        }

        // Healthy license — share banner data for trial countdown.
        view()->share('licenseStatus', [
            'license'    => $license,
            'is_trial'   => $license->status === License::STATUS_TRIAL,
            'days_left'  => $license->daysRemaining(),
            'plan_name'  => $license->plan?->name,
        ]);

        // Touch validation timestamp once per day to keep an audit trail.
        if (! $license->last_validated_at || $license->last_validated_at->lt(now()->startOfDay())) {
            $license->forceFill(['last_validated_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
