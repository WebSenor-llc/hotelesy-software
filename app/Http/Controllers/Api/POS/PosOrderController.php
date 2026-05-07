<?php

namespace App\Http\Controllers\Api\POS;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\POS\Order;
use App\Models\POS\OrderItem;
use App\Models\POS\Outlet;
use App\Services\POS\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * GET /api/pos/orders?outlet_id=&status=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with(['outlet', 'table', 'items', 'server']);

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->integer('outlet_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->open();
        }
        if ($request->filled('reservation_id')) {
            $query->where('reservation_id', $request->integer('reservation_id'));
        }

        return response()->json([
            'data' => $query->orderByDesc('opened_at')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $order->load([
                'outlet', 'table', 'items.menuItem', 'server',
                'tickets.items', 'tickets.station',
                'reservation', 'folio',
            ]),
        ]);
    }

    /**
     * POST /api/pos/orders
     */
    public function store(Request $request, Outlet $outlet): JsonResponse
    {
        $data = $request->validate([
            'order_type' => ['nullable', Rule::in([
                Order::TYPE_DINE_IN,
                Order::TYPE_ROOM_SERVICE,
                Order::TYPE_TAKEAWAY,
                Order::TYPE_DELIVERY,
                Order::TYPE_BANQUET,
            ])],
            'table_id' => ['nullable', 'integer', 'exists:pos_tables,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'folio_id' => ['nullable', 'integer', 'exists:folios,id'],
            'room_number' => ['nullable', 'string', 'max:20'],
            'guest_name' => ['nullable', 'string', 'max:200'],
            'covers' => ['nullable', 'integer', 'min:1', 'max:200'],
            'server_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $order = $this->orders->open($outlet, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $order], 201);
    }

    /**
     * POST /api/pos/orders/{order}/items
     */
    public function addItems(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:pos_menu_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*.name' => ['required_with:items.*.modifiers', 'string', 'max:100'],
            'items.*.modifiers.*.price_delta' => ['required_with:items.*.modifiers', 'numeric'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:500'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $items = $this->orders->addItems($order, $data['items']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $items,
            'order' => $order->fresh(),
        ], 201);
    }

    /**
     * POST /api/pos/orders/{order}/send-to-kitchen
     */
    public function sendToKitchen(Order $order): JsonResponse
    {
        try {
            $tickets = $this->orders->sendToKitchen($order);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => ['tickets' => $tickets, 'order' => $order->fresh()],
        ]);
    }

    /**
     * POST /api/pos/orders/{order}/items/{item}/void
     */
    public function voidItem(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        if ($item->order_id !== $order->id) {
            return response()->json(['message' => 'Item does not belong to order.'], 422);
        }
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $item = $this->orders->voidItem($item, $data['reason']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $item, 'order' => $order->fresh()]);
    }

    /**
     * POST /api/pos/orders/{order}/discount
     */
    public function applyDiscount(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $order = $this->orders->applyDiscount($order, $data['amount'], $data['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * POST /api/pos/orders/{order}/bill
     */
    public function bill(Order $order): JsonResponse
    {
        try {
            $order = $this->orders->bill($order);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * POST /api/pos/orders/{order}/charge-to-folio
     */
    public function chargeToFolio(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'folio_id' => ['required', 'integer', 'exists:folios,id'],
        ]);

        $folio = Folio::findOrFail($data['folio_id']);

        try {
            $order = $this->orders->chargeToFolio($order, $folio);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * POST /api/pos/orders/{order}/settle
     */
    public function settle(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'gateway'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'card_last4' => ['nullable', 'string', 'size:4'],
            'card_brand' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $order = $this->orders->settle($order, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $order]);
    }
}
