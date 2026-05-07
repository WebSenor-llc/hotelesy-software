<?php

namespace App\Livewire\Reports;

use App\Models\Amenities\AmenityOrder;
use App\Models\Banquet\BanquetBooking;
use App\Models\FolioCharge;
use App\Models\POS\Order as PosOrder;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class RevenueReport extends Component
{
    public string $startDate;
    public string $endDate;
    public string $preset = 'month';

    public function mount(): void
    {
        $this->applyPreset('month');
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $today = today();
        switch ($preset) {
            case 'today':
                $this->startDate = $today->toDateString();
                $this->endDate   = $today->toDateString();
                break;
            case 'yesterday':
                $this->startDate = $today->copy()->subDay()->toDateString();
                $this->endDate   = $today->copy()->subDay()->toDateString();
                break;
            case 'last_month':
                $this->startDate = $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
                $this->endDate   = $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
                break;
            case 'custom':
                if (empty($this->startDate)) {
                    $this->startDate = $today->copy()->startOfMonth()->toDateString();
                }
                if (empty($this->endDate)) {
                    $this->endDate = $today->toDateString();
                }
                break;
            case 'month':
            default:
                $this->startDate = $today->copy()->startOfMonth()->toDateString();
                $this->endDate   = $today->toDateString();
                $this->preset    = 'month';
                break;
        }
    }

    public function updatedStartDate(): void { $this->preset = 'custom'; }
    public function updatedEndDate(): void   { $this->preset = 'custom'; }

    protected function buildReport(): array
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $property   = $ctx->property();

        $start = Carbon::parse($this->startDate);
        $end   = Carbon::parse($this->endDate);

        // Revenue by category from folio_charges (tax-exclusive amounts)
        $catRows = FolioCharge::where('property_id', $propertyId)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->where('is_voided', false)
            ->selectRaw('category, sum(amount) as amount, sum(tax_amount) as tax')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $roomRev    = (float) ($catRows['room']->amount ?? 0)
                    + (float) ($catRows['extra_bed']->amount ?? 0)
                    + (float) ($catRows['package']->amount ?? 0);
        $fbRev      = (float) ($catRows['food']->amount ?? 0)
                    + (float) ($catRows['beverage']->amount ?? 0)
                    + (float) ($catRows['mini_bar']->amount ?? 0);
        $otherCharges = ['laundry','spa','telephone','misc','damage','service_charge'];
        $otherRev   = 0;
        foreach ($otherCharges as $cat) {
            $otherRev += (float) ($catRows[$cat]->amount ?? 0);
        }
        $totalTax   = (float) $catRows->sum('tax');

        // Banquet revenue (event_date within range)
        $banquetRev = (float) BanquetBooking::where('property_id', $propertyId)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['confirmed','completed'])
            ->sum('subtotal');
        $banquetTax = (float) BanquetBooking::where('property_id', $propertyId)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['confirmed','completed'])
            ->sum('tax_amount');

        // Amenity / ancillary orders
        $amenityRev = (float) AmenityOrder::where('property_id', $propertyId)
            ->whereBetween('service_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['confirmed','fulfilled'])
            ->selectRaw('coalesce(sum(total_amount),0) - coalesce(sum(tax_amount),0) as net')
            ->value('net') ?? 0;
        $amenityTax = (float) AmenityOrder::where('property_id', $propertyId)
            ->whereBetween('service_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['confirmed','fulfilled'])
            ->sum('tax_amount');

        // POS orders that have NOT been room-charged (those flow into folio_charges already)
        $posRev = (float) PosOrder::where('property_id', $propertyId)
            ->whereBetween('opened_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereIn('status', ['billed','settled','served'])
            ->whereNull('folio_id')      // exclude room-charged orders
            ->sum('subtotal');
        $posTax = (float) PosOrder::where('property_id', $propertyId)
            ->whereBetween('opened_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereIn('status', ['billed','settled','served'])
            ->whereNull('folio_id')
            ->sum('tax_amount');

        // Total ancillary (other + amenity + POS standalone)
        $ancillaryRev = $otherRev + $amenityRev + $posRev;
        $ancillaryTax = (float) ($catRows->whereIn('category', $otherCharges)->sum('tax')) + $amenityTax + $posTax;

        $taxesAll  = $totalTax + $banquetTax + $amenityTax + $posTax;
        $totalRev  = $roomRev + $fbRev + $banquetRev + $ancillaryRev;
        $grandTotal = $totalRev + $taxesAll;

        // Revenue by source (reservations whose departure_date <= end and arrival_date >= start)
        $bySourceRows = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $end->toDateString())
            ->where('departure_date', '>=', $start->toDateString())
            ->whereIn('status', ['confirmed','checked_in','checked_out'])
            ->selectRaw("source_type, count(*) as bookings, coalesce(sum(rooms_count),0) as rooms,
                         coalesce(sum(room_revenue),0) as room_revenue,
                         coalesce(sum(total_amount),0) as total_amount")
            ->groupBy('source_type')
            ->get();

        // Revenue by room type — uses reservation_rooms join via DB
        $byRoomTypeRows = DB::table('reservation_rooms as rr')
            ->join('room_types as rt', 'rt.id', '=', 'rr.room_type_id')
            ->where('rr.property_id', $propertyId)
            ->where('rr.arrival_date', '<=', $end->toDateString())
            ->where('rr.departure_date', '>=', $start->toDateString())
            ->whereIn('rr.status', ['booked','allocated','checked_in','checked_out'])
            ->selectRaw('rt.name as room_type,
                         count(rr.id) as rooms_booked,
                         sum(rr.nights) as room_nights,
                         sum(rr.total_rate) as room_revenue,
                         sum(rr.total_amount) as total_amount')
            ->groupBy('rt.id', 'rt.name')
            ->orderByDesc('total_amount')
            ->get();

        // Occupancy / ADR / RevPAR for the range
        $totalRooms  = Room::where('property_id', $propertyId)->count();
        $rangeNights = max($start->diffInDays($end) + 1, 1);

        $roomNightsSold = (int) DB::table('reservation_room_nights')
            ->where('property_id', $propertyId)
            ->whereBetween('night_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $roomRevenueForKpi = (float) DB::table('reservation_room_nights')
            ->where('property_id', $propertyId)
            ->whereBetween('night_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('coalesce(sum(rate + extra_adult_charge + extra_child_charge + extra_bed_charge - discount_amount),0) as r')
            ->value('r');

        // Fall back to folio_charges if room nights not posted
        if ($roomNightsSold === 0 && $roomRev > 0) {
            $roomRevenueForKpi = $roomRev;
        }

        $availableNights = $totalRooms * $rangeNights;
        $occupancyPct    = $availableNights > 0 ? round($roomNightsSold / $availableNights * 100, 2) : 0;
        $adr             = $roomNightsSold > 0 ? round($roomRevenueForKpi / $roomNightsSold, 2) : 0;
        $revpar          = $availableNights > 0 ? round($roomRevenueForKpi / $availableNights, 2) : 0;

        // Payments collected in window
        $totalPayments = (float) Payment::where('property_id', $propertyId)
            ->where('status','completed')
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $kpis = [
            'roomRev'         => $roomRev,
            'fbRev'           => $fbRev,
            'banquetRev'      => $banquetRev,
            'ancillaryRev'    => $ancillaryRev,
            'totalTaxes'      => $taxesAll,
            'totalRev'        => $totalRev,
            'grandTotal'      => $grandTotal,
            'totalPayments'   => $totalPayments,
            'totalRooms'      => $totalRooms,
            'roomNightsSold'  => $roomNightsSold,
            'availableNights' => $availableNights,
            'occupancyPct'    => $occupancyPct,
            'adr'             => $adr,
            'revpar'          => $revpar,
        ];

        $breakdown = [
            'room'      => $roomRev,
            'fb'        => $fbRev,
            'banquet'   => $banquetRev,
            'ancillary' => $ancillaryRev,
            'amenity'   => $amenityRev,
            'pos'       => $posRev,
            'other'     => $otherRev,
            'taxes'     => $taxesAll,
        ];

        return compact('kpis','breakdown','bySourceRows','byRoomTypeRows','start','end','property');
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->buildReport();
        $filename = sprintf('revenue-report-%s_to_%s.csv', $this->startDate, $this->endDate);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Revenue Report']);
            fputcsv($out, ['Property', $data['property']?->name ?? '']);
            fputcsv($out, ['Period',   $data['start']->format('d M Y').' to '.$data['end']->format('d M Y')]);
            fputcsv($out, []);

            // Revenue breakdown
            fputcsv($out, ['Revenue breakdown']);
            fputcsv($out, ['Stream', 'Amount']);
            fputcsv($out, ['Room',         number_format($data['breakdown']['room'], 2, '.', '')]);
            fputcsv($out, ['F&B',          number_format($data['breakdown']['fb'], 2, '.', '')]);
            fputcsv($out, ['Banquet',      number_format($data['breakdown']['banquet'], 2, '.', '')]);
            fputcsv($out, ['Other charges',number_format($data['breakdown']['other'], 2, '.', '')]);
            fputcsv($out, ['Amenity',      number_format($data['breakdown']['amenity'], 2, '.', '')]);
            fputcsv($out, ['POS standalone',number_format($data['breakdown']['pos'], 2, '.', '')]);
            fputcsv($out, ['Total ancillary',number_format($data['breakdown']['ancillary'], 2, '.', '')]);
            fputcsv($out, ['Taxes',        number_format($data['breakdown']['taxes'], 2, '.', '')]);
            fputcsv($out, ['Net revenue',  number_format($data['kpis']['totalRev'], 2, '.', '')]);
            fputcsv($out, ['Grand total',  number_format($data['kpis']['grandTotal'], 2, '.', '')]);
            fputcsv($out, []);

            // KPIs
            fputcsv($out, ['Occupancy KPIs']);
            fputcsv($out, ['Total rooms',           $data['kpis']['totalRooms']]);
            fputcsv($out, ['Available room-nights', $data['kpis']['availableNights']]);
            fputcsv($out, ['Sold room-nights',      $data['kpis']['roomNightsSold']]);
            fputcsv($out, ['Occupancy %',           $data['kpis']['occupancyPct']]);
            fputcsv($out, ['ADR',                   $data['kpis']['adr']]);
            fputcsv($out, ['RevPAR',                $data['kpis']['revpar']]);
            fputcsv($out, ['Payments collected',    number_format($data['kpis']['totalPayments'], 2, '.', '')]);
            fputcsv($out, []);

            // Source
            fputcsv($out, ['Revenue by source']);
            fputcsv($out, ['Source', 'Bookings', 'Rooms', 'Room revenue', 'Total amount']);
            foreach ($data['bySourceRows'] as $r) {
                fputcsv($out, [
                    $r->source_type,
                    $r->bookings,
                    $r->rooms,
                    number_format((float) $r->room_revenue, 2, '.', ''),
                    number_format((float) $r->total_amount, 2, '.', ''),
                ]);
            }
            fputcsv($out, []);

            // Room type
            fputcsv($out, ['Revenue by room type']);
            fputcsv($out, ['Room type', 'Rooms booked', 'Room nights', 'Room revenue', 'Total amount']);
            foreach ($data['byRoomTypeRows'] as $r) {
                fputcsv($out, [
                    $r->room_type,
                    $r->rooms_booked,
                    $r->room_nights,
                    number_format((float) $r->room_revenue, 2, '.', ''),
                    number_format((float) $r->total_amount, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.revenue-report', $this->buildReport());
    }
}
