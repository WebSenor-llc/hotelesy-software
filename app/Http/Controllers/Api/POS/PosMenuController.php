<?php

namespace App\Http\Controllers\Api\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\MenuCategory;
use App\Models\POS\MenuItem;
use App\Models\POS\Outlet;
use App\Models\POS\PosTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosMenuController extends Controller
{
    /**
     * GET /api/pos/outlets
     */
    public function outlets(Request $request): JsonResponse
    {
        $query = Outlet::query()->with('property');
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->integer('property_id'));
        }
        return response()->json(['data' => $query->where('is_active', true)->get()]);
    }

    public function storeOutlet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['restaurant', 'bar', 'cafe', 'room_service', 'banquet', 'pool', 'spa', 'other'])],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'service_charge_percent' => ['nullable', 'numeric', 'min:0', 'max:25'],
        ]);
        $outlet = Outlet::create($data);
        return response()->json(['data' => $outlet], 201);
    }

    /**
     * GET /api/pos/outlets/{outlet}/tables
     */
    public function tables(Outlet $outlet): JsonResponse
    {
        return response()->json([
            'data' => $outlet->tables()->where('is_active', true)->orderBy('section')->orderBy('name')->get(),
        ]);
    }

    public function storeTable(Request $request, Outlet $outlet): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'section' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $table = $outlet->tables()->create([
            'property_id' => $outlet->property_id,
            'name' => $data['name'],
            'section' => $data['section'] ?? null,
            'capacity' => $data['capacity'] ?? 2,
            'status' => PosTable::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
        return response()->json(['data' => $table], 201);
    }

    /**
     * GET /api/pos/outlets/{outlet}/menu — full menu tree (categories with items)
     */
    public function menu(Outlet $outlet): JsonResponse
    {
        $categories = MenuCategory::where('property_id', $outlet->property_id)
            ->where(function ($q) use ($outlet) {
                $q->where('outlet_id', $outlet->id)->orWhereNull('outlet_id');
            })
            ->where('is_active', true)
            ->orderBy('display_order')
            ->with(['items' => function ($q) {
                $q->where('is_active', true)->where('available', true);
            }])
            ->get();
        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'outlet_id' => ['nullable', 'integer', 'exists:pos_outlets,id'],
            'name' => ['required', 'string', 'max:100'],
            'kot_printer' => ['nullable', 'string', 'max:100'],
            'is_liquor' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $cat = MenuCategory::create($data);
        return response()->json(['data' => $cat], 201);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'category_id' => ['required', 'integer', 'exists:pos_menu_categories,id'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'food_type' => ['required', Rule::in(['veg', 'non_veg', 'egg', 'jain', 'beverage', 'liquor'])],
            'is_combo' => ['nullable', 'boolean'],
            'is_taxable' => ['nullable', 'boolean'],
        ]);
        $item = MenuItem::create($data);
        return response()->json(['data' => $item], 201);
    }
}
