<?php

namespace App\Livewire\POS;

use App\Models\Folio;
use App\Models\KDS\KdsStation;
use App\Models\KDS\KdsTicket;
use App\Models\Payment;
use App\Models\POS\MenuCategory;
use App\Models\POS\MenuItem;
use App\Models\POS\Order;
use App\Models\POS\Outlet;
use App\Models\POS\PosTable;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\Billing\FolioService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class PosDashboard extends Component
{
    public ?int $selectedOutletId = null;

    // Menu filters
    public ?int $selectedCategoryId = null;        // null = all categories
    public string $foodTypeFilter = 'all';          // all | veg | non_veg | egg | jain
    public string $menuSearch = '';

    // New order form
    public bool $showNewOrder = false;
    public string $orderType = 'dine_in';
    public ?int $tableId = null;
    public ?int $reservationId = null;
    public string $guestName = '';
    public string $guestPhone = '';
    public string $deliveryAddress = '';
    public int $covers = 2;
    public array $orderLines = []; // [['menu_item_id'=>, 'qty'=>1]]

    // Payment timing at order placement
    // 'on_bill'      — collect when bill is generated (default)
    // 'prepaid'      — collect immediately at order placement
    // 'room_charge'  — auto-post to room folio (room_service only)
    public string $paymentTiming = 'on_bill';
    public string $prepaidMode = 'cash';        // cash | card | upi | wallet | bank_transfer
    public float $prepaidAmount = 0;
    public string $prepaidReference = '';

    // Settle / payment form
    public ?int $settleOrderId = null;
    public string $payMode = 'cash';
    public float $payAmount = 0;
    public string $payReference = '';
    public bool $chargeToRoom = false;

    // View / print bill (read-only snapshot)
    public ?int $viewBillId = null;

    public function mount(): void
    {
        $ctx = app(TenantContext::class);
        // Default to outlet that actually has menu items
        $first = Outlet::where('property_id', $ctx->propertyId())
            ->whereHas('menuCategories.items')
            ->orderBy('id')
            ->first()
            ?? Outlet::where('property_id', $ctx->propertyId())->first();
        $this->selectedOutletId = $first?->id;
    }

    public function setFoodType(string $type): void
    {
        $this->foodTypeFilter = in_array($type, ['all','veg','non_veg','egg','jain'], true) ? $type : 'all';
    }

    public function setCategory(?int $id): void
    {
        $this->selectedCategoryId = $id;
    }

    public function clearFilters(): void
    {
        $this->foodTypeFilter = 'all';
        $this->selectedCategoryId = null;
        $this->menuSearch = '';
    }

    public function startNewOrder(): void
    {
        $this->reset(['orderLines', 'guestName', 'guestPhone', 'deliveryAddress', 'tableId', 'reservationId', 'prepaidAmount', 'prepaidReference']);
        $this->orderType = 'dine_in';
        $this->covers = 2;
        $this->paymentTiming = 'on_bill';
        $this->prepaidMode = 'cash';
        $this->showNewOrder = true;
    }

    /**
     * Auto-adjust defaults when the order type changes:
     * - Switching to room_service → clear table, default payment timing to 'room_charge'
     * - Switching to dine_in → clear reservation, payment timing back to 'on_bill'
     * - Takeaway/delivery → suggest 'prepaid'
     */
    public function updatedOrderType(string $value): void
    {
        switch ($value) {
            case 'room_service':
                $this->tableId = null;
                $this->paymentTiming = 'room_charge';
                $this->covers = 1;
                break;
            case 'takeaway':
            case 'delivery':
                $this->tableId = null;
                $this->reservationId = null;
                $this->paymentTiming = 'prepaid';
                break;
            default: // dine_in
                $this->reservationId = null;
                $this->paymentTiming = 'on_bill';
        }
    }
    public function cancelNewOrder(): void { $this->showNewOrder = false; }

    public function addItem(int $menuItemId): void
    {
        foreach ($this->orderLines as $i => $l) {
            if ((int) $l['menu_item_id'] === $menuItemId) {
                $this->orderLines[$i]['qty']++;
                return;
            }
        }
        $this->orderLines[] = ['menu_item_id' => $menuItemId, 'qty' => 1];
    }
    public function changeQty(int $i, int $delta): void
    {
        if (!isset($this->orderLines[$i])) return;
        $this->orderLines[$i]['qty'] = max(0, $this->orderLines[$i]['qty'] + $delta);
        if ($this->orderLines[$i]['qty'] === 0) {
            unset($this->orderLines[$i]);
            $this->orderLines = array_values($this->orderLines);
        }
    }

    public function placeOrder(): void
    {
        if (empty($this->orderLines)) {
            session()->flash('error', 'Add at least one item.');
            return;
        }
        // Validate room_service has a reservation
        if ($this->orderType === 'room_service' && ! $this->reservationId) {
            session()->flash('error', 'Room service orders need an in-house guest selected.');
            return;
        }
        // room_charge timing only valid with reservation
        if ($this->paymentTiming === 'room_charge' && ! $this->reservationId) {
            session()->flash('error', 'Charge-to-room requires an in-house guest. Pick a reservation or change payment timing.');
            return;
        }

        $ctx = app(TenantContext::class);
        $outlet = Outlet::findOrFail($this->selectedOutletId);
        $items = MenuItem::whereIn('id', collect($this->orderLines)->pluck('menu_item_id'))->get()->keyBy('id');

        $subtotal = 0; $tax = 0;
        $orderItems = [];
        foreach ($this->orderLines as $line) {
            $mi = $items->get($line['menu_item_id']); if (!$mi) continue;
            $qty = max(1, (int) $line['qty']);
            $unit = (float) $mi->price;
            $lineAmt = $qty * $unit;
            $lineTax = round($lineAmt * (($mi->tax_percent ?? 5) / 100), 2);
            $subtotal += $lineAmt; $tax += $lineTax;
            $orderItems[] = compact('mi', 'qty', 'unit', 'lineAmt', 'lineTax');
        }
        $sc = round($subtotal * (($outlet->service_charge_percent ?? 10) / 100), 2);
        $total = $subtotal + $tax + $sc;
        $now = now();

        $reservation = $this->reservationId ? Reservation::find($this->reservationId) : null;

        $createdOrderId = null;
        DB::transaction(function () use ($ctx, $outlet, $orderItems, $subtotal, $tax, $sc, $total, $now, $reservation, &$createdOrderId) {
            $orderNote = "Payment timing: " . $this->paymentTiming;
            if ($this->paymentTiming === 'prepaid') {
                $orderNote .= " (paid {$this->prepaidMode}" . ($this->prepaidReference ? " ref {$this->prepaidReference}" : '') . ")";
            }
            $order = Order::create([
                'tenant_id' => $ctx->tenantId(), 'property_id' => $ctx->propertyId(),
                'outlet_id' => $outlet->id,
                'order_number' => 'ORD-' . $now->format('ymd') . '-' . str_pad((string) (Order::count() + 1), 4, '0', STR_PAD_LEFT),
                'table_id' => $this->orderType === 'dine_in' ? $this->tableId : null,
                'order_type' => $this->orderType,
                'reservation_id' => $reservation?->id,
                'room_number' => $reservation?->rooms?->first()?->room?->number,
                'guest_name' => $this->guestName ?: ($reservation?->guest_name ?: 'Walk-in'),
                'guest_phone' => $this->guestPhone ?: $reservation?->guest_phone,
                'delivery_address' => $this->orderType === 'delivery' ? $this->deliveryAddress : null,
                'covers' => $this->covers, 'server_id' => auth()->id(),
                'status' => 'sent_to_kitchen',
                'opened_at' => $now, 'kot_printed_at' => $now,
                'subtotal' => $subtotal, 'service_charge' => $sc, 'tax_amount' => $tax,
                'discount_amount' => 0, 'round_off' => 0, 'total_amount' => $total,
                'payment_timing' => $this->paymentTiming,
                'payment_mode' => $this->paymentTiming === 'prepaid' ? $this->prepaidMode : null,
                'payment_reference' => $this->paymentTiming === 'prepaid' ? ($this->prepaidReference ?: null) : null,
                'paid_at' => $this->paymentTiming === 'prepaid' ? $now : null,
                'notes' => $orderNote,
                'created_by' => auth()->id(),
            ]);
            $createdOrderId = $order->id;

            foreach ($orderItems as $oi) {
                DB::table('pos_order_items')->insert([
                    'tenant_id' => $ctx->tenantId(), 'property_id' => $ctx->propertyId(),
                    'order_id' => $order->id, 'menu_item_id' => $oi['mi']->id,
                    'item_name' => $oi['mi']->name,
                    'quantity' => $oi['qty'], 'unit_price' => $oi['unit'],
                    'amount' => $oi['lineAmt'], 'tax_percent' => $oi['mi']->tax_percent ?? 5,
                    'tax_amount' => $oi['lineTax'], 'total' => $oi['lineAmt'] + $oi['lineTax'],
                    'status' => 'sent',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            // KDS ticket
            $station = KdsStation::where('outlet_id', $outlet->id)->first()
                ?? KdsStation::where('property_id', $ctx->propertyId())->first();
            if ($station) {
                DB::table('kds_tickets')->insert([
                    'tenant_id' => $ctx->tenantId(), 'property_id' => $ctx->propertyId(),
                    'order_id' => $order->id, 'station_id' => $station->id,
                    'ticket_number' => 'KOT-' . $order->id,
                    'status' => 'queued', 'queued_at' => $now,
                    'priority' => 'normal', 'is_recall' => false, 'is_overdue' => false,
                    'target_prep_seconds' => 900,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });

        // If prepaid, record the Payment immediately so today's revenue is correct.
        if ($this->paymentTiming === 'prepaid' && $createdOrderId) {
            try {
                Payment::create([
                    'tenant_id'             => $ctx->tenantId(),
                    'property_id'           => $ctx->propertyId(),
                    'folio_id'              => null,
                    'reservation_id'        => $reservation?->id,
                    'receipt_number'        => 'RCPT-' . $now->format('ymd') . '-' . str_pad((string) (Payment::count() + 1), 4, '0', STR_PAD_LEFT),
                    'payment_date'          => $now->toDateString(),
                    'business_date'         => $now->toDateString(),
                    'mode'                  => $this->prepaidMode,
                    'amount'                => $this->prepaidAmount > 0 ? $this->prepaidAmount : $total,
                    'currency'              => 'INR',
                    'transaction_reference' => $this->prepaidReference ?: null,
                    'status'                => 'completed',
                    'received_by'           => auth()->id(),
                    'notes'                 => "POS prepaid · order #{$createdOrderId}",
                    'payable_type'          => Order::class,
                    'payable_id'            => $createdOrderId,
                ]);
            } catch (\Throwable $e) {
                // Non-fatal — Payment may have stricter constraints. Log and continue.
                \Log::warning('POS prepaid Payment record failed: ' . $e->getMessage());
            }
        }

        $this->showNewOrder = false;
        $this->orderLines = [];
        $msg = match ($this->paymentTiming) {
            'prepaid'     => 'Order sent to kitchen · prepaid ' . strtoupper($this->prepaidMode) . ' ₹' . number_format($total, 2),
            'room_charge' => 'Order sent to kitchen · will be charged to room folio',
            default       => 'Order sent to kitchen · payment will be collected at billing',
        };
        session()->flash('success', $msg);
    }

    /**
     * Generate the bill: any active state → billed.
     * Snapshots totals as billed_at and stops further KOT additions.
     * Tolerates skipping through kitchen states for takeaway / quick service.
     */
    public function generateBill(int $id): void
    {
        $o = Order::findOrFail($id);
        if (in_array($o->status, ['settled', 'voided'])) {
            session()->flash('error', "Order is already {$o->status} — cannot bill again.");
            return;
        }
        if ($o->status === 'billed') {
            // Already billed — just open the view
            $this->openBill($id);
            return;
        }
        $o->update(['status' => 'billed', 'billed_at' => now()]);
        // Auto-open the bill so the cashier can review/print
        $this->viewBillId = $o->id;
        session()->flash('success', "Bill generated for {$o->order_number}. Total ₹" . number_format((float) $o->total_amount, 2) . ".");
    }

    /**
     * Mark a ready order as served (for floor staff workflow).
     */
    public function markServed(int $id): void
    {
        $o = Order::findOrFail($id);
        if (! in_array($o->status, ['preparing','ready'])) {
            session()->flash('error', "Order isn't ready yet.");
            return;
        }
        $o->update(['status' => 'served', 'served_at' => now()]);
    }

    /**
     * Open the settle/payment dialog for an order.
     */
    public function openSettle(int $id): void
    {
        $o = Order::findOrFail($id);
        $this->settleOrderId = $o->id;
        $this->payAmount = (float) $o->total_amount;
        $this->payMode = $o->order_type === 'room_service' && $o->reservation_id ? 'company_credit' : 'cash';
        $this->payReference = '';
        $this->chargeToRoom = ($o->order_type === 'room_service' && $o->reservation_id);
    }
    public function closeSettle(): void { $this->settleOrderId = null; }

    /**
     * Open the printable bill snapshot for an order in 'billed' or 'settled' state.
     * Read-only — does not change order status.
     */
    public function openBill(int $id): void
    {
        $o = Order::findOrFail($id);
        if (in_array($o->status, ['voided'])) {
            session()->flash('error', "This order was voided.");
            return;
        }
        // Auto-bill on the fly if it isn't billed yet — saves a click
        if (! in_array($o->status, ['billed', 'settled'])) {
            $o->update(['status' => 'billed', 'billed_at' => now()]);
        }
        $this->viewBillId = $o->id;
    }

    public function closeBill(): void { $this->viewBillId = null; }

    /**
     * Settle the order: take payment OR charge to room folio.
     * Marks order settled. Posts food/beverage charges to folio for room_service orders.
     */
    public function confirmSettle(FolioService $folioService): void
    {
        $o = Order::findOrFail($this->settleOrderId);
        if ($o->status === 'settled') {
            session()->flash('error', 'Already settled.');
            $this->settleOrderId = null;
            return;
        }

        $ctx = app(TenantContext::class);
        $now = now();
        $today = $now->toDateString();

        DB::transaction(function () use ($o, $ctx, $now, $today, $folioService) {
            // Always advance to billed if not yet
            if (!in_array($o->status, ['billed', 'settled'])) {
                $o->update(['billed_at' => $o->billed_at ?: $now]);
            }

            if ($this->chargeToRoom && $o->reservation_id) {
                // Post charges to the guest's folio
                $folio = Folio::where('reservation_id', $o->reservation_id)
                    ->whereIn('status', ['open', 'closed'])
                    ->orderBy('id')
                    ->first();

                if (!$folio) {
                    // Create a guest folio if none exists
                    $reservation = Reservation::find($o->reservation_id);
                    $folio = Folio::create([
                        'tenant_id' => $ctx->tenantId(),
                        'property_id' => $ctx->propertyId(),
                        'reservation_id' => $o->reservation_id,
                        'folio_number' => 'F-' . $now->format('ymd') . '-' . $o->reservation_id,
                        'type' => 'guest',
                        'guest_id' => $reservation?->guest_id,
                        'billing_name' => $reservation?->guest_name ?? $o->guest_name ?? 'Guest',
                        'currency' => 'INR',
                        'status' => 'open',
                    ]);
                }

                // Group F&B by category — split alcohol vs food
                $items = $o->items()->with('menuItem.category')->where('is_voided', false)->get();
                $foodAmt = 0; $bevAmt = 0; $foodTax = 0; $bevTax = 0;
                foreach ($items as $it) {
                    $isLiquor = (bool) optional(optional($it->menuItem)->category)->is_liquor;
                    if ($isLiquor) {
                        $bevAmt += (float) $it->amount;
                        $bevTax += (float) $it->tax_amount;
                    } else {
                        $foodAmt += (float) $it->amount;
                        $foodTax += (float) $it->tax_amount;
                    }
                }
                $sc = (float) $o->service_charge;
                // Apportion service charge proportionally
                $totalSub = max(0.01, $foodAmt + $bevAmt);
                $foodSc = round($sc * ($foodAmt / $totalSub), 2);
                $bevSc = round($sc - $foodSc, 2);

                if ($foodAmt > 0) {
                    $foodTotal = $foodAmt + $foodTax + $foodSc;
                    \App\Models\FolioCharge::create([
                        'tenant_id' => $ctx->tenantId(),
                        'property_id' => $ctx->propertyId(),
                        'folio_id' => $folio->id,
                        'charge_date' => $today,
                        'charge_time' => $now->toTimeString(),
                        'business_date' => $today,
                        'category' => 'food',
                        'description' => "F&B — {$o->order_number} (room service)",
                        'reference' => $o->order_number,
                        'quantity' => 1,
                        'rate' => $foodAmt,
                        'amount' => $foodAmt,
                        'discount_amount' => 0,
                        'tax_amount' => $foodTax + $foodSc,
                        'net_amount' => $foodTotal,
                        'tax_breakdown' => ['cgst' => round($foodTax / 2, 2), 'sgst' => round($foodTax / 2, 2), 'service_charge' => $foodSc, 'total' => $foodTax + $foodSc],
                        'posted_by' => auth()->id(),
                    ]);
                }
                if ($bevAmt > 0) {
                    $bevTotal = $bevAmt + $bevTax + $bevSc;
                    \App\Models\FolioCharge::create([
                        'tenant_id' => $ctx->tenantId(),
                        'property_id' => $ctx->propertyId(),
                        'folio_id' => $folio->id,
                        'charge_date' => $today,
                        'charge_time' => $now->toTimeString(),
                        'business_date' => $today,
                        'category' => 'beverage',
                        'description' => "Beverages — {$o->order_number} (room service)",
                        'reference' => $o->order_number,
                        'quantity' => 1,
                        'rate' => $bevAmt,
                        'amount' => $bevAmt,
                        'discount_amount' => 0,
                        'tax_amount' => $bevTax + $bevSc,
                        'net_amount' => $bevTotal,
                        'tax_breakdown' => ['cgst' => round($bevTax / 2, 2), 'sgst' => round($bevTax / 2, 2), 'service_charge' => $bevSc, 'total' => $bevTax + $bevSc],
                        'posted_by' => auth()->id(),
                    ]);
                }

                $folio->recomputeTotals();

                // Link order → folio and mark settled (room charge means payment is via room folio)
                $o->update([
                    'folio_id' => $folio->id,
                    'status' => 'settled',
                    'settled_at' => $now,
                    'billed_at' => $o->billed_at ?: $now,
                    'payment_mode' => 'room_charge',
                    'payment_timing' => 'room_charge',
                    'paid_at' => $now,
                ]);

                session()->flash('success', "Order {$o->order_number} charged to room folio (₹" . number_format((float) $o->total_amount, 2) . ").");
            } else {
                // Direct payment (cash / card / upi etc.) — record payment, settle order
                Payment::create([
                    'tenant_id' => $ctx->tenantId(),
                    'property_id' => $ctx->propertyId(),
                    'folio_id' => null, // POS-direct (no room)
                    'reservation_id' => $o->reservation_id,
                    'receipt_number' => 'RCPT-' . $now->format('ymd') . '-' . str_pad((string) (Payment::count() + 1), 4, '0', STR_PAD_LEFT),
                    'payment_date' => $today,
                    'business_date' => $today,
                    'mode' => $this->payMode,
                    'amount' => $this->payAmount,
                    'currency' => 'INR',
                    'transaction_reference' => $this->payReference ?: null,
                    'status' => 'completed',
                    'received_by' => auth()->id(),
                    'notes' => "POS settlement for {$o->order_number}",
                    'payable_type' => Order::class,
                    'payable_id' => $o->id,
                ]);

                $o->update([
                    'status' => 'settled',
                    'settled_at' => $now,
                    'billed_at' => $o->billed_at ?: $now,
                    'payment_mode' => $this->payMode,
                    'payment_reference' => $this->payReference ?: null,
                    'paid_at' => $now,
                    // Keep timing as 'on_bill' if it was, else preserve prepaid/room
                    'payment_timing' => $o->payment_timing ?? 'on_bill',
                ]);

                session()->flash('success', "Order {$o->order_number} settled — " . strtoupper($this->payMode) . " ₹" . number_format((float) $this->payAmount, 2) . ".");
            }
        });

        $this->settleOrderId = null;
        $this->payAmount = 0;
        $this->payReference = '';
    }

    public function voidOrder(int $id): void
    {
        $o = Order::findOrFail($id);
        if ($o->status === 'settled') {
            session()->flash('error', 'Cannot void a settled order.');
            return;
        }
        $o->update(['status' => 'voided']);
        // Cancel its KDS ticket too
        KdsTicket::where('order_id', $o->id)->update(['status' => 'voided']);
        session()->flash('success', "Order {$o->order_number} voided.");
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $outlets = Outlet::where('property_id', $propertyId)->where('is_active', true)->orderBy('name')->get();

        $menuCategories = $this->selectedOutletId
            ? MenuCategory::where('outlet_id', $this->selectedOutletId)
                ->when($this->selectedCategoryId, fn($q, $id) => $q->where('id', $id))
                ->with(['items' => function ($q) {
                    $q->where('available', true)->where('is_active', true);
                    if ($this->foodTypeFilter !== 'all') {
                        $q->where('food_type', $this->foodTypeFilter);
                    }
                    if ($s = trim($this->menuSearch)) {
                        $q->where('name', 'like', "%{$s}%");
                    }
                    $q->orderBy('name');
                }])
                ->orderBy('display_order')->get()
            : collect();

        // Category list (always full — used for filter chips)
        $allCategories = $this->selectedOutletId
            ? MenuCategory::where('outlet_id', $this->selectedOutletId)
                ->withCount(['items' => fn($q) => $q->where('available', true)->where('is_active', true)])
                ->orderBy('display_order')
                ->get()
            : collect();

        $tables = $this->selectedOutletId
            ? PosTable::where('outlet_id', $this->selectedOutletId)->orderBy('name')->get()
            : collect();

        $openOrders = Order::where('property_id', $propertyId)
            ->whereIn('status', ['open', 'sent_to_kitchen', 'preparing', 'ready', 'served', 'billed'])
            ->when($this->selectedOutletId, fn ($q, $o) => $q->where('outlet_id', $o))
            ->orderByDesc('opened_at')
            ->limit(30)
            ->get();

        $recentSettled = Order::where('property_id', $propertyId)
            ->whereIn('status', ['settled', 'voided'])
            ->whereDate('opened_at', today())
            ->when($this->selectedOutletId, fn ($q, $o) => $q->where('outlet_id', $o))
            ->orderByDesc('settled_at')
            ->limit(10)
            ->get();

        $todayStats = Order::where('property_id', $propertyId)
            ->whereDate('opened_at', today())
            ->selectRaw('count(*) as orders, coalesce(sum(total_amount),0) as revenue, coalesce(sum(case when status="settled" then total_amount else 0 end),0) as settled_revenue')
            ->first();

        $itemsLookup = MenuItem::whereIn('id', collect($this->orderLines)->pluck('menu_item_id'))->get()->keyBy('id');
        $linesView = collect($this->orderLines)->map(function ($l) use ($itemsLookup) {
            $mi = $itemsLookup->get($l['menu_item_id']);
            return $mi ? ['mi' => $mi, 'qty' => $l['qty'], 'subtotal' => $mi->price * $l['qty']] : null;
        })->filter()->values();
        $linesSubtotal = $linesView->sum('subtotal');

        $checkedInReservations = Reservation::where('property_id', $propertyId)->where('status', 'checked_in')->orderBy('guest_name')->get();

        $settleOrder = $this->settleOrderId ? Order::with('reservation')->find($this->settleOrderId) : null;

        $billOrder = null; $billPayment = null; $billProperty = null;
        if ($this->viewBillId) {
            $billOrder = Order::with(['reservation', 'server', 'table', 'outlet', 'activeItems'])
                ->find($this->viewBillId);
            if ($billOrder) {
                $billProperty = Property::find($billOrder->property_id);
                $billPayment = Payment::where('notes', 'POS settlement for ' . $billOrder->order_number)
                    ->orderByDesc('id')->first();
            }
        }

        return view('livewire.pos.pos-dashboard', compact(
            'outlets', 'menuCategories', 'allCategories', 'tables', 'openOrders', 'recentSettled', 'todayStats',
            'linesView', 'linesSubtotal', 'checkedInReservations', 'settleOrder',
            'billOrder', 'billPayment', 'billProperty'
        ));
    }
}
