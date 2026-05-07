<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Property;
use App\Services\ModuleService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ModuleAdminController — tenant admin can view and toggle their modules.
 *
 * Per-tenant toggles flow through tenant_modules pivot.
 * Per-property toggles flow through property_modules pivot.
 *
 * Authorisation: only users with 'manage_modules' permission can toggle.
 */
class ModuleAdminController extends Controller
{
    public function __construct(
        private readonly ModuleService $modules,
        private readonly TenantContext $context,
    ) {}

    /**
     * GET /api/admin/modules
     * Returns the catalog with current enabled state for the tenant/property.
     */
    public function index(Request $request): JsonResponse
    {
        $catalog = Module::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $property = null;
        if ($request->filled('property_id')) {
            $property = Property::findOrFail($request->integer('property_id'));
        }

        $enabledMap = $this->modules->enabledMap($property);

        $data = $catalog->map(function (Module $m) use ($enabledMap) {
            return [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'description' => $m->description,
                'category' => $m->category,
                'is_core' => $m->is_core,
                'depends_on' => $m->depends_on,
                'available_in_plans' => $m->available_in_plans,
                'addon_price_inr' => $m->addon_price_inr,
                'icon' => $m->icon,
                'is_enabled' => $enabledMap[$m->code] ?? false,
            ];
        })->groupBy('category');

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/admin/modules/{moduleCode}/enable
     */
    public function enable(Request $request, string $moduleCode): JsonResponse
    {
        $tenant = $this->context->tenant();
        if (! $tenant) abort(401);

        try {
            if ($request->filled('property_id')) {
                $property = Property::findOrFail($request->integer('property_id'));
                $this->modules->toggleForProperty($property, $moduleCode, true);
            } else {
                $this->modules->toggleForTenant($tenant, $moduleCode, true, auth()->id());
            }
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "Module '{$moduleCode}' enabled."]);
    }

    /**
     * POST /api/admin/modules/{moduleCode}/disable
     */
    public function disable(Request $request, string $moduleCode): JsonResponse
    {
        $tenant = $this->context->tenant();
        if (! $tenant) abort(401);

        try {
            if ($request->filled('property_id')) {
                $property = Property::findOrFail($request->integer('property_id'));
                $this->modules->toggleForProperty($property, $moduleCode, false);
            } else {
                $this->modules->toggleForTenant($tenant, $moduleCode, false, auth()->id());
            }
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "Module '{$moduleCode}' disabled."]);
    }
}
