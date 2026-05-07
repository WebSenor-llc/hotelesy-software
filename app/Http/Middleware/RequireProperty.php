<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the request has a property context. Operational routes
 * (reservations, check-in, folios) require this — they're property-specific.
 */
class RequireProperty
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->context->property()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Property context required. Pass X-Property-Id header or set default_property_id on user.',
                ], 422);
            }
            return redirect()->route('property.select')
                ->with('warning', 'Please select a property to continue.');
        }

        return $next($request);
    }
}
