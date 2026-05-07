<?php

namespace App\Livewire\Store;

use App\Models\Store\PurchaseOrder;
use App\Models\Store\StockMovement;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreItem;
use App\Models\Store\StoreVendor;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Dashboard extends Component
{
    public string $tab = 'items';

    // Item form
    public bool $showItemForm = false;
    public ?int $editingItemId = null;
    public string $itemCode = '';
    public string $itemName = '';
    public ?int $itemCategoryId = null;
    public string $itemUnit = 'pcs';
    public float $itemReorder = 0;
    public float $itemMax = 0;
    public bool $itemActive = true;

    // Vendor form
    public bool $showVendorForm = false;
    public ?int $editingVendorId = null;
    public string $vCode = '';
    public string $vName = '';
    public string $vContact = '';
    public string $vPhone = '';
    public string $vEmail = '';
    public string $vGst = '';
    public string $vAddress = '';
    public int $vTerms = 30;

    // Purchase / GRN form
    public bool $showPurchaseForm = false;
    public ?int $purchaseVendorId = null;
    public array $purchaseLines = []; // [['item_id'=>, 'qty'=>, 'price'=>]]
    public string $purchaseInvoiceNumber = '';

    // Issue / consume form
    public bool $showIssueForm = false;
    public ?int $issueItemId = null;
    public float $issueQty = 1;
    public string $issueDept = 'kitchen';
    public string $issueNotes = '';

    // Adjustment
    public bool $showAdjustForm = false;
    public ?int $adjustItemId = null;
    public float $adjustQty = 0;
    public string $adjustReason = '';

    public function setTab(string $t): void { $this->tab = $t; }

    /* ---------- ITEM CRUD ---------- */
    public function startCreateItem(): void
    {
        $this->resetItemForm();
        $this->editingItemId = null;
        $this->showItemForm = true;
    }

    public function startEditItem(int $id): void
    {
        $i = StoreItem::findOrFail($id);
        $this->editingItemId = $i->id;
        $this->itemCode = $i->code;
        $this->itemName = $i->name;
        $this->itemCategoryId = $i->category_id;
        $this->itemUnit = $i->unit;
        $this->itemReorder = (float) $i->reorder_level;
        $this->itemMax = (float) ($i->max_stock ?? 0);
        $this->itemActive = (bool) $i->is_active;
        $this->showItemForm = true;
    }

    public function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->itemCode = '';
        $this->itemName = '';
        $this->itemCategoryId = null;
        $this->itemUnit = 'pcs';
        $this->itemReorder = 0;
        $this->itemMax = 0;
        $this->itemActive = true;
    }
    public function cancelItemForm(): void { $this->showItemForm = false; }

    public function saveItem(): void
    {
        $this->validate([
            'itemCode' => 'required',
            'itemName' => 'required',
            'itemCategoryId' => 'required|exists:store_categories,id',
        ]);
        $ctx = app(TenantContext::class);
        $data = [
            'tenant_id' => $ctx->tenantId(),
            'property_id' => $ctx->propertyId(),
            'category_id' => $this->itemCategoryId,
            'code' => strtoupper($this->itemCode),
            'name' => $this->itemName,
            'unit' => $this->itemUnit,
            'reorder_level' => $this->itemReorder,
            'max_stock' => $this->itemMax > 0 ? $this->itemMax : null,
            'is_active' => $this->itemActive,
        ];
        if ($this->editingItemId) {
            StoreItem::findOrFail($this->editingItemId)->update($data);
            session()->flash('success', "Item updated.");
        } else {
            $data['current_stock'] = 0;
            StoreItem::create($data);
            session()->flash('success', "Item created.");
        }
        $this->showItemForm = false;
        $this->resetItemForm();
    }

    /* ---------- VENDOR CRUD ---------- */
    public function startCreateVendor(): void { $this->resetVendorForm(); $this->editingVendorId = null; $this->showVendorForm = true; }

    public function startEditVendor(int $id): void
    {
        $v = StoreVendor::findOrFail($id);
        $this->editingVendorId = $v->id;
        $this->vCode = $v->code;
        $this->vName = $v->name;
        $this->vContact = (string) $v->contact_person;
        $this->vPhone = (string) $v->phone;
        $this->vEmail = (string) $v->email;
        $this->vGst = (string) $v->gst_number;
        $this->vAddress = (string) $v->address;
        $this->vTerms = (int) $v->payment_terms_days;
        $this->showVendorForm = true;
    }

    public function resetVendorForm(): void
    {
        $this->editingVendorId = null;
        $this->vCode = '';
        $this->vName = '';
        $this->vContact = '';
        $this->vPhone = '';
        $this->vEmail = '';
        $this->vGst = '';
        $this->vAddress = '';
        $this->vTerms = 30;
    }
    public function cancelVendorForm(): void { $this->showVendorForm = false; }

    public function saveVendor(): void
    {
        $this->validate(['vCode' => 'required', 'vName' => 'required']);
        $ctx = app(TenantContext::class);
        $data = [
            'tenant_id' => $ctx->tenantId(),
            'property_id' => $ctx->propertyId(),
            'code' => strtoupper($this->vCode),
            'name' => $this->vName,
            'contact_person' => $this->vContact,
            'phone' => $this->vPhone,
            'email' => $this->vEmail,
            'gst_number' => $this->vGst,
            'address' => $this->vAddress,
            'payment_terms_days' => $this->vTerms,
            'is_active' => true,
        ];
        if ($this->editingVendorId) {
            StoreVendor::findOrFail($this->editingVendorId)->update($data);
            session()->flash('success', 'Vendor updated.');
        } else {
            StoreVendor::create($data);
            session()->flash('success', 'Vendor created.');
        }
        $this->showVendorForm = false;
        $this->resetVendorForm();
    }

    /* ---------- PURCHASE / GRN ---------- */
    public function startPurchase(): void
    {
        $this->purchaseVendorId = null;
        $this->purchaseLines = [['item_id' => null, 'qty' => 1, 'price' => 0]];
        $this->purchaseInvoiceNumber = '';
        $this->showPurchaseForm = true;
    }
    public function cancelPurchase(): void { $this->showPurchaseForm = false; }
    public function addPurchaseLine(): void { $this->purchaseLines[] = ['item_id' => null, 'qty' => 1, 'price' => 0]; }
    public function removePurchaseLine(int $i): void
    {
        if (isset($this->purchaseLines[$i])) {
            unset($this->purchaseLines[$i]);
            $this->purchaseLines = array_values($this->purchaseLines);
        }
    }

    public function recordPurchase(): void
    {
        $this->validate([
            'purchaseVendorId' => 'required|exists:store_vendors,id',
            'purchaseLines' => 'required|array|min:1',
        ]);
        $valid = collect($this->purchaseLines)->filter(fn ($l) => !empty($l['item_id']) && (float) ($l['qty'] ?? 0) > 0);
        if ($valid->isEmpty()) {
            session()->flash('error', 'Add at least one item with quantity.');
            return;
        }

        $ctx = app(TenantContext::class);
        $now = now();
        DB::transaction(function () use ($ctx, $valid, $now) {
            $total = $valid->sum(fn ($l) => (float) $l['qty'] * (float) $l['price']);
            $po = PurchaseOrder::create([
                'tenant_id' => $ctx->tenantId(),
                'property_id' => $ctx->propertyId(),
                'vendor_id' => $this->purchaseVendorId,
                'po_number' => 'PO-' . $now->format('ymd') . '-' . str_pad((string) (PurchaseOrder::count() + 1), 4, '0', STR_PAD_LEFT),
                'po_date' => $now->toDateString(),
                'expected_delivery_date' => $now->toDateString(),
                'status' => 'received',
                'subtotal' => $total,
                'tax_amount' => 0,
                'total_amount' => $total,
                'notes' => $this->purchaseInvoiceNumber ? "Vendor invoice: {$this->purchaseInvoiceNumber}" : null,
                'created_by' => auth()->id(),
                'approved_by' => auth()->id(),
                'approved_at' => $now,
            ]);

            foreach ($valid as $line) {
                $item = StoreItem::find($line['item_id']);
                if (!$item) continue;
                $qty = (float) $line['qty'];
                $price = (float) $line['price'];

                DB::table('store_purchase_order_items')->insert([
                    'purchase_order_id' => $po->id,
                    'item_id' => $item->id,
                    'quantity_ordered' => $qty,
                    'quantity_received' => $qty,
                    'unit_price' => $price,
                    'tax_percent' => 0,
                    'amount' => $qty * $price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                StockMovement::create([
                    'tenant_id' => $ctx->tenantId(),
                    'property_id' => $ctx->propertyId(),
                    'item_id' => $item->id,
                    'movement_date' => $now->toDateString(),
                    'movement_type' => 'receipt',
                    'quantity' => $qty,
                    'unit_cost' => $price,
                    'total_cost' => $qty * $price,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $po->id,
                    'notes' => "Receipt against PO {$po->po_number}",
                    'performed_by' => auth()->id(),
                ]);

                $newStock = (float) $item->current_stock + $qty;
                $newAvg = $newStock > 0
                    ? ((((float) $item->current_stock * (float) $item->average_cost) + ($qty * $price)) / $newStock)
                    : (float) $item->average_cost;
                $item->update([
                    'current_stock' => $newStock,
                    'average_cost' => round($newAvg, 2),
                    'last_purchase_price' => $price,
                ]);
            }
        });

        session()->flash('success', 'Purchase recorded — stock updated.');
        $this->showPurchaseForm = false;
    }

    /* ---------- ISSUE / CONSUME ---------- */
    public function startIssue(?int $itemId = null): void
    {
        $this->issueItemId = $itemId;
        $this->issueQty = 1;
        $this->issueDept = 'kitchen';
        $this->issueNotes = '';
        $this->showIssueForm = true;
    }
    public function cancelIssue(): void { $this->showIssueForm = false; }

    public function recordIssue(): void
    {
        $this->validate([
            'issueItemId' => 'required|exists:store_items,id',
            'issueQty' => 'required|numeric|min:0.001',
        ]);
        $ctx = app(TenantContext::class);
        $item = StoreItem::findOrFail($this->issueItemId);
        if ((float) $item->current_stock < $this->issueQty) {
            session()->flash('error', "Insufficient stock. Available: {$item->current_stock} {$item->unit}.");
            return;
        }

        DB::transaction(function () use ($ctx, $item) {
            StockMovement::create([
                'tenant_id' => $ctx->tenantId(),
                'property_id' => $ctx->propertyId(),
                'item_id' => $item->id,
                'movement_date' => today()->toDateString(),
                'movement_type' => 'issue',
                'quantity' => -1 * $this->issueQty,
                'unit_cost' => $item->average_cost,
                'total_cost' => $this->issueQty * (float) $item->average_cost,
                'reference_type' => 'department',
                'reference_id' => null,
                'notes' => "Issued to {$this->issueDept}" . ($this->issueNotes ? " · {$this->issueNotes}" : ''),
                'performed_by' => auth()->id(),
            ]);
            $item->update(['current_stock' => (float) $item->current_stock - $this->issueQty]);
        });

        session()->flash('success', "{$this->issueQty} {$item->unit} of {$item->name} issued to {$this->issueDept}.");
        $this->showIssueForm = false;
    }

    /* ---------- ADJUSTMENT ---------- */
    public function startAdjust(?int $itemId = null): void
    {
        $this->adjustItemId = $itemId;
        $this->adjustQty = 0;
        $this->adjustReason = '';
        $this->showAdjustForm = true;
    }
    public function cancelAdjust(): void { $this->showAdjustForm = false; }

    public function recordAdjust(): void
    {
        $this->validate([
            'adjustItemId' => 'required|exists:store_items,id',
            'adjustQty' => 'required|numeric',
            'adjustReason' => 'required|min:3',
        ]);
        $ctx = app(TenantContext::class);
        $item = StoreItem::findOrFail($this->adjustItemId);

        DB::transaction(function () use ($ctx, $item) {
            StockMovement::create([
                'tenant_id' => $ctx->tenantId(),
                'property_id' => $ctx->propertyId(),
                'item_id' => $item->id,
                'movement_date' => today()->toDateString(),
                'movement_type' => 'adjustment',
                'quantity' => $this->adjustQty,
                'unit_cost' => $item->average_cost,
                'total_cost' => abs($this->adjustQty) * (float) $item->average_cost,
                'reference_type' => 'manual',
                'notes' => "Adjustment: {$this->adjustReason}",
                'performed_by' => auth()->id(),
            ]);
            $item->update(['current_stock' => (float) $item->current_stock + $this->adjustQty]);
        });

        session()->flash('success', 'Stock adjusted.');
        $this->showAdjustForm = false;
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $items = StoreItem::where('property_id', $propertyId)->with('category')->orderBy('name')->limit(100)->get();
        $allActiveItems = StoreItem::where('property_id', $propertyId)->where('is_active', true)->orderBy('name')->get();
        $vendors = StoreVendor::where('property_id', $propertyId)->orderBy('name')->limit(50)->get();
        $categories = StoreCategory::where('property_id', $propertyId)->orderBy('name')->get();
        $purchaseOrders = PurchaseOrder::where('property_id', $propertyId)->with('vendor')->orderByDesc('created_at')->limit(20)->get();
        $movements = StockMovement::where('property_id', $propertyId)->with('item')->orderByDesc('created_at')->limit(30)->get();
        $lowStock = StoreItem::where('property_id', $propertyId)->whereColumn('current_stock', '<', 'reorder_level')->limit(20)->get();

        $stats = [
            'items' => StoreItem::where('property_id', $propertyId)->count(),
            'vendors' => StoreVendor::where('property_id', $propertyId)->count(),
            'open_pos' => PurchaseOrder::where('property_id', $propertyId)->whereIn('status', ['draft', 'sent', 'partial'])->count(),
            'low_stock' => StoreItem::where('property_id', $propertyId)->whereColumn('current_stock', '<', 'reorder_level')->count(),
        ];

        return view('livewire.store.dashboard', compact('items', 'allActiveItems', 'vendors', 'categories', 'purchaseOrders', 'movements', 'lowStock', 'stats'));
    }
}
