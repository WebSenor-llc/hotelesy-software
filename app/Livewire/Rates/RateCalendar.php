<?php

namespace App\Livewire\Rates;

use App\Models\DailyInventory;
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
class RateCalendar extends Component
{
    public string $startDate;
    public int $days = 14;
    public ?int $selectedRoomTypeId = null;
    public ?int $selectedRatePlanId = null;
    public ?string $selectedDate = null;
    public float $editRate = 0;
    public bool $editStopSell = false;

    // Bulk update form
    public bool $showBulk = false;
    public array $bulkRoomTypeIds = [];
    public string $bulkStart = '';
    public string $bulkEnd = '';
    public float $bulkRate = 0;
    public bool $bulkApplyWeekend = false;
    public float $bulkWeekendPct = 20;
    public bool $bulkStopSell = false;
    public ?int $bulkRatePlanId = null;

    public function mount(): void
    {
        $this->startDate = today()->toDateString();
    }

    public function shift(int $offset): void
    {
        $this->startDate = Carbon::parse($this->startDate)->addDays($offset)->toDateString();
    }

    public function startBulk(): void
    {
        $this->bulkRoomTypeIds = [];
        $this->bulkStart = $this->startDate;
        $this->bulkEnd = Carbon::parse($this->startDate)->addDays($this->days - 1)->toDateString();
        $this->bulkRate = 0;
        $this->bulkApplyWeekend = false;
        $this->bulkWeekendPct = 20;
        $this->bulkStopSell = false;
        $this->bulkRatePlanId = null;
        $this->showBulk = true;
    }

    public function cancelBulk(): void
    {
        $this->showBulk = false;
    }

    public function applyBulk(): void
    {
        $this->validate([
            'bulkRoomTypeIds'  => 'required|array|min:1',
            'bulkRoomTypeIds.*'=> 'integer|exists:room_types,id',
            'bulkStart'        => 'required|date',
            'bulkEnd'          => 'required|date|after_or_equal:bulkStart',
            'bulkRate'         => 'required|numeric|min:0',
            'bulkApplyWeekend' => 'boolean',
            'bulkWeekendPct'   => 'numeric|min:-100|max:500',
            'bulkStopSell'     => 'boolean',
            'bulkRatePlanId'   => 'nullable|integer|exists:rate_plans,id',
        ]);

        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $tenantId   = $ctx->tenantId();

        $start = Carbon::parse($this->bulkStart);
        $end   = Carbon::parse($this->bulkEnd);

        // Resolve rate plans: when no specific rate plan picked, use ALL active plans for selected room types.
        $rpQuery = RatePlan::where('property_id', $propertyId)
            ->where('is_active', true)
            ->whereIn('room_type_id', $this->bulkRoomTypeIds);
        if ($this->bulkRatePlanId) {
            $rpQuery->where('id', $this->bulkRatePlanId);
        }
        $ratePlans = $rpQuery->get(['id', 'room_type_id']);

        if ($ratePlans->isEmpty()) {
            session()->flash('error', 'No active rate plans found for the selected room types.');
            return;
        }

        $count = 0;
        DB::transaction(function () use ($ratePlans, $start, $end, $propertyId, $tenantId, &$count) {
            $now = now();
            $cur = $start->copy();
            while ($cur->lte($end)) {
                $isWeekend = $cur->isWeekend();
                $effectiveRate = $this->bulkRate;
                if ($this->bulkApplyWeekend && $isWeekend) {
                    $effectiveRate = round($this->bulkRate * (1 + ((float) $this->bulkWeekendPct / 100)), 2);
                }

                foreach ($ratePlans as $rp) {
                    DB::table('daily_rates')->updateOrInsert(
                        [
                            'rate_plan_id' => $rp->id,
                            'room_type_id' => $rp->room_type_id,
                            'date'         => $cur->toDateString(),
                        ],
                        [
                            'tenant_id'   => $tenantId,
                            'property_id' => $propertyId,
                            'rate'        => $effectiveRate,
                            'stop_sell'   => $this->bulkStopSell,
                            'updated_by'  => auth()->id(),
                            'updated_at'  => $now,
                            'created_at'  => $now,
                        ]
                    );
                    $count++;
                }
                $cur->addDay();
            }
        });

        $this->showBulk = false;
        session()->flash('success', "Bulk update applied: {$count} daily-rate row(s) upserted.");
    }

    public function pickCell(int $rtId, int $rpId, string $date, float $rate): void
    {
        $this->selectedRoomTypeId = $rtId;
        $this->selectedRatePlanId = $rpId;
        $this->selectedDate = $date;
        $this->editRate = $rate;

        $existing = DailyRate::where('rate_plan_id', $rpId)
            ->where('room_type_id', $rtId)
            ->where('date', $date)->first();
        $this->editStopSell = (bool) ($existing->stop_sell ?? false);
    }

    public function saveRate(): void
    {
        $this->validate([
            'editRate' => 'required|numeric|min:0',
            'selectedRoomTypeId' => 'required|exists:room_types,id',
            'selectedRatePlanId' => 'required|exists:rate_plans,id',
            'selectedDate' => 'required|date',
        ]);

        $ctx = app(TenantContext::class);
        DailyRate::updateOrCreate([
            'rate_plan_id' => $this->selectedRatePlanId,
            'room_type_id' => $this->selectedRoomTypeId,
            'date' => $this->selectedDate,
        ], [
            'tenant_id'  => $ctx->tenantId(),
            'property_id'=> $ctx->propertyId(),
            'rate'       => $this->editRate,
            'stop_sell'  => $this->editStopSell,
            'updated_by' => auth()->id(),
        ]);
        session()->flash('success', "Rate updated for {$this->selectedDate}");
        $this->reset(['selectedDate','selectedRoomTypeId','selectedRatePlanId']);
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $start = Carbon::parse($this->startDate);
        $end   = $start->copy()->addDays($this->days - 1);
        $dates = collect(range(0, $this->days - 1))->map(fn ($i) => $start->copy()->addDays($i));

        $roomTypes = RoomType::where('property_id', $propertyId)->where('is_active', true)->orderBy('base_rate')->get();
        $ratePlans = RatePlan::where('property_id', $propertyId)->where('is_active', true)->get()->groupBy('room_type_id');

        $totalByType = Room::where('property_id', $propertyId)->where('is_active', true)
            ->selectRaw('room_type_id, count(*) as cnt')->groupBy('room_type_id')->pluck('cnt','room_type_id');

        // Existing daily_rates
        $rates = DailyRate::where('property_id', $propertyId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($r) => $r->room_type_id.'|'.$r->rate_plan_id.'|'.$r->date->toDateString());

        // Reservations to compute availability
        $reservations = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $end->toDateString())
            ->where('departure_date', '>=', $start->toDateString())
            ->whereIn('status', ['confirmed','checked_in','tentative'])
            ->with('rooms')->get();

        $sold = [];
        foreach ($reservations as $r) {
            foreach ($r->rooms as $rr) {
                $a = Carbon::parse($rr->arrival_date); $d = Carbon::parse($rr->departure_date);
                $cur = $a->copy();
                while ($cur->lt($d)) {
                    $key = $rr->room_type_id.'|'.$cur->toDateString();
                    $sold[$key] = ($sold[$key] ?? 0) + 1;
                    $cur->addDay();
                }
            }
        }

        return view('livewire.rates.rate-calendar', compact('dates','roomTypes','ratePlans','totalByType','rates','sold','start','end'));
    }
}
