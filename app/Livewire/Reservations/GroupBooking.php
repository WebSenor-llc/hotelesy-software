<?php

namespace App\Livewire\Reservations;

use App\Models\Company;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class GroupBooking extends Component
{
    // Master booking
    public string $arrivalDate = '';
    public string $departureDate = '';
    public ?string $arrivalTime = '14:00';
    public ?string $departureTime = '12:00';

    // Group lead contact
    public string $groupName = '';
    public string $contactName = '';
    public string $contactPhone = '';
    public string $contactEmail = '';
    public ?int $companyId = null;
    public string $sourceType = 'direct';
    public string $sourceName = '';
    public string $marketSegment = 'group';

    // Room selections — array of:
    // [['room_type_id'=>X, 'rate_plan_id'=>Y, 'quantity'=>Z, 'rate_per_night'=>R, 'guest_name'=>'', 'adults'=>2, 'children'=>0]]
    public array $roomLines = [];

    // Misc
    public string $specialRequests = '';
    public string $internalNotes = '';
    public ?float $advanceAmount = 0;
    public ?string $advanceDueDate = null;
    public string $billingTo = 'guest';   // guest | company | split
    public bool $isVip = false;

    public function mount(): void
    {
        $this->arrivalDate   = today()->toDateString();
        $this->departureDate = today()->copy()->addDay()->toDateString();
        $this->addRoomLine();
    }

    public function addRoomLine(): void
    {
        $this->roomLines[] = [
            'room_type_id'   => null,
            'rate_plan_id'   => null,
            'quantity'       => 1,
            'rate_per_night' => 0,
            'guest_name'     => '',
            'adults'         => 2,
            'children'       => 0,
        ];
    }

    public function removeRoomLine(int $i): void
    {
        if (count($this->roomLines) <= 1) return;
        unset($this->roomLines[$i]);
        $this->roomLines = array_values($this->roomLines);
    }

    public function autofillRate(int $i): void
    {
        if (! isset($this->roomLines[$i])) return;
        $rt = RoomType::find($this->roomLines[$i]['room_type_id'] ?? null);
        if ($rt) {
            $this->roomLines[$i]['rate_per_night'] = (float) ($rt->base_rate ?? 0);
        }
    }

    public function updatedRoomLines($value, $key): void
    {
        // When user picks a room_type_id and rate is still 0, auto-fill from base_rate
        if (str_ends_with($key, '.room_type_id')) {
            $i = (int) explode('.', $key)[0];
            if (($this->roomLines[$i]['rate_per_night'] ?? 0) <= 0) {
                $this->autofillRate($i);
            }
        }
    }

    public function getNightsProperty(): int
    {
        try {
            $a = Carbon::parse($this->arrivalDate);
            $d = Carbon::parse($this->departureDate);
            return max(1, $a->diffInDays($d));
        } catch (\Throwable $e) {
            return 1;
        }
    }

    public function getTotalRoomsProperty(): int
    {
        return (int) collect($this->roomLines)->sum(fn ($l) => max(1, (int) ($l['quantity'] ?? 1)));
    }

    public function getSubtotalProperty(): float
    {
        $nights = $this->nights;
        return (float) collect($this->roomLines)->sum(function ($l) use ($nights) {
            return ((float) ($l['rate_per_night'] ?? 0)) * (int) ($l['quantity'] ?? 1) * $nights;
        });
    }

    public function getTaxProperty(): float
    {
        // Apply per-room tax: 12% if ≤ 7500/night, else 18%
        $nights = $this->nights;
        $taxTotal = 0;
        foreach ($this->roomLines as $l) {
            $rate = (float) ($l['rate_per_night'] ?? 0);
            $qty  = (int) ($l['quantity'] ?? 1);
            $pct  = $rate > 7500 ? 18 : 12;
            $taxTotal += round($rate * $qty * $nights * $pct / 100, 2);
        }
        return round($taxTotal, 2);
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal + $this->tax;
    }

    public function save(): void
    {
        $this->validate([
            'groupName'      => 'required|string|max:200',
            'contactName'    => 'required|string|max:200',
            'contactPhone'   => 'required|string|max:30',
            'contactEmail'   => 'nullable|email|max:200',
            'arrivalDate'    => 'required|date',
            'departureDate'  => 'required|date|after:arrivalDate',
            'roomLines'      => 'required|array|min:1',
            'roomLines.*.room_type_id' => 'required|integer|exists:room_types,id',
            'roomLines.*.quantity'     => 'required|integer|min:1|max:100',
            'roomLines.*.rate_per_night' => 'required|numeric|min:0',
            'roomLines.*.adults'   => 'required|integer|min:1|max:10',
            'roomLines.*.children' => 'integer|min:0|max:10',
        ]);

        $ctx = app(TenantContext::class);
        $tenantId   = $ctx->tenantId();
        $propertyId = $ctx->propertyId();
        $nights     = $this->nights;
        $subtotal   = $this->subtotal;
        $tax        = $this->tax;
        $total      = $this->total;

        DB::transaction(function () use ($tenantId, $propertyId, $nights, $subtotal, $tax, $total) {
            // 1. Master reservation
            $masterNumber = 'GRP-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
            $master = Reservation::create([
                'tenant_id'       => $tenantId,
                'property_id'     => $propertyId,
                'reservation_number' => $masterNumber,
                'confirmation_number' => $masterNumber,
                'is_group_master' => true,
                'company_id'      => $this->companyId ?: null,
                'guest_name'      => $this->contactName,
                'guest_phone'     => $this->contactPhone,
                'guest_email'     => $this->contactEmail ?: null,
                'source_type'     => $this->sourceType ?: 'direct',
                'source_name'     => $this->sourceName ?: $this->groupName,
                'market_segment'  => $this->marketSegment ?: 'group',
                'business_source' => $this->groupName,
                'arrival_date'    => $this->arrivalDate,
                'departure_date'  => $this->departureDate,
                'arrival_time'    => $this->arrivalTime ?: '14:00',
                'departure_time'  => $this->departureTime ?: '12:00',
                'nights'          => $nights,
                'rooms_count'     => $this->totalRooms,
                'adults'          => collect($this->roomLines)->sum(fn ($l) => (int) ($l['adults'] ?? 0) * (int) ($l['quantity'] ?? 1)),
                'children'        => collect($this->roomLines)->sum(fn ($l) => (int) ($l['children'] ?? 0) * (int) ($l['quantity'] ?? 1)),
                'status'          => Reservation::STATUS_CONFIRMED ?? 'confirmed',
                'room_revenue'    => $subtotal,
                'total_tax'       => $tax,
                'total_discount'  => 0,
                'total_amount'    => $total,
                'paid_amount'     => $this->advanceAmount ?: 0,
                'balance_amount'  => $total - ($this->advanceAmount ?: 0),
                'currency'        => 'INR',
                'advance_amount'  => $this->advanceAmount ?: 0,
                'advance_due_date' => $this->advanceDueDate ?: null,
                'special_requests' => $this->specialRequests ?: null,
                'internal_notes'  => $this->internalNotes ?: null,
                'is_vip'          => $this->isVip,
                'billing_to'      => $this->billingTo,
                'created_by'      => auth()->id(),
            ]);

            // 2. Expand room lines into individual reservation_rooms entries
            $a = Carbon::parse($this->arrivalDate);
            $d = Carbon::parse($this->departureDate);
            $idx = 0;
            foreach ($this->roomLines as $line) {
                $qty      = max(1, (int) ($line['quantity'] ?? 1));
                $rate     = (float) ($line['rate_per_night'] ?? 0);
                $totalRate= round($rate * $nights, 2);
                $taxPct   = $rate > 7500 ? 18 : 12;
                $taxAmt   = round($totalRate * $taxPct / 100, 2);

                for ($n = 0; $n < $qty; $n++) {
                    $idx++;
                    ReservationRoom::create([
                        'tenant_id'      => $tenantId,
                        'property_id'    => $propertyId,
                        'reservation_id' => $master->id,
                        'room_type_id'   => $line['room_type_id'],
                        'rate_plan_id'   => $line['rate_plan_id'] ?: null,
                        'arrival_date'   => $a->toDateString(),
                        'departure_date' => $d->toDateString(),
                        'nights'         => $nights,
                        'adults'         => (int) ($line['adults'] ?? 2),
                        'children'       => (int) ($line['children'] ?? 0),
                        'extra_beds'     => 0,
                        'guest_name'     => trim($line['guest_name'] ?? '') ?: ($this->contactName . ' #' . $idx),
                        'average_rate'   => $rate,
                        'total_rate'     => $totalRate,
                        'total_tax'      => $taxAmt,
                        'total_amount'   => $totalRate + $taxAmt,
                        // reservation_rooms.status is a different enum from reservations.status
                        // — 'booked' / 'checked_in' / 'checked_out' / 'cancelled' / 'no_show'.
                        // 'confirmed' isn't a valid reservation_room state.
                        'status'         => 'booked',
                    ]);
                }
            }
        });

        session()->flash('success', "Group booking created — {$this->totalRooms} rooms across {$nights} night" . ($nights === 1 ? '' : 's') . ", total ₹" . number_format($total, 2) . '.');
        $this->redirect(route('reservations.index'), navigate: true);
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $roomTypes = RoomType::where('property_id', $propertyId)->where('is_active', true)->orderBy('name')->get();
        $ratePlans = RatePlan::where('property_id', $propertyId)->where('is_active', true)->orderBy('name')->get();
        $companies = $ctx->bypass(fn () => Company::orderBy('name')->limit(200)->get());

        return view('livewire.reservations.group-booking', [
            'roomTypes' => $roomTypes,
            'ratePlans' => $ratePlans,
            'companies' => $companies,
            'nights'    => $this->nights,
            'totalRooms'=> $this->totalRooms,
            'subtotal'  => $this->subtotal,
            'tax'       => $this->tax,
            'total'     => $this->total,
        ]);
    }
}
