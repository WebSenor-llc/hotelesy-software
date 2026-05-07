<?php

namespace App\Livewire\Dashboard;

use App\Models\Banquet\BanquetBooking;
use App\Models\Folio;
use App\Models\Housekeeping\HousekeepingTask;
use App\Models\Payment;
use App\Models\Pos\Order as PosOrder;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Overview extends Component
{
    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $today = Carbon::today();

        // Rooms — status counts
        $rooms = Room::where('property_id', $propertyId)->orderBy('floor')->orderBy('number')->get();
        $totalRooms     = $rooms->count();
        $occupiedRooms  = $rooms->whereIn('status', ['occupied_clean','occupied_dirty'])->count();
        $vacantRooms    = $rooms->whereIn('status', ['vacant_clean','vacant_dirty','inspected'])->count();
        $oooRooms       = $rooms->whereIn('status', ['out_of_order','out_of_service','blocked'])->count();
        $dirtyRooms     = $rooms->whereIn('status', ['vacant_dirty','occupied_dirty'])->count();
        $occupancyPct   = $totalRooms > 0 ? round($occupiedRooms / $totalRooms * 100, 1) : 0;

        // Today reservation snapshot
        $reservations = Reservation::where('property_id', $propertyId)->get();
        $arrivalsToday   = $reservations->where('arrival_date', $today->toDateString())->whereIn('status', ['confirmed','checked_in'])->count();
        $departuresToday = $reservations->where('departure_date', $today->toDateString())->whereIn('status', ['checked_in','checked_out'])->count();
        $inHouse         = $reservations->where('status', 'checked_in')->count();
        $todayRevenue    = $reservations->where('arrival_date', '<=', $today->toDateString())
                                        ->where('departure_date', '>=', $today->toDateString())
                                        ->whereIn('status', ['checked_in','checked_out','confirmed'])
                                        ->sum('total_amount');

        // ADR & RevPAR (today)
        $adr = $occupiedRooms > 0 ? round($todayRevenue / $occupiedRooms, 2) : 0;
        $revPar = $totalRooms > 0 ? round($todayRevenue / $totalRooms, 2) : 0;

        // 7-day occupancy + revenue trend
        $last7 = collect(range(6, 0))->map(function ($daysAgo) use ($propertyId, $totalRooms) {
            $date = Carbon::today()->subDays($daysAgo);
            $occ = Reservation::where('property_id', $propertyId)
                ->whereIn('status', ['checked_in','checked_out','confirmed'])
                ->where('arrival_date', '<=', $date->toDateString())
                ->where('departure_date', '>=', $date->toDateString())
                ->count();
            $rev = (float) Reservation::where('property_id', $propertyId)
                ->whereIn('status', ['checked_in','checked_out','confirmed'])
                ->where('arrival_date', '<=', $date->toDateString())
                ->where('departure_date', '>=', $date->toDateString())
                ->sum('total_amount');
            return [
                'date'  => $date->toDateString(),
                'label' => $date->format('D'),
                'occ'   => $occ,
                'pct'   => $totalRooms > 0 ? round($occ / $totalRooms * 100, 1) : 0,
                'rev'   => $rev,
            ];
        });

        // Pickup pace — bookings made in last 7 days
        $pickupPace = (int) Reservation::where('property_id', $propertyId)
            ->where('created_at', '>=', $today->copy()->subDays(7))
            ->count();
        $bookingsYesterday = (int) Reservation::where('property_id', $propertyId)
            ->whereDate('created_at', $today->copy()->subDay())
            ->count();
        $bookingsTodaySoFar = (int) Reservation::where('property_id', $propertyId)
            ->whereDate('created_at', $today)
            ->count();

        // Today's arrivals queue (full detail)
        $arrivalsList = Reservation::where('property_id', $propertyId)
            ->whereDate('arrival_date', $today)
            ->whereIn('status', ['confirmed','tentative','checked_in'])
            ->orderBy('arrival_time')
            ->limit(8)
            ->get();
        $departuresList = Reservation::where('property_id', $propertyId)
            ->whereDate('departure_date', $today)
            ->whereIn('status', ['checked_in','checked_out'])
            ->orderBy('departure_time')
            ->limit(8)
            ->get();

        // Upcoming arrivals (next 7 days)
        $upcomingArrivals = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '>', $today->toDateString())
            ->where('arrival_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->whereIn('status', ['confirmed','tentative'])
            ->orderBy('arrival_date')
            ->limit(6)
            ->get();

        // Today's banquet events
        $banquetToday = collect();
        if (class_exists(BanquetBooking::class)) {
            $banquetToday = BanquetBooking::where('property_id', $propertyId)
                ->whereDate('event_date', $today)
                ->whereNotIn('status', ['cancelled'])
                ->with(['guest:id,first_name,last_name', 'company:id,name'])
                ->orderBy('event_start_time')
                ->limit(5)
                ->get();
        }

        // Today's payments (channel-wise) — uses `mode` + `payment_date`
        $paymentsToday = Payment::where('property_id', $propertyId)
            ->whereDate('payment_date', $today)
            ->get();
        $paymentByMode = $paymentsToday->groupBy('mode')->map->sum('amount');
        $paymentsTotal = (float) $paymentsToday->sum('amount');

        // Open folios + outstanding
        $openFolios = Folio::where('property_id', $propertyId)
            ->whereNotIn('status', ['settled','voided'])
            ->count();
        $totalOutstanding = (float) Folio::where('property_id', $propertyId)
            ->whereNotIn('status', ['settled','voided'])
            ->sum('balance');

        // Housekeeping snapshot
        $hkPending = 0;
        $hkInProgress = 0;
        if (class_exists(HousekeepingTask::class)) {
            $hkPending    = HousekeepingTask::where('property_id', $propertyId)->where('status', 'pending')->count();
            $hkInProgress = HousekeepingTask::where('property_id', $propertyId)->where('status', 'in_progress')->count();
        }

        // POS today
        $posToday = collect();
        if (class_exists(PosOrder::class)) {
            $posToday = PosOrder::where('property_id', $propertyId)
                ->whereDate('created_at', $today)
                ->get();
        }
        $posOrdersCount = $posToday->count();
        $posRevenue = (float) $posToday->sum('total_amount');
        $posOpen = $posToday->whereIn('status', ['open','sent_to_kitchen','preparing','ready'])->count();

        // Source-mix (donut)
        $sourceMix = Reservation::where('property_id', $propertyId)
            ->whereDate('arrival_date', '>=', $today->copy()->subDays(30))
            ->select('source_type as source', DB::raw('COUNT(*) as count'))
            ->groupBy('source_type')
            ->orderByDesc('count')
            ->limit(6)
            ->get();
        $sourceTotal = max(1, $sourceMix->sum('count'));

        // Recent activity feed (last events)
        $recentReservationsAct = Reservation::where('property_id', $propertyId)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'kind'  => 'reservation',
                'when'  => $r->updated_at,
                'title' => ($r->reservation_number ?: '#'.$r->id) . ' · ' . ($r->guest_name ?: 'Guest'),
                'meta'  => ucfirst(str_replace('_',' ',$r->status)) . ' · ₹' . number_format($r->total_amount, 0),
                'href'  => Route::has('reservations.show') ? route('reservations.show', $r->id) : null,
                'tone'  => match($r->status) {
                    'checked_in' => 'emerald',
                    'checked_out' => 'slate',
                    'cancelled' => 'rose',
                    default => 'brand',
                },
            ]);
        $recentPaymentsAct = $paymentsToday->take(5)->map(fn($p) => [
            'kind'  => 'payment',
            'when'  => $p->payment_date instanceof \Carbon\Carbon ? $p->payment_date : Carbon::parse($p->payment_date),
            'title' => '₹' . number_format($p->amount, 0) . ' received',
            'meta'  => ucfirst($p->mode ?? 'cash') . ' · ' . ($p->transaction_reference ?: 'No ref'),
            'href'  => null,
            'tone'  => 'emerald',
        ]);
        $activity = $recentReservationsAct->concat($recentPaymentsAct)
            ->sortByDesc('when')
            ->take(8)
            ->values();

        // Yesterday revenue for delta
        $yesterdayRevenue = (float) Reservation::where('property_id', $propertyId)
            ->whereIn('status', ['checked_in','checked_out','confirmed'])
            ->where('arrival_date', '<=', $today->copy()->subDay()->toDateString())
            ->where('departure_date', '>=', $today->copy()->subDay()->toDateString())
            ->sum('total_amount');
        $revenueDelta = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : 0;

        return view('livewire.dashboard.overview', [
            'kpis' => [
                'occupancy_pct'    => $occupancyPct,
                'occupied_rooms'   => $occupiedRooms,
                'total_rooms'      => $totalRooms,
                'vacant_rooms'     => $vacantRooms,
                'ooo_rooms'        => $oooRooms,
                'dirty_rooms'      => $dirtyRooms,
                'arrivals_today'   => $arrivalsToday,
                'departures_today' => $departuresToday,
                'in_house'         => $inHouse,
                'today_revenue'    => $todayRevenue,
                'open_folios'      => $openFolios,
                'outstanding'      => $totalOutstanding,
                'adr'              => $adr,
                'revpar'           => $revPar,
                'pos_orders'       => $posOrdersCount,
                'pos_revenue'      => $posRevenue,
                'pos_open'         => $posOpen,
                'hk_pending'       => $hkPending,
                'hk_in_progress'   => $hkInProgress,
                'pickup_7d'        => $pickupPace,
                'bookings_today'   => $bookingsTodaySoFar,
                'bookings_yest'    => $bookingsYesterday,
                'payments_total'   => $paymentsTotal,
                'revenue_delta'    => $revenueDelta,
            ],
            'rooms'              => $rooms,
            'last7'              => $last7,
            'arrivalsList'       => $arrivalsList,
            'departuresList'     => $departuresList,
            'upcomingArrivals'   => $upcomingArrivals,
            'banquetToday'       => $banquetToday,
            'paymentByMode'      => $paymentByMode,
            'sourceMix'          => $sourceMix,
            'sourceTotal'        => $sourceTotal,
            'activity'           => $activity,
        ]);
    }
}
