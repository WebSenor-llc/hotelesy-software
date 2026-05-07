<?php

namespace App\Livewire\Operations;

use App\Models\FolioCharge;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class NightAudit extends Component
{
    public bool $confirmRun = false;
    public string $notes = '';
    public bool $previewMode = false;
    public bool $skipPreview = false;
    public array $previewSummary = [];

    public function previewAudit(): void
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();
        abort_unless($property, 422);

        $bizDate = $property->current_business_date
            ? Carbon::parse($property->current_business_date)
            : today();

        $summary = [
            'business_date'         => $bizDate->format('d M Y'),
            'next_business_date'    => $bizDate->copy()->addDay()->format('d M Y'),
            'reservations_to_charge'=> 0,
            'rooms_to_post'         => 0,
            'no_shows_to_mark'      => 0,
            'total_room_charges'    => 0.0,
            'total_tax'             => 0.0,
            'lines'                 => [],
        ];

        DB::beginTransaction();
        try {
            $noShows = Reservation::where('property_id', $property->id)
                ->whereDate('arrival_date', $bizDate)
                ->whereIn('status', ['confirmed','tentative'])
                ->get();
            $summary['no_shows_to_mark'] = $noShows->count();

            $inHouse = Reservation::where('property_id', $property->id)
                ->where('status', 'checked_in')
                ->where('arrival_date', '<=', $bizDate->toDateString())
                ->where('departure_date', '>', $bizDate->toDateString())
                ->with(['rooms.roomType'])
                ->get();

            foreach ($inHouse as $r) {
                $summary['reservations_to_charge']++;
                foreach ($r->rooms as $rRoom) {
                    if (!$rRoom->roomType) continue;
                    $rate = (float) ($rRoom->average_rate ?: $rRoom->roomType->base_rate);
                    $taxPct = $rate <= 7500 ? 12.0 : 18.0;
                    $tax = round($rate * ($taxPct / 100), 2);
                    $summary['rooms_to_post']++;
                    $summary['total_room_charges'] += $rate;
                    $summary['total_tax'] += $tax;
                    $summary['lines'][] = [
                        'reservation' => $r->reservation_number ?? ('#' . $r->id),
                        'room_type'   => $rRoom->roomType->name,
                        'rate'        => $rate,
                        'tax'         => $tax,
                    ];
                }
            }
        } finally {
            DB::rollBack();
        }

        $this->previewSummary = $summary;
        $this->previewMode = true;
        $this->confirmRun = false;
    }

    public function cancelPreview(): void
    {
        $this->previewMode = false;
        $this->previewSummary = [];
    }

    public function runAudit(): void
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();
        abort_unless($property, 422);

        $startedAt = now();
        $bizDate = $property->current_business_date
            ? Carbon::parse($property->current_business_date)
            : today();

        $result = DB::transaction(function () use ($property, $bizDate) {
            $stats = [
                'reservations_processed' => 0,
                'rooms_posted'           => 0,
                'no_shows_marked'        => 0,
                'folios_closed'          => 0,
                'total_room_revenue'     => 0,
                'total_pos_revenue'      => 0,
                'total_tax'              => 0,
                'total_payments'         => 0,
            ];

            // 1. Mark no-shows: confirmed reservations whose arrival_date <= bizDate and not checked in
            $noShows = Reservation::where('property_id', $property->id)
                ->whereDate('arrival_date', $bizDate)
                ->whereIn('status', ['confirmed','tentative'])
                ->get();
            foreach ($noShows as $r) {
                $r->update([
                    'status' => 'no_show',
                    'status_changed_at' => now(),
                    'status_changed_by' => auth()->id(),
                ]);
                $stats['no_shows_marked']++;
            }

            // 2. Post nightly room charge for in-house guests
            $inHouse = Reservation::where('property_id', $property->id)
                ->where('status', 'checked_in')
                ->where('arrival_date', '<=', $bizDate->toDateString())
                ->where('departure_date', '>', $bizDate->toDateString())
                ->with(['rooms.roomType', 'folios'])
                ->get();
            foreach ($inHouse as $r) {
                $stats['reservations_processed']++;
                foreach ($r->rooms as $rRoom) {
                    if (!$rRoom->roomType) continue;
                    $rate = (float) ($rRoom->average_rate ?: $rRoom->roomType->base_rate);
                    $taxPct = $rate <= 7500 ? 12.0 : 18.0;
                    $tax = round($rate * ($taxPct / 100), 2);
                    $net = $rate + $tax;

                    $folio = $r->folios->where('status', 'open')->first();
                    if (!$folio) continue;

                    FolioCharge::create([
                        'tenant_id'    => $r->tenant_id,
                        'property_id'  => $r->property_id,
                        'folio_id'     => $folio->id,
                        'charge_date'  => $bizDate,
                        'business_date'=> $bizDate,
                        'category'     => 'room',
                        'description'  => "Room charge — {$rRoom->roomType->name} #".($rRoom->room?->number ?? '-')." [{$bizDate->format('d M')}]",
                        'reference'    => 'NA-'.$bizDate->format('ymd'),
                        'quantity'     => 1,
                        'rate'         => $rate,
                        'amount'       => $rate,
                        'tax_amount'   => $tax,
                        'net_amount'   => $net,
                        'tax_breakdown'=> ['rate' => $taxPct, 'amount' => $tax],
                        'is_voided'    => false,
                        'posted_by'    => auth()->id(),
                    ]);
                    $folio->update([
                        'total_charges' => $folio->total_charges + $rate,
                        'total_taxes'   => $folio->total_taxes + $tax,
                        'balance'       => $folio->balance + $net,
                    ]);
                    $stats['rooms_posted']++;
                    $stats['total_room_revenue'] += $rate;
                    $stats['total_tax'] += $tax;
                }
            }

            // 3. Compute snapshot KPIs
            $totalRooms = Room::where('property_id', $property->id)->where('is_active', true)->count();
            $occupied   = $inHouse->count();
            $occPct     = $totalRooms > 0 ? round($occupied / $totalRooms * 100, 2) : 0;
            $arr        = $occupied > 0 ? round($stats['total_room_revenue'] / $occupied, 2) : 0;
            $revpar     = $totalRooms > 0 ? round($stats['total_room_revenue'] / $totalRooms, 2) : 0;

            // 4. Insert night_audit_logs row
            \DB::table('night_audit_logs')->insert([
                'tenant_id' => $property->tenant_id,
                'property_id' => $property->id,
                'business_date' => $bizDate,
                'next_business_date' => $bizDate->copy()->addDay(),
                'started_at' => now(),
                'completed_at' => now(),
                'duration_seconds' => 0,
                'status' => 'completed',
                'reservations_processed' => $stats['reservations_processed'],
                'rooms_posted' => $stats['rooms_posted'],
                'no_shows_marked' => $stats['no_shows_marked'],
                'folios_closed' => $stats['folios_closed'],
                'total_room_revenue' => $stats['total_room_revenue'],
                'total_pos_revenue' => $stats['total_pos_revenue'],
                'total_tax' => $stats['total_tax'],
                'total_payments' => $stats['total_payments'],
                'rooms_occupied' => $occupied,
                'occupancy_percent' => $occPct,
                'arr' => $arr,
                'revpar' => $revpar,
                'notes' => $this->notes ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Roll the business date forward and lock the prior day
            $property->update([
                'current_business_date' => $bizDate->copy()->addDay()->toDateString(),
                'night_audit_locked'    => true,
            ]);

            return $stats;
        });

        $this->confirmRun = false;
        $this->previewMode = false;
        $this->previewSummary = [];
        $this->notes = '';
        session()->flash('success', "Night audit complete. {$result['reservations_processed']} reservations processed, {$result['rooms_posted']} room charges posted, {$result['no_shows_marked']} no-shows marked.");
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $property = $ctx->property();

        $bizDate = $property?->current_business_date
            ? Carbon::parse($property->current_business_date)
            : today();

        $inHouse = Reservation::where('property_id', $propertyId)
            ->where('status', 'checked_in')
            ->where('arrival_date', '<=', $bizDate->toDateString())
            ->where('departure_date', '>', $bizDate->toDateString())
            ->count();
        $expectedNoShows = Reservation::where('property_id', $propertyId)
            ->whereDate('arrival_date', $bizDate)
            ->whereIn('status', ['confirmed','tentative'])
            ->count();
        $expectedDepartures = Reservation::where('property_id', $propertyId)
            ->whereDate('departure_date', $bizDate)
            ->where('status', 'checked_in')
            ->count();

        $previousAudits = DB::table('night_audit_logs')
            ->where('property_id', $propertyId)
            ->orderByDesc('business_date')
            ->limit(7)
            ->get();

        return view('livewire.operations.night-audit', compact('bizDate','property','inHouse','expectedNoShows','expectedDepartures','previousAudits'));
    }
}
