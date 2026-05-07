<?php

namespace App\Livewire\Reports;

use App\Models\FolioCharge;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class DailyFlash extends Component
{
    public string $businessDate;

    public function mount(): void
    {
        $this->businessDate = today()->toDateString();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $bizDate = Carbon::parse($this->businessDate);

        // Rooms snapshot
        $rooms = Room::where('property_id', $propertyId)->get();
        $totalRooms = $rooms->count();
        $occupied = $rooms->whereIn('status', ['occupied_clean','occupied_dirty'])->count();
        $vacant = $rooms->whereIn('status', ['vacant_clean','vacant_dirty','inspected'])->count();
        $oos = $rooms->whereIn('status', ['out_of_order','out_of_service','blocked'])->count();

        // Reservations stats for the business date
        $arrivals = Reservation::where('property_id', $propertyId)
            ->whereDate('arrival_date', $bizDate)
            ->get();
        $departures = Reservation::where('property_id', $propertyId)
            ->whereDate('departure_date', $bizDate)
            ->get();
        $inHouse = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $bizDate->toDateString())
            ->where('departure_date', '>', $bizDate->toDateString())
            ->whereIn('status', ['checked_in'])
            ->get();

        // Source breakdown
        $bySource = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $bizDate->toDateString())
            ->where('departure_date', '>=', $bizDate->toDateString())
            ->whereIn('status', ['confirmed','checked_in','checked_out'])
            ->selectRaw("coalesce(source_name, source_type, 'Direct') as source, count(*) as cnt, coalesce(sum(total_amount),0) as revenue")
            ->groupBy('source')
            ->get();

        // Revenue by category for the business date
        $revByCategory = FolioCharge::where('property_id', $propertyId)
            ->whereDate('business_date', $bizDate)
            ->where('is_voided', false)
            ->selectRaw('category, sum(amount) as amount, sum(tax_amount) as tax, sum(net_amount) as net')
            ->groupBy('category')
            ->get();

        $totalRevenue = $revByCategory->sum('amount');
        $totalTax = $revByCategory->sum('tax');
        $totalNet = $revByCategory->sum('net');

        // Payments by mode for the business date
        $paymentsByMode = Payment::where('property_id', $propertyId)
            ->whereDate('business_date', $bizDate)
            ->where('status', 'completed')
            ->selectRaw('mode, count(*) as cnt, sum(amount) as amount')
            ->groupBy('mode')
            ->get();
        $totalPayments = $paymentsByMode->sum('amount');

        // Top spenders today (from folios with charges today)
        $topSpenders = Reservation::where('property_id', $propertyId)
            ->whereHas('folios.charges', fn ($q) => $q->whereDate('business_date', $bizDate)->where('is_voided', false))
            ->withSum(['folios as today_charges' => fn ($q) => $q->whereHas('charges', fn ($qc) => $qc->whereDate('business_date', $bizDate)->where('is_voided', false))], 'total_charges')
            ->orderByDesc('today_charges')
            ->limit(8)
            ->get();

        // Pickup (new bookings created today, regardless of arrival date)
        $pickupToday = Reservation::where('property_id', $propertyId)
            ->whereDate('created_at', $bizDate)
            ->count();
        $pickupRevenueToday = (float) Reservation::where('property_id', $propertyId)
            ->whereDate('created_at', $bizDate)
            ->sum('total_amount');

        // No-shows
        $noShows = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', $bizDate->toDateString())
            ->where('status', 'no_show')->count();

        // Occupancy KPIs
        $occPct = $totalRooms > 0 ? round($occupied / $totalRooms * 100, 1) : 0;
        $arr = $occupied > 0 ? round($totalRevenue / $occupied, 0) : 0;
        $revpar = $totalRooms > 0 ? round($totalRevenue / $totalRooms, 0) : 0;

        return view('livewire.reports.daily-flash', [
            'date' => $bizDate,
            'rooms' => compact('totalRooms','occupied','vacant','oos'),
            'arrivals' => $arrivals,
            'departures' => $departures,
            'inHouse' => $inHouse,
            'bySource' => $bySource,
            'revByCategory' => $revByCategory,
            'paymentsByMode' => $paymentsByMode,
            'topSpenders' => $topSpenders,
            'kpis' => compact('occPct','arr','revpar','totalRevenue','totalTax','totalNet','totalPayments','pickupToday','pickupRevenueToday','noShows'),
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $bizDate = Carbon::parse($this->businessDate);

        $rooms = Room::where('property_id', $propertyId)->get();
        $totalRooms = $rooms->count();
        $occupied = $rooms->whereIn('status', ['occupied_clean','occupied_dirty'])->count();
        $vacant = $rooms->whereIn('status', ['vacant_clean','vacant_dirty','inspected'])->count();
        $oos = $rooms->whereIn('status', ['out_of_order','out_of_service','blocked'])->count();

        $arrivals = Reservation::where('property_id', $propertyId)->whereDate('arrival_date', $bizDate)->get();
        $departures = Reservation::where('property_id', $propertyId)->whereDate('departure_date', $bizDate)->get();

        $revByCategory = FolioCharge::where('property_id', $propertyId)
            ->whereDate('business_date', $bizDate)->where('is_voided', false)
            ->selectRaw('category, sum(amount) as amount, sum(tax_amount) as tax, sum(net_amount) as net')
            ->groupBy('category')->get();

        $paymentsByMode = Payment::where('property_id', $propertyId)
            ->whereDate('business_date', $bizDate)->where('status', 'completed')
            ->selectRaw('mode, count(*) as cnt, sum(amount) as amount')
            ->groupBy('mode')->get();

        $occPct = $totalRooms > 0 ? round($occupied / $totalRooms * 100, 1) : 0;

        $filename = "daily_flash_{$this->businessDate}.csv";
        return response()->streamDownload(function () use ($bizDate, $totalRooms, $occupied, $vacant, $oos, $occPct, $arrivals, $departures, $revByCategory, $paymentsByMode) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Daily Flash Report', $bizDate->toDateString()]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Metric', 'Value']);
            fputcsv($out, ['Rooms', 'Total', $totalRooms]);
            fputcsv($out, ['Rooms', 'Occupied', $occupied]);
            fputcsv($out, ['Rooms', 'Vacant', $vacant]);
            fputcsv($out, ['Rooms', 'Out of order', $oos]);
            fputcsv($out, ['Rooms', 'Occupancy %', $occPct]);
            fputcsv($out, ['Movement', 'Arrivals expected', $arrivals->whereIn('status', ['confirmed','checked_in'])->count()]);
            fputcsv($out, ['Movement', 'Arrivals checked-in', $arrivals->where('status','checked_in')->count()]);
            fputcsv($out, ['Movement', 'Departures expected', $departures->whereIn('status', ['checked_in','checked_out'])->count()]);
            fputcsv($out, ['Movement', 'Departures checked-out', $departures->where('status','checked_out')->count()]);
            fputcsv($out, []);
            fputcsv($out, ['Revenue by category']);
            fputcsv($out, ['Category', 'Amount', 'Tax', 'Net']);
            foreach ($revByCategory as $r) fputcsv($out, [$r->category, $r->amount, $r->tax, $r->net]);
            fputcsv($out, []);
            fputcsv($out, ['Payments by mode']);
            fputcsv($out, ['Mode', 'Count', 'Amount']);
            foreach ($paymentsByMode as $p) fputcsv($out, [$p->mode, $p->cnt, $p->amount]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
