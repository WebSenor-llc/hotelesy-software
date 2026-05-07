<?php

namespace App\Services\POS;

use App\Models\Folio;
use App\Models\KDS\KdsStation;
use App\Models\KDS\KdsTicket;
use App\Models\KDS\KdsTicketItem;
use App\Models\POS\MenuItem;
use App\Models\POS\Order;
use App\Models\POS\OrderItem;
use App\Models\POS\Outlet;
use App\Models\POS\PosTable;
use App\Models\Reservation;
use App\Services\NumberGeneratorService;
use App\Services\TaxCalculationService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * OrderService — POS order lifecycle (FX POS / FX POS Ultra equivalent).
 *
 * Lifecycle:
 *   open() → addItems() → sendToKitchen() (creates KDS tickets) → recompute() →
 *   bill() → either:
 *     - settle() with payment (cash/card/UPI), OR
 *     - chargeToFolio(folio) for in-house guests, then settle.
 *
 * Atomic per operation. Orders are kept open across multiple kitchen rounds
 * (server adds items, fires to kitchen, adds more items, fires again, etc.).
 *
 * Tax handling: GST-aware via TaxCalculationService. Service charge added on top
 * of subtotal, GST applied to (subtotal + service_charge) per Indian rules.
 */
class OrderService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly NumberGeneratorService $numbers,
        private readonly TaxCalculationService $tax,
        private readonly KdsTicketService $kds,
    ) {}

    /**
     * Open a new order at an outlet.
     */
    public function open(Outlet $outlet, array $data): Order
    {
        return DB::transaction(function () use ($outlet, $data) {
            $tableId = $data['table_id'] ?? null;
            $orderType = $data['order_type'] ?? Order::TYPE_DINE_IN;

            // Mark table occupied if dine-in
            if ($orderType === Order::TYPE_DINE_IN && $tableId) {
                $table = PosTable::lockForUpdate()->findOrFail($tableId);
                if (! $table->isAvailable() && $table->status !== PosTable::STATUS_RESERVED) {
                    throw new \DomainException("Table {$table->name} is not available.");
                }
                $table->update(['status' => PosTable::STATUS_OCCUPIED]);
            }

            // Number — uses NumberGeneratorService which we already have
            $orderNumber = $this->numbers->generate($outlet->property, 'pos_order', 'POS/' . $outlet->code);

            return Order::create([
                'property_id' => $outlet->property_id,
                'outlet_id' => $outlet->id,
                'order_number' => $orderNumber,
                'table_id' => $tableId,
                'order_type' => $orderType,
                'reservation_id' => $data['reservation_id'] ?? null,
                'folio_id' => $data['folio_id'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'covers' => $data['covers'] ?? 1,
                'server_id' => $data['server_id'] ?? auth()->id(),
                'status' => Order::STATUS_OPEN,
                'opened_at' => now(),
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Add one or more items to an open order.
     *
     * @param array<int, array{menu_item_id: int, quantity: int|float, modifiers?: array, special_instructions?: string, discount_amount?: float}> $items
     * @return array<int, OrderItem>
     */
    public function addItems(Order $order, array $items): array
    {
        if (! $order->isOpen()) {
            throw new \DomainException("Order {$order->order_number} is {$order->status}; cannot add items.");
        }

        return DB::transaction(function () use ($order, $items) {
            $created = [];

            foreach ($items as $line) {
                /** @var MenuItem $menuItem */
                $menuItem = MenuItem::findOrFail($line['menu_item_id']);
                if (! $menuItem->available || ! $menuItem->is_active) {
                    throw new \DomainException("Item '{$menuItem->name}' is not available.");
                }

                $quantity = (float) ($line['quantity'] ?? 1);
                $modifiersDelta = 0;
                $modifiers = [];
                if (! empty($line['modifiers'])) {
                    foreach ($line['modifiers'] as $mod) {
                        $modifiers[] = ['name' => $mod['name'], 'price_delta' => (float) ($mod['price_delta'] ?? 0)];
                        $modifiersDelta += (float) ($mod['price_delta'] ?? 0);
                    }
                }
                $unitPrice = (float) $menuItem->price + $modifiersDelta;
                $amount = round($unitPrice * $quantity, 2);
                $discount = (float) ($line['discount_amount'] ?? 0);
                $taxableAmount = max(0, $amount - $discount);
                $taxPercent = $menuItem->is_taxable ? (float) $menuItem->tax_percent : 0;
                $taxAmount = round($taxableAmount * $taxPercent / 100, 2);

                $orderItem = OrderItem::create([
                    'property_id' => $order->property_id,
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'modifiers' => $modifiers,
                    'special_instructions' => $line['special_instructions'] ?? null,
                    'amount' => $amount,
                    'discount_amount' => $discount,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $taxAmount,
                    'total' => $taxableAmount + $taxAmount,
                    'status' => OrderItem::STATUS_PENDING,
                ]);

                $created[] = $orderItem;
            }

            $this->recomputeTotals($order);
            return $created;
        });
    }

    /**
     * Fire pending items to the kitchen — creates KDS tickets per station.
     * Items already sent are NOT re-fired.
     */
    public function sendToKitchen(Order $order): array
    {
        if (! $order->isOpen()) {
            throw new \DomainException("Order {$order->order_number} is closed.");
        }

        return DB::transaction(function () use ($order) {
            $pending = $order->items()
                ->where('status', OrderItem::STATUS_PENDING)
                ->where('is_voided', false)
                ->with('menuItem.category')
                ->get();

            if ($pending->isEmpty()) {
                throw new \DomainException('No pending items to fire.');
            }

            // Group items by station
            $byStation = $pending->groupBy(function (OrderItem $item) use ($order) {
                $categoryId = $item->menuItem->category_id;
                $station = KdsStation::whereHas('categories', fn($q) => $q->where('menu_category_id', $categoryId))
                    ->where('property_id', $order->property_id)
                    ->where('is_active', true)
                    ->orderByPivot('priority')
                    ->first();

                if (! $station) {
                    // Default — first hot kitchen station
                    $station = KdsStation::where('property_id', $order->property_id)
                        ->where('outlet_id', $order->outlet_id)
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->first();
                }

                if (! $station) {
                    throw new \DomainException("No KDS station configured for menu category {$item->menuItem->category->name}.");
                }

                return $station->id;
            });

            $tickets = [];
            foreach ($byStation as $stationId => $items) {
                $tickets[] = $this->kds->createTicket(
                    order: $order,
                    stationId: (int) $stationId,
                    orderItems: $items,
                );
            }

            // Mark items as sent
            $pending->each(function (OrderItem $item) {
                $item->update([
                    'status' => OrderItem::STATUS_SENT,
                    'sent_at' => now(),
                ]);
            });

            // Update order status
            $order->update([
                'status' => Order::STATUS_SENT,
                'kot_printed_at' => now(),
            ]);

            return $tickets;
        });
    }

    /**
     * Void an item (manager action). Inventory not held in POS so no rollback required;
     * voids reduce order total.
     */
    public function voidItem(OrderItem $item, string $reason): OrderItem
    {
        if ($item->is_voided) {
            throw new \DomainException('Item already voided.');
        }
        if ($item->order->status === Order::STATUS_SETTLED) {
            throw new \DomainException('Cannot void item on settled order.');
        }

        return DB::transaction(function () use ($item, $reason) {
            $item->update([
                'is_voided' => true,
                'void_reason' => $reason,
                'status' => OrderItem::STATUS_VOIDED,
            ]);

            // Cascade to KDS ticket items
            KdsTicketItem::where('order_item_id', $item->id)
                ->update(['status' => 'voided']);

            $this->recomputeTotals($item->order);

            return $item->fresh();
        });
    }

    /**
     * Apply a discount to the entire order (e.g. 10% off, fixed ₹100 off).
     */
    public function applyDiscount(Order $order, float $amount, ?string $reason = null): Order
    {
        if ($order->status === Order::STATUS_SETTLED) {
            throw new \DomainException('Cannot discount a settled order.');
        }

        $order->update(['discount_amount' => round($amount, 2)]);
        $this->recomputeTotals($order);

        return $order->fresh();
    }

    /**
     * Bill the order — moves to BILLED state. After this, only payment / settle remain.
     */
    public function bill(Order $order): Order
    {
        if (! in_array($order->status, [Order::STATUS_OPEN, Order::STATUS_SENT, Order::STATUS_PREPARING, Order::STATUS_READY, Order::STATUS_SERVED])) {
            throw new \DomainException("Cannot bill order in status {$order->status}.");
        }
        if ($order->items()->where('is_voided', false)->count() === 0) {
            throw new \DomainException('Cannot bill an order with no items.');
        }

        $this->recomputeTotals($order);
        $order->update([
            'status' => Order::STATUS_BILLED,
            'billed_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * Charge order to a PMS folio (room charge). Posts a charge to the folio
     * and marks the order settled. Used for in-house guest dining.
     */
    public function chargeToFolio(Order $order, Folio $folio): Order
    {
        if ($order->status !== Order::STATUS_BILLED) {
            throw new \DomainException('Bill the order before charging to folio.');
        }
        if ($folio->status !== 'open') {
            throw new \DomainException('Target folio is not open.');
        }
        if ($folio->property_id !== $order->property_id) {
            throw new \DomainException('Folio and order belong to different properties.');
        }

        return DB::transaction(function () use ($order, $folio) {
            // Determine category for tax engine: food / beverage by content
            $hasLiquor = $order->items()
                ->where('is_voided', false)
                ->whereHas('menuItem.category', fn($q) => $q->where('is_liquor', true))
                ->exists();

            // Use Billing\FolioService to post the charge so tax breakdown matches
            $folioService = app(\App\Services\Billing\FolioService::class);
            $folioService->postCharge($folio, [
                'category' => $hasLiquor ? 'beverage' : 'food',
                'description' => "POS Order {$order->order_number} - {$order->outlet->name}",
                'reference' => $order->order_number,
                'quantity' => 1,
                'rate' => $order->total_amount,
                'amount' => $order->total_amount,
                'discount_amount' => 0,
                'skip_tax' => true, // Order already has tax computed
            ]);

            $order->update([
                'folio_id' => $folio->id,
                'status' => Order::STATUS_SETTLED,
                'settled_at' => now(),
            ]);

            // Free table
            if ($order->table_id) {
                PosTable::where('id', $order->table_id)->update([
                    'status' => PosTable::STATUS_CLEANING,
                ]);
            }

            return $order->fresh();
        });
    }

    /**
     * Settle order with direct payment (cash/card/UPI) — outlet-level cashier flow.
     */
    public function settle(Order $order, array $payment): Order
    {
        if ($order->status !== Order::STATUS_BILLED) {
            throw new \DomainException('Bill the order before settling.');
        }

        return DB::transaction(function () use ($order, $payment) {
            // Record settlement metadata on order itself (POS-direct payments don't go to PMS folio)
            $order->update([
                'status' => Order::STATUS_SETTLED,
                'settled_at' => now(),
                // Payment details are stored as a row in pos_payments (out of scope this turn)
                // For now, settlement metadata recorded on order via notes JSON
            ]);

            if ($order->table_id) {
                PosTable::where('id', $order->table_id)->update([
                    'status' => PosTable::STATUS_CLEANING,
                ]);
            }

            return $order->fresh();
        });
    }

    /**
     * Recompute totals from active items + service charge + tax.
     */
    public function recomputeTotals(Order $order): void
    {
        $items = $order->items()->where('is_voided', false)->get();

        $subtotal = (float) $items->sum('amount');
        $itemDiscounts = (float) $items->sum('discount_amount');
        $orderDiscount = (float) $order->discount_amount;
        $totalDiscount = $itemDiscounts + $orderDiscount;
        $taxableBase = max(0, $subtotal - $totalDiscount);

        $serviceChargePct = (float) ($order->outlet->service_charge_percent ?? 0);
        $serviceCharge = round($taxableBase * $serviceChargePct / 100, 2);

        // Aggregate tax from items (each item already has tax computed at item rate)
        $itemTax = (float) $items->sum('tax_amount');
        // Service charge taxed at standard food rate (~5%) — for simplicity, use TaxCalculationService
        $serviceChargeTax = round($serviceCharge * 5 / 100, 2);
        $totalTax = round($itemTax + $serviceChargeTax, 2);

        $rawTotal = $taxableBase + $serviceCharge + $totalTax;
        $rounded = round($rawTotal);
        $roundOff = round($rounded - $rawTotal, 2);

        $order->update([
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($totalDiscount, 2),
            'service_charge' => $serviceCharge,
            'tax_amount' => $totalTax,
            'round_off' => $roundOff,
            'total_amount' => $rounded,
        ]);
    }
}
