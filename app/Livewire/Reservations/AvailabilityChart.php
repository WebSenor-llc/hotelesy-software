<?php

namespace App\Livewire\Reservations;

use App\Models\DailyRate;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class AvailabilityChart extends Component
{
    public string $startDate;
    public int $days = 14;

    // Popover state
    public ?int $popoverRoomTypeId = null;
    public ?string $popoverDate = null;
    public bool $popStopSell = false;
    public bool $popSoldOut = false;
    public bool $popAllowOverbook = false;
    public int $popOverbookLimit = 0;

    public function mount(): void
    {
        $this->startDate = Carbon::today()->toDateString();
    }

    public function shift(int $offsetDays): void
    {
        $this->startDate = Carbon::parse($this->startDate)->addDays($offsetDays)->toDateString();
    }

    public function jumpToToday(): void
    {
        $this->startDate = Carbon::today()->toDateString();
    }

    public function openCell(int $roomTypeId, string $date): void
    {
        $this->popoverRoomTypeId = $roomTypeId;
        $this->popoverDate = $date;

        // Pull a representative daily_rates row for the room type/date (any rate plan).
        $row = DB::table('daily_rates')
            ->where('room_type_id', $roomTypeId)
            ->where('date', $date)
            ->orderBy('id')
            ->first();
        $this->popStopSell = (bool) ($row->stop_sell ?? false);
        $this->popSoldOut  = (bool) ($row->is_sold_out ?? false);

        $rt = RoomType::find($roomTypeId);
        $this->popAllowOverbook = (bool) ($rt->allow_overbook ?? false);
        $this->popOverbookLimit = (int) ($rt->overbook_limit ?? 0);
    }

    public function closeCell(): void
    {
        $this->popoverRoomTypeId = null;
        $this->popoverDate = null;
    }

    public function toggleStopSell(): void
    {
        if (!$this->popoverRoomTypeId || !$this->popoverDate) return;
        $ctx = app(TenantContext::class);
        $newVal = !$this->popStopSell;
        $this->setDailyRateFlags(['stop_sell' => $newVal], $ctx);
        $this->popStopSell = $newVal;
        session()->flash('success', $newVal ? 'Stop-sell enabled.' : 'Stop-sell cleared.');
    }

    public function toggleSoldOut(): void
    {
        if (!$this->popoverRoomTypeId || !$this->popoverDate) return;
        $ctx = app(TenantContext::class);
        $newVal = !$this->popSoldOut;
        $this->setDailyRateFlags(['is_sold_out' => $newVal], $ctx);
        $this->popSoldOut = $newVal;
        session()->flash('success', $newVal ? 'Marked sold-out.' : 'Sold-out flag cleared.');
    }

    public function toggleOverbook(): void
    {
        if (!$this->popoverRoomTypeId) return;
        $rt = RoomType::find($this->popoverRoomTypeId);
        if (!$rt) return;
        $newVal = !$this->popAllowOverbook;
        $rt->update([
            'allow_overbook' => $newVal,
            'overbook_limit' => $newVal && $rt->overbook_limit < 1 ? max(1, $this->popOverbookLimit) : $rt->overbook_limit,
        ]);
        $this->popAllowOverbook = $newVal;
        $this->popOverbookLimit = (int) $rt->fresh()->overbook_limit;
        session()->flash('success', $newVal ? "Overbooking enabled for {$rt->name}." : "Overbooking disabled for {$rt->name}.");
    }

    public function saveOverbookLimit(): void
    {
        if (!$this->popoverRoomTypeId) return;
        $rt = RoomType::find($this->popoverRoomTypeId);
        if (!$rt) return;
        $rt->update(['overbook_limit' => max(0, (int) $this->popOverbookLimit)]);
        session()->flash('success', "Overbook limit set to {$this->popOverbookLimit} for {$rt->name}.");
    }

    protected function setDailyRateFlags(array $flags, TenantContext $ctx): void
    {
        $propertyId = $ctx->propertyId();
        $tenantId   = $ctx->tenantId();

        // Apply flag to all rate plans for this room type (rates may not exist yet — create per-plan rows).
        $ratePlans = RatePlan::where('property_id', $propertyId)
            ->where('room_type_id', $this->popoverRoomTypeId)
            ->where('is_active', true)
            ->pluck('id');

        if ($ratePlans->isEmpty()) {
            // Just upsert one synthetic row keyed by room_type+date with rate_plan_id=null is impossible
            // (rate_plan_id is required). Instead, update any existing rows for that (room_type, date).
            DB::table('daily_rates')
                ->where('room_type_id', $this->popoverRoomTypeId)
                ->where('date', $this->popoverDate)
                ->update(array_merge($flags, ['updated_at' => now()]));
            return;
        }

        $now = now();
        foreach ($ratePlans as $rpId) {
            $existing = DB::table('daily_rates')
                ->where('rate_plan_id', $rpId)
                ->where('room_type_id', $this->popoverRoomTypeId)
                ->where('date', $this->popoverDate)
                ->first();

            if ($existing) {
                DB::table('daily_rates')
                    ->where('id', $existing->id)
                    ->update(array_merge($flags, [
                        'updated_by' => auth()->id(),
                        'updated_at' => $now,
                    ]));
            } else {
                // Seed with the rate plan's base rate so the new row isn't accidentally sold at 0.
                $rp = RatePlan::find($rpId);
                $baseRate = (float) ($rp->base_rate ?? 0);
                DB::table('daily_rates')->insert(array_merge($flags, [
                    'tenant_id'    => $tenantId,
                    'property_id'  => $propertyId,
                    'rate_plan_id' => $rpId,
                    'room_type_id' => $this->popoverRoomTypeId,
                    'date'         => $this->popoverDate,
                    'rate'         => $baseRate,
                    'updated_by'   => auth()->id(),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]));
            }
        }
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $start = Carbon::parse($this->startDate);
        $dates = collect(range(0, $this->days - 1))->map(fn ($i) => $start->copy()->addDays($i));

        $roomTypes = RoomType::where('property_id', $propertyId)->get();
        $totalRoomsByType = Room::where('property_id', $propertyId)
            ->select('room_type_id')
            ->selectRaw('count(*) as cnt')
            ->groupBy('room_type_id')
            ->pluck('cnt', 'room_type_id');

        // Reservations overlapping the window
        $reservations = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $start->copy()->addDays($this->days)->toDateString())
            ->where('departure_date', '>=', $start->toDateString())
            ->whereIn('status', ['confirmed','checked_in','tentative'])
            ->with('rooms')
            ->get();

        // Build occupied count per (room_type_id, date)
        $occupied = [];
        foreach ($reservations as $r) {
            foreach ($r->rooms as $rr) {
                $rtId = $rr->room_type_id;
                $a = Carbon::parse($rr->arrival_date);
                $d = Carbon::parse($rr->departure_date);
                $cur = $a->copy();
                while ($cur->lt($d)) {
                    $key = $rtId.'|'.$cur->toDateString();
                    $occupied[$key] = ($occupied[$key] ?? 0) + 1;
                    $cur->addDay();
                }
            }
        }

        // Stop-sell / sold-out flags per (room_type_id, date) — use any rate plan row (OR over plans).
        $endDate = $start->copy()->addDays($this->days - 1)->toDateString();
        $flagRows = DB::table('daily_rates')
            ->where('property_id', $propertyId)
            ->whereBetween('date', [$start->toDateString(), $endDate])
            ->selectRaw('room_type_id, date, MAX(stop_sell) as stop_sell, MAX(is_sold_out) as is_sold_out')
            ->groupBy('room_type_id', 'date')
            ->get();
        $stopSell = [];
        $soldOut  = [];
        foreach ($flagRows as $row) {
            $key = $row->room_type_id.'|'.\Carbon\Carbon::parse($row->date)->toDateString();
            if ($row->stop_sell)  $stopSell[$key] = true;
            if ($row->is_sold_out) $soldOut[$key] = true;
        }

        return view('livewire.reservations.availability-chart', [
            'dates' => $dates,
            'roomTypes' => $roomTypes,
            'totalRoomsByType' => $totalRoomsByType,
            'occupied' => $occupied,
            'stopSell' => $stopSell,
            'soldOut'  => $soldOut,
        ]);
    }
}
