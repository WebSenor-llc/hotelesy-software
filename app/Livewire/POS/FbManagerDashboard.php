<?php

namespace App\Livewire\POS;

use App\Models\POS\Order;
use App\Models\POS\Outlet;
use App\Models\Payment;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * F&B Manager Dashboard — kitchen-side financial view of POS activity.
 *
 * Shows:
 *   - Revenue snapshot for preset windows (today / yesterday / last 7 / this month)
 *   - Custom date-range filter
 *   - Top selling menu items
 *   - Outstanding room-service charges by room (not yet settled to folio)
 *   - Cash collected
 *   - GST collected (CGST + SGST split)
 *   - Payment mode breakdown
 *   - Order count and average ticket size
 *
 * Optional outlet filter so multi-outlet properties can drill down.
 *
 * All revenue numbers count only `settled` orders by default — voided and
 * still-open orders are explicitly excluded so the dashboard reflects
 * actual collected revenue, not provisional bills.
 */
#[Layout('layouts.app-shell')]
class FbManagerDashboard extends Component
{
    public string $rangePreset = 'today';   // today | yesterday | last_7 | month | range
    public string $rangeFrom = '';
    public string $rangeTo = '';
    public ?int $outletId = null;            // null = all outlets

    public function mount(): void
    {
        $this->rangeFrom = today()->toDateString();
        $this->rangeTo = today()->toDateString();
    }

    public function setPreset(string $preset): void
    {
        $this->rangePreset = $preset;
        $today = today();
        switch ($preset) {
            case 'yesterday':
                $this->rangeFrom = $today->copy()->subDay()->toDateString();
                $this->rangeTo = $today->copy()->subDay()->toDateString();
                break;
            case 'last_7':
                $this->rangeFrom = $today->copy()->subDays(6)->toDateString();
                $this->rangeTo = $today->toDateString();
                break;
            case 'month':
                $this->rangeFrom = $today->copy()->startOfMonth()->toDateString();
                $this->rangeTo = $today->toDateString();
                break;
            case 'range':
                // keep existing dates
                break;
            case 'today':
            default:
                $this->rangeFrom = $today->toDateString();
                $this->rangeTo = $today->toDateString();
                break;
        }
    }

    /**
     * Apply common scope: tenant property + date range + optional outlet.
     * Centralised so every metric query stays consistent.
     */
    private function scopedOrders(int $propertyId): \Illuminate\Database\Eloquent\Builder
    {
        $from = Carbon::parse($this->rangeFrom)->startOfDay();
        $to = Carbon::parse($this->rangeTo ?: $this->rangeFrom)->endOfDay();

        return Order::where('property_id', $propertyId)
            ->whereBetween('opened_at', [$from, $to])
            ->when($this->outletId, fn ($q, $id) => $q->where('outlet_id', $id));
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        // --- Revenue (only settled orders count toward earned revenue) ---
        $settledQuery = $this->scopedOrders($propertyId)->where('status', Order::STATUS_SETTLED);

        $revenue       = (float) (clone $settledQuery)->sum('total_amount');
        $subtotal      = (float) (clone $settledQuery)->sum('subtotal');
        $taxCollected  = (float) (clone $settledQuery)->sum('tax_amount');
        $serviceCharge = (float) (clone $settledQuery)->sum('service_charge');
        $orderCount    = (int)   (clone $settledQuery)->count();
        $avgTicket     = $orderCount > 0 ? round($revenue / $orderCount, 2) : 0;

        // --- Comparison cards: today, yesterday, last 7, this month ---
        $today = today();
        $compareCards = [
            'Today'     => $this->revenueFor($propertyId, $today, $today),
            'Yesterday' => $this->revenueFor($propertyId, $today->copy()->subDay(), $today->copy()->subDay()),
            'Last 7 days' => $this->revenueFor($propertyId, $today->copy()->subDays(6), $today),
            'This month'  => $this->revenueFor($propertyId, $today->copy()->startOfMonth(), $today),
        ];

        // --- Top selling items (by revenue, with quantity for context) ---
        // Pull from pos_order_items joined to pos_orders for the same scope as $settledQuery.
        $from = Carbon::parse($this->rangeFrom)->startOfDay();
        $to = Carbon::parse($this->rangeTo ?: $this->rangeFrom)->endOfDay();
        $topItems = DB::table('pos_order_items as oi')
            ->join('pos_orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.property_id', $propertyId)
            ->whereBetween('o.opened_at', [$from, $to])
            ->where('o.status', Order::STATUS_SETTLED)
            ->when($this->outletId, fn ($q) => $q->where('o.outlet_id', $this->outletId))
            ->groupBy('oi.item_name')
            ->select(
                'oi.item_name',
                DB::raw('SUM(oi.quantity) as total_qty'),
                DB::raw('SUM(oi.amount) as total_amount'),
                DB::raw('COUNT(DISTINCT o.id) as order_count')
            )
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        // --- Outstanding room-service charges by room ---
        // These are room_service orders that were charged to the folio but the
        // folio hasn't been settled yet. Money is "due" from those rooms.
        // Includes orders charged to the room (folio_id set) where the linked
        // folio is still 'open' — i.e. the guest is still in-house and hasn't paid.
        $dueByRoom = DB::table('pos_orders as o')
            ->leftJoin('folios as f', 'f.id', '=', 'o.folio_id')
            ->where('o.property_id', $propertyId)
            ->where('o.order_type', 'room_service')
            ->where('o.status', '!=', Order::STATUS_VOIDED)
            ->whereNotNull('o.room_number')
            ->where(function ($q) {
                // Either: not yet settled to folio at all, OR settled but folio still open (balance due)
                $q->whereNull('o.folio_id')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('o.folio_id')->where('f.status', 'open');
                  });
            })
            ->groupBy('o.room_number')
            ->select(
                'o.room_number',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(o.total_amount) as total_due')
            )
            ->orderByDesc('total_due')
            ->limit(20)
            ->get();

        // --- Cash collected (in date range) ---
        // Sum from Payment table where mode='cash' AND payable_type=Order::class.
        // This catches both prepaid and on-bill cash settlements.
        $cashCollected = (float) Payment::where('property_id', $propertyId)
            ->where('payable_type', Order::class)
            ->where('mode', 'cash')
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        // --- Payment mode breakdown ---
        $paymentByMode = Payment::where('property_id', $propertyId)
            ->where('payable_type', Order::class)
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('mode, COUNT(*) as txns, SUM(amount) as total')
            ->groupBy('mode')
            ->orderByDesc('total')
            ->get();

        // --- Order type breakdown ---
        $orderTypeBreakdown = (clone $settledQuery)
            ->selectRaw('order_type, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy('order_type')
            ->orderByDesc('revenue')
            ->get();

        // --- Outlet list for filter dropdown ---
        $outlets = Outlet::where('property_id', $propertyId)->where('is_active', true)->orderBy('name')->get();

        // GST split — Indian context: total tax is split equally CGST + SGST
        // for intra-state supply. Most of Hotelesy's data is Rajasthan→Rajasthan.
        $cgst = round($taxCollected / 2, 2);
        $sgst = round($taxCollected / 2, 2);

        return view('livewire.pos.fb-manager-dashboard', [
            'revenue'            => $revenue,
            'subtotal'           => $subtotal,
            'taxCollected'       => $taxCollected,
            'cgst'               => $cgst,
            'sgst'               => $sgst,
            'serviceCharge'      => $serviceCharge,
            'orderCount'         => $orderCount,
            'avgTicket'          => $avgTicket,
            'cashCollected'      => $cashCollected,
            'compareCards'       => $compareCards,
            'topItems'           => $topItems,
            'dueByRoom'          => $dueByRoom,
            'paymentByMode'      => $paymentByMode,
            'orderTypeBreakdown' => $orderTypeBreakdown,
            'outlets'            => $outlets,
        ]);
    }

    /**
     * Helper for the comparison cards. Returns [revenue, orders] for a window.
     */
    private function revenueFor(int $propertyId, Carbon $from, Carbon $to): array
    {
        $q = Order::where('property_id', $propertyId)
            ->whereBetween('opened_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', Order::STATUS_SETTLED)
            ->when($this->outletId, fn ($q, $id) => $q->where('outlet_id', $id));

        return [
            'revenue' => (float) (clone $q)->sum('total_amount'),
            'orders'  => (int) (clone $q)->count(),
        ];
    }
}
