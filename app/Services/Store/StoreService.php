<?php

namespace App\Services\Store;

use App\Models\Property;
use App\Models\Store\Grn;
use App\Models\Store\GrnItem;
use App\Models\Store\PurchaseOrder;
use App\Models\Store\PurchaseOrderItem;
use App\Models\Store\StockMovement;
use App\Models\Store\StoreItem;
use App\Models\Store\StoreVendor;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

/**
 * StoreService — purchase orders, GRN (goods receipt), stock movements.
 *
 * Cost method: Weighted Average (the most common in Indian hotel ops; alternatives
 * are FIFO/LIFO but Indian GST rules + simpler audit trail favour WA).
 *
 * Stock movement chain:
 *   PO → GRN (receipt, increases stock) → Issue (decreases stock to dept) →
 *        Optional Adjustment / Wastage / Transfer
 *
 * Cost computation on GRN:
 *   new_avg = (current_stock × current_avg + receipt_qty × receipt_price) / total_qty
 */
class StoreService
{
    public function __construct(private readonly NumberGeneratorService $numbers) {}

    public function createPurchaseOrder(Property $property, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($property, $data) {
            $vendor = StoreVendor::findOrFail($data['vendor_id']);

            $po = PurchaseOrder::create([
                'property_id' => $property->id,
                'vendor_id' => $vendor->id,
                'po_number' => $this->numbers->generate($property, 'po', 'PO/' . $property->code),
                'po_date' => $data['po_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;
            $taxTotal = 0;

            foreach ($data['items'] as $line) {
                $item = StoreItem::findOrFail($line['item_id']);
                $qty = (float) $line['quantity'];
                $price = (float) $line['unit_price'];
                $taxPct = (float) ($line['tax_percent'] ?? 0);
                $amount = $qty * $price;
                $tax = round($amount * $taxPct / 100, 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_id' => $item->id,
                    'quantity_ordered' => $qty,
                    'unit_price' => $price,
                    'tax_percent' => $taxPct,
                    'amount' => $amount,
                ]);

                $subtotal += $amount;
                $taxTotal += $tax;
            }

            $po->update([
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxTotal, 2),
                'total_amount' => round($subtotal + $taxTotal, 2),
            ]);

            return $po->fresh('items');
        });
    }

    public function approvePO(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new \DomainException("Only draft POs can be approved.");
        }
        $po->update([
            'status' => PurchaseOrder::STATUS_SENT,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        return $po->fresh();
    }

    /**
     * Receive goods against a PO. Creates a GRN, increments stock, recomputes
     * weighted-average cost per item.
     *
     * $items shape: [['item_id' => 1, 'quantity' => 10, 'unit_price' => 50, 'expiry_date' => '2025-12-31'], ...]
     */
    public function receiveGoods(PurchaseOrder $po, array $data): Grn
    {
        return DB::transaction(function () use ($po, $data) {
            $grn = Grn::create([
                'property_id' => $po->property_id,
                'vendor_id' => $po->vendor_id,
                'purchase_order_id' => $po->id,
                'grn_number' => $this->numbers->generate($po->property, 'grn', 'GRN/' . $po->property->code),
                'grn_date' => $data['grn_date'] ?? now()->toDateString(),
                'vendor_invoice_number' => $data['vendor_invoice_number'] ?? null,
                'vendor_invoice_date' => $data['vendor_invoice_date'] ?? null,
                'received_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $totalAmount = 0;

            foreach ($data['items'] as $line) {
                /** @var StoreItem $item */
                $item = StoreItem::lockForUpdate()->findOrFail($line['item_id']);
                $qty = (float) $line['quantity'];
                $price = (float) $line['unit_price'];

                // Weighted-average cost recomputation
                $oldStock = (float) $item->current_stock;
                $oldAvg = (float) $item->average_cost;
                $newStock = $oldStock + $qty;
                $newAvg = $newStock > 0
                    ? round((($oldStock * $oldAvg) + ($qty * $price)) / $newStock, 2)
                    : $price;

                $item->update([
                    'current_stock' => round($newStock, 3),
                    'average_cost' => $newAvg,
                    'last_purchase_price' => $price,
                ]);

                GrnItem::create([
                    'grn_id' => $grn->id,
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'amount' => round($qty * $price, 2),
                    'expiry_date' => $line['expiry_date'] ?? null,
                ]);

                StockMovement::create([
                    'property_id' => $po->property_id,
                    'item_id' => $item->id,
                    'movement_date' => $grn->grn_date,
                    'movement_type' => StockMovement::TYPE_RECEIPT,
                    'quantity' => $qty,
                    'unit_cost' => $price,
                    'total_cost' => round($qty * $price, 2),
                    'reference_type' => 'grn',
                    'reference_id' => $grn->id,
                    'performed_by' => auth()->id(),
                ]);

                // Update PO line received qty
                PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->where('item_id', $item->id)
                    ->increment('quantity_received', $qty);

                $totalAmount += $qty * $price;
            }

            $grn->update(['total_amount' => round($totalAmount, 2)]);

            // Update PO status: 'received' if all items fully received, else 'partial'
            $unfilledLines = PurchaseOrderItem::where('purchase_order_id', $po->id)
                ->whereColumn('quantity_received', '<', 'quantity_ordered')
                ->count();

            $po->update([
                'status' => $unfilledLines === 0
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIAL,
            ]);

            return $grn->fresh('items');
        });
    }

    /**
     * Issue items to a department (decrements stock at average cost).
     */
    public function issue(StoreItem $item, float $quantity, ?string $reason = null): StockMovement
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Issue quantity must be positive.');
        }

        return DB::transaction(function () use ($item, $quantity, $reason) {
            $item->refresh();
            if ((float) $item->current_stock < $quantity) {
                throw new \DomainException("Insufficient stock for {$item->name}: have {$item->current_stock}, need {$quantity}.");
            }

            $cost = (float) $item->average_cost;

            $item->update(['current_stock' => round((float) $item->current_stock - $quantity, 3)]);

            return StockMovement::create([
                'property_id' => $item->property_id,
                'item_id' => $item->id,
                'movement_date' => now()->toDateString(),
                'movement_type' => StockMovement::TYPE_ISSUE,
                'quantity' => -$quantity,
                'unit_cost' => $cost,
                'total_cost' => -round($quantity * $cost, 2),
                'notes' => $reason,
                'performed_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Manual adjustment (positive or negative) — for stock takes / reconciliations.
     */
    public function adjust(StoreItem $item, float $deltaQuantity, string $reason): StockMovement
    {
        return DB::transaction(function () use ($item, $deltaQuantity, $reason) {
            $newStock = max(0, (float) $item->current_stock + $deltaQuantity);
            $item->update(['current_stock' => round($newStock, 3)]);

            return StockMovement::create([
                'property_id' => $item->property_id,
                'item_id' => $item->id,
                'movement_date' => now()->toDateString(),
                'movement_type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => $deltaQuantity,
                'unit_cost' => (float) $item->average_cost,
                'total_cost' => round($deltaQuantity * (float) $item->average_cost, 2),
                'notes' => $reason,
                'performed_by' => auth()->id(),
            ]);
        });
    }
}
