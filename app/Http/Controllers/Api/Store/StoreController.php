<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Store\PurchaseOrder;
use App\Models\Store\StoreItem;
use App\Services\Store\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(private readonly StoreService $service) {}

    public function items(Request $request): JsonResponse
    {
        $q = StoreItem::with('category')->where('is_active', true);
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->boolean('low_stock_only')) $q->whereColumn('current_stock', '<=', 'reorder_level');
        return response()->json(['data' => $q->orderBy('name')->paginate($request->integer('per_page', 50))]);
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $q = PurchaseOrder::with('vendor', 'items.item');
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        return response()->json(['data' => $q->latest()->paginate($request->integer('per_page', 25))]);
    }

    public function createPO(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'vendor_id' => ['required', 'integer', 'exists:store_vendors,id'],
            'po_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:store_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);
        $property = Property::findOrFail($data['property_id']);
        $po = $this->service->createPurchaseOrder($property, $data);
        return response()->json(['data' => $po], 201);
    }

    public function approvePO(PurchaseOrder $po): JsonResponse
    {
        try {
            $po = $this->service->approvePO($po);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $po]);
    }

    public function receiveGoods(Request $request, PurchaseOrder $po): JsonResponse
    {
        $data = $request->validate([
            'grn_date' => ['nullable', 'date'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:50'],
            'vendor_invoice_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:store_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        try {
            $grn = $this->service->receiveGoods($po, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $grn], 201);
    }

    public function issue(Request $request, StoreItem $item): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $movement = $this->service->issue($item, (float) $data['quantity'], $data['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $movement], 201);
    }
}
