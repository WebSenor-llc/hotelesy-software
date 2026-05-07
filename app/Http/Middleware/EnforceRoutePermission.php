<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceRoutePermission — gates routes by spatie permission based on a
 * route-name → permission map. Privileged users (super admin / Owner / GM)
 * always pass.
 *
 * Routes not in the map pass through (whitelist-by-omission). This is to
 * avoid accidentally locking out essential routes (login, dashboard, etc.).
 */
class EnforceRoutePermission
{
    /**
     * Map route-name prefix → required permission.
     * Use prefix matching (str_starts_with) — e.g. "setup." gates all
     * routes named setup.*.
     */
    private const MAP = [
        'reservations.' => 'reservations.view',
        'frontoffice.'  => 'frontoffice.view',
        'pos.'          => 'pos.view',
        'kds.'          => 'pos.view',
        'housekeeping.' => 'housekeeping.view',
        'banquet.'      => 'banquet.view',
        'store.'        => 'store.view',
        'crm.'          => 'crm.view',
        'channel.'      => 'channel.view',
        'revenue.'      => 'revenue.view',
        'rates.'        => 'revenue.view',
        'reports.'      => 'reports.view',
        'accounts.'     => 'accounts.view',
        'compliance.'   => 'frontoffice.view',
        'setup.'        => 'setup.view',
        'integrations.' => 'setup.edit',
        'promotions.'   => 'revenue.view',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        // Privileged bypass
        if ($user->is_super_admin
            || $user->hasRole('Owner / Director')
            || $user->hasRole('General Manager')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        // Always-allowed
        if ($routeName === 'dashboard'
            || $routeName === 'logout'
            || str_starts_with($routeName, 'license.')
            || str_starts_with($routeName, 'property.')
            || str_starts_with($routeName, 'super.')
            || str_starts_with($routeName, 'profile.')) {
            return $next($request);
        }

        foreach (self::MAP as $prefix => $perm) {
            if (str_starts_with($routeName, $prefix)) {
                if (! $user->hasPermissionTo($perm)) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => "You don't have permission to access this section ({$perm}).",
                        ], 403);
                    }
                    return redirect()->route('dashboard')
                        ->with('warning', 'You don\'t have permission to access that section.');
                }
                break;
            }
        }

        return $next($request);
    }
}
