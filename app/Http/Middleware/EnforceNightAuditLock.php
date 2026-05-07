<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After night audit runs, the previous business day is "locked".
 * No charges can be posted, no folios closed, no payments recorded
 * with a business_date < property.current_business_date.
 *
 * Apply to mutating routes that accept business_date in payload.
 * Manager override: users with permission `bypass_night_audit_lock` skip this.
 */
class EnforceNightAuditLock
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $property = $this->context->property();
        if (!$property || !$property->night_audit_locked) {
            return $next($request);
        }

        $user = $request->user();
        if ($user?->can('bypass_night_audit_lock')) {
            return $next($request);
        }

        $bizDateInput = $request->input('business_date');
        if (!$bizDateInput) {
            return $next($request);
        }

        $current = $property->current_business_date;
        if ($current && \Carbon\Carbon::parse($bizDateInput)->lt($current)) {
            return response()->json([
                'message' => "Business date {$bizDateInput} is locked by night audit. " .
                    "Current business date is {$current->toDateString()}. " .
                    'Manager override required.',
            ], 423);
        }

        return $next($request);
    }
}
