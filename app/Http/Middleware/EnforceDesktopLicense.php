<?php

namespace App\Http\Middleware;

use App\Services\Desktop\LicenseActivator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceDesktopLicense — runs on every request in the desktop build.
 *
 *   - unactivated → redirect to /desktop/activate (license entry screen)
 *   - active      → pass through, share `licenseStatus` view variable for banner
 *   - soft_warn   → pass, but share a warning banner ("connect to internet
 *                   within X days to keep using")
 *   - readonly    → block POST/PUT/PATCH/DELETE, redirect writes to license screen
 *   - invalid     → redirect to license screen
 */
class EnforceDesktopLicense
{
    public function __construct(private readonly LicenseActivator $activator) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Skip the license check on the license screens themselves and on the
        // first-run setup wizard.
        $route = $request->route()?->getName() ?? '';
        if (str_starts_with($route, 'desktop.license.') || str_starts_with($route, 'desktop.setup.')) {
            return $next($request);
        }

        $state = $this->activator->status();

        // The activation + setup pages submit via the Livewire update endpoint
        // (/livewire/update), which is its own route — not desktop.license.*.
        // Without this carve-out we'd return a 402 JSON to those AJAX calls
        // and the form would silently do nothing. Allowing Livewire requests
        // through in unactivated/invalid states is safe because the only
        // Livewire components reachable in those states are the activation
        // and first-run setup screens.
        $isLivewire = $request->is('livewire/*') || $request->hasHeader('X-Livewire');
        if ($isLivewire && in_array($state['status'], [
            LicenseActivator::STATUS_UNACTIVATED,
            LicenseActivator::STATUS_INVALID,
        ], true)) {
            return $next($request);
        }

        switch ($state['status']) {
            case LicenseActivator::STATUS_UNACTIVATED:
            case LicenseActivator::STATUS_INVALID:
                return $request->expectsJson()
                    ? response()->json(['message' => 'Activation required.'], 402)
                    : redirect()->route('desktop.license.activate')->with('reason', $state['reason'] ?? null);

            case LicenseActivator::STATUS_READONLY:
                if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    return redirect()->route('desktop.license.activate')
                        ->with('warning', "Offline for {$state['days_offline']} days. Please connect to the internet to revalidate your license — Hotelesy is in read-only mode.");
                }
                view()->share('licenseStatus', [
                    'mode'         => 'readonly',
                    'days_offline' => $state['days_offline'] ?? null,
                    'license'      => $state['license'] ?? null,
                ]);
                break;

            case LicenseActivator::STATUS_SOFT_WARN:
                view()->share('licenseStatus', [
                    'mode'         => 'soft_warn',
                    'days_offline' => $state['days_offline'] ?? null,
                    'license'      => $state['license'] ?? null,
                ]);
                break;

            case LicenseActivator::STATUS_ACTIVE:
            default:
                view()->share('licenseStatus', [
                    'mode'    => 'active',
                    'license' => $state['license'] ?? null,
                ]);
        }

        return $next($request);
    }
}
