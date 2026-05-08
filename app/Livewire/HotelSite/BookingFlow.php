<?php

namespace App\Livewire\HotelSite;

use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

#[Layout('layouts.hotel-site')]
class BookingFlow extends HotelSiteBase
{
    public string $checkIn = '';
    public string $checkOut = '';
    public int $adults = 2;
    public int $children = 0;
    public int $roomsCount = 1;
    public ?string $preferredRoomTypeCode = null;

    public ?int $selectedRoomTypeId = null;

    // Guest details
    #[Validate('required|string|min:2|max:120')] public string $guestName = '';
    #[Validate('required|email|max:160')]        public string $guestEmail = '';
    #[Validate('required|string|min:8|max:25')]  public string $guestPhone = '';
    #[Validate('nullable|string|max:1000')]      public ?string $specialRequests = '';

    public string $step = 'rooms'; // rooms | details | confirm
    public ?string $confirmationNumber = null;

    public function mounted(?string $roomTypeCode = null): void
    {
        $this->checkIn  = (string) request('check_in', today()->copy()->addDay()->toDateString());
        $this->checkOut = (string) request('check_out', today()->copy()->addDays(2)->toDateString());
        $this->adults   = (int) request('adults', 2);
        $this->children = (int) request('children', 0);
        $this->roomsCount = (int) request('rooms', 1);
        $this->preferredRoomTypeCode = (string) (request('room_type') ?: '');
    }

    public function pickRoom(int $roomTypeId): void
    {
        $this->selectedRoomTypeId = $roomTypeId;
        $this->step = 'details';
        $this->dispatch('scroll-to-top');
    }

    public function backToRooms(): void
    {
        $this->step = 'rooms';
    }

    public function getNightsProperty(): int
    {
        try {
            $a = Carbon::parse($this->checkIn);
            $d = Carbon::parse($this->checkOut);
            return max(1, $a->diffInDays($d));
        } catch (\Throwable $e) {
            return 1;
        }
    }

    public function getSelectedRoomTypeProperty(): ?RoomType
    {
        if (! $this->selectedRoomTypeId) return null;
        return app(TenantContext::class)->bypass(fn () => RoomType::find($this->selectedRoomTypeId));
    }

    public function submit(): void
    {
        $this->validate();
        if (! $this->selectedRoomTypeId) {
            session()->flash('error', 'Please pick a room first.');
            return;
        }

        $rt = $this->selectedRoomType;
        if (! $rt) {
            session()->flash('error', 'Selected room type no longer available.');
            return;
        }

        $nights = $this->nights;
        $rate = (float) $rt->base_rate;
        $sub  = $rate * $this->roomsCount * $nights;
        $taxPct = $rate > 7500 ? 18 : 12;
        $tax = round($sub * $taxPct / 100, 2);
        $total = $sub + $tax;

        $confNumber = 'BKG-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));

        app(TenantContext::class)->bypass(function () use ($rt, $nights, $sub, $tax, $total, $confNumber) {
            DB::transaction(function () use ($rt, $nights, $sub, $tax, $total, $confNumber) {
                Reservation::create([
                    'tenant_id'           => $this->tenant->id,
                    'property_id'         => $this->property->id,
                    'reservation_number'  => $confNumber,
                    'confirmation_number' => $confNumber,
                    'guest_name'          => $this->guestName,
                    'guest_email'         => $this->guestEmail,
                    'guest_phone'         => $this->guestPhone,
                    'source_type'         => 'website',
                    'source_name'         => 'Direct (hotel website)',
                    'arrival_date'        => $this->checkIn,
                    'departure_date'      => $this->checkOut,
                    'arrival_time'        => '14:00',
                    'departure_time'      => '12:00',
                    'nights'              => $nights,
                    'rooms_count'         => $this->roomsCount,
                    'adults'              => $this->adults,
                    'children'            => $this->children,
                    'status'              => 'tentative', // pending hotel confirmation
                    'room_revenue'        => $sub,
                    'total_tax'           => $tax,
                    'total_amount'        => $total,
                    'paid_amount'         => 0,
                    'balance_amount'      => $total,
                    'currency'            => 'INR',
                    'special_requests'    => $this->specialRequests ?: null,
                    'business_source'     => 'Website direct booking',
                ]);
            });
        });

        $this->confirmationNumber = $confNumber;
        $this->step = 'confirm';
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $roomTypes = $ctx->bypass(fn () => RoomType::where('property_id', $this->property?->id ?? 0)
            ->where('is_active', true)
            ->orderBy('base_rate')
            ->get());

        return view('livewire.hotel-site.booking-flow', [
            'roomTypes' => $roomTypes,
            'nights'    => $this->nights,
        ]);
    }
}
