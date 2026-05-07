<?php

namespace App\Http\Middleware;

use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block access to routes when the required module isn't enabled for the
 * current tenant/property. Apply via route middleware:
 *
 *   Route::middleware(['module:pos'])->group(...);
 */
class RequireModule
{
    public function __construct(private readonly ModuleService $modules) {}

    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        if (! $this->modules->enabled($moduleCode)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "The '{$moduleCode}' module is not enabled for your account.",
                    'error_code' => 'module_disabled',
                    'module' => $moduleCode,
                ], 403);
            }
            abort(403, "The {$moduleCode} module is not enabled.");
        }

        return $next($request);
    }
}
