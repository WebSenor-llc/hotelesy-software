<?php

namespace App\Livewire\Reports;

use App\Models\Reservation;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class ReservationStatusReport extends Component
{
    public string $startDate;
    public string $endDate;
    public string $groupBy = 'status'; // status | source | segment

    public function mount(): void
    {
        $this->startDate = today()->copy()->subDays(30)->toDateString();
        $this->endDate = today()->copy()->addDays(30)->toDateString();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        $base = Reservation::where('property_id', $propertyId)
            ->whereBetween('arrival_date', [$start->toDateString(), $end->toDateString()]);

        $byStatus = (clone $base)->selectRaw('status, count(*) as cnt, coalesce(sum(total_amount),0) as revenue, coalesce(sum(paid_amount),0) as paid, coalesce(sum(balance_amount),0) as balance')
            ->groupBy('status')->get();

        $bySource = (clone $base)->selectRaw('coalesce(source_name, source_type, "Unknown") as src, count(*) as cnt, coalesce(sum(total_amount),0) as revenue')
            ->groupBy('src')->orderByDesc('cnt')->get();

        $bySegment = (clone $base)->selectRaw('coalesce(market_segment, "Unspecified") as segment, count(*) as cnt, coalesce(sum(total_amount),0) as revenue')
            ->groupBy('segment')->orderByDesc('cnt')->get();

        $byCompany = (clone $base)->whereNotNull('company_id')
            ->selectRaw('company_id, count(*) as cnt, coalesce(sum(total_amount),0) as revenue')
            ->with('company:id,name,gst_number')
            ->groupBy('company_id')->orderByDesc('revenue')->get();

        // Daily trend
        $daily = (clone $base)->selectRaw('arrival_date, status, count(*) as cnt')
            ->groupBy('arrival_date','status')->orderBy('arrival_date')->get();
        $dailyMap = [];
        foreach ($daily as $row) {
            $dailyMap[$row->arrival_date->toDateString()][$row->status] = $row->cnt;
        }

        $totals = [
            'total' => (clone $base)->count(),
            'revenue' => (float) (clone $base)->sum('total_amount'),
            'paid' => (float) (clone $base)->sum('paid_amount'),
            'balance' => (float) (clone $base)->sum('balance_amount'),
            'cancelled_pct' => 0,
        ];
        $totals['cancelled_pct'] = $totals['total'] > 0
            ? round(($byStatus->where('status','cancelled')->first()?->cnt ?? 0) / $totals['total'] * 100, 1) : 0;

        // No-show rate, conversion etc.
        $confirmed = $byStatus->where('status','confirmed')->first()?->cnt ?? 0;
        $checkedIn = $byStatus->where('status','checked_in')->first()?->cnt ?? 0;
        $checkedOut = $byStatus->where('status','checked_out')->first()?->cnt ?? 0;
        $tentative = $byStatus->where('status','tentative')->first()?->cnt ?? 0;
        $cancelled = $byStatus->where('status','cancelled')->first()?->cnt ?? 0;
        $noShow = $byStatus->where('status','no_show')->first()?->cnt ?? 0;
        $confirmedTotal = $confirmed + $checkedIn + $checkedOut;
        $noShowRate = ($confirmedTotal + $noShow) > 0 ? round($noShow / ($confirmedTotal + $noShow) * 100, 1) : 0;

        return view('livewire.reports.reservation-status', [
            'byStatus' => $byStatus,
            'bySource' => $bySource,
            'bySegment' => $bySegment,
            'byCompany' => $byCompany,
            'dailyMap' => $dailyMap,
            'totals' => $totals,
            'start' => $start, 'end' => $end,
            'kpis' => compact('confirmed','checkedIn','checkedOut','tentative','cancelled','noShow','noShowRate'),
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        $reservations = Reservation::where('property_id', $propertyId)
            ->whereBetween('arrival_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('arrival_date')
            ->get();

        $filename = "reservation_status_{$this->startDate}_to_{$this->endDate}.csv";
        return response()->streamDownload(function () use ($reservations) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Reservation #','Guest','Arrival','Departure','Nights','Rooms','Status',
                'Source','Market segment','Total','Paid','Balance',
            ]);
            foreach ($reservations as $r) {
                fputcsv($out, [
                    $r->reservation_number,
                    $r->guest_name,
                    $r->arrival_date instanceof \DateTimeInterface ? $r->arrival_date->format('Y-m-d') : (string)$r->arrival_date,
                    $r->departure_date instanceof \DateTimeInterface ? $r->departure_date->format('Y-m-d') : (string)$r->departure_date,
                    $r->nights,
                    $r->rooms_count,
                    $r->status,
                    $r->source_name ?? $r->source_type ?? '',
                    $r->market_segment ?? '',
                    $r->total_amount,
                    $r->paid_amount,
                    $r->balance_amount,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
