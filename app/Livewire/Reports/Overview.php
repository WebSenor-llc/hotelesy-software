<?php

namespace App\Livewire\Reports;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class Overview extends Component
{
    public string $rangeStart;
    public string $rangeEnd;

    public function mount(): void
    {
        $this->rangeEnd   = today()->toDateString();
        $this->rangeStart = today()->copy()->subDays(29)->toDateString();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $start = Carbon::parse($this->rangeStart);
        $end   = Carbon::parse($this->rangeEnd);

        $totalRooms = Room::where('property_id', $propertyId)->count();

        // Reservations overlapping the range
        $rs = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $end->toDateString())
            ->where('departure_date', '>=', $start->toDateString())
            ->whereIn('status', ['confirmed','checked_in','checked_out'])
            ->get();

        // Daily occupancy/revenue series
        $days = collect();
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $occ = 0; $rev = 0;
            foreach ($rs as $r) {
                $a = Carbon::parse($r->arrival_date); $d = Carbon::parse($r->departure_date);
                if ($cur->gte($a) && $cur->lt($d)) {
                    $occ += $r->rooms_count;
                    $rev += $r->nights > 0 ? ($r->room_revenue / $r->nights) * $r->rooms_count : 0;
                }
            }
            $days->push([
                'date' => $cur->toDateString(),
                'occ' => $occ,
                'occ_pct' => $totalRooms > 0 ? round($occ / $totalRooms * 100, 1) : 0,
                'rev' => round($rev, 2),
            ]);
            $cur->addDay();
        }

        $totalRevenue = $days->sum('rev');
        $totalRoomNights = $days->sum('occ');
        $totalAvailableNights = $totalRooms * $days->count();
        $avgOcc = $totalAvailableNights > 0 ? round($totalRoomNights / $totalAvailableNights * 100, 1) : 0;
        $arr = $totalRoomNights > 0 ? round($totalRevenue / $totalRoomNights, 0) : 0;
        $revpar = $totalAvailableNights > 0 ? round($totalRevenue / $totalAvailableNights, 0) : 0;

        $totalPayments = Payment::where('property_id', $propertyId)
            ->where('status','completed')
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $byStatus = Reservation::where('property_id', $propertyId)
            ->whereBetween('created_at', [$start, $end->copy()->endOfDay()])
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')->pluck('cnt','status');

        return view('livewire.reports.overview', [
            'days' => $days,
            'kpis' => compact('totalRevenue','totalRoomNights','totalAvailableNights','avgOcc','arr','revpar','totalPayments'),
            'byStatus' => $byStatus,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $start = Carbon::parse($this->rangeStart);
        $end   = Carbon::parse($this->rangeEnd);

        $totalRooms = Room::where('property_id', $propertyId)->count();
        $rs = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $end->toDateString())
            ->where('departure_date', '>=', $start->toDateString())
            ->whereIn('status', ['confirmed','checked_in','checked_out'])
            ->get();

        $rows = [];
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $occ = 0; $rev = 0;
            foreach ($rs as $r) {
                $a = Carbon::parse($r->arrival_date); $d = Carbon::parse($r->departure_date);
                if ($cur->gte($a) && $cur->lt($d)) {
                    $occ += $r->rooms_count;
                    $rev += $r->nights > 0 ? ($r->room_revenue / $r->nights) * $r->rooms_count : 0;
                }
            }
            $rows[] = [
                $cur->toDateString(),
                $occ,
                $totalRooms > 0 ? round($occ / $totalRooms * 100, 1) : 0,
                round($rev, 2),
            ];
            $cur->addDay();
        }

        $filename = "overview_{$this->rangeStart}_to_{$this->rangeEnd}.csv";
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Occupied rooms', 'Occupancy %', 'Room revenue']);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
