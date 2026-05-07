<?php

namespace App\Livewire\FrontOffice;

use App\Models\Reservation;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class MovementLists extends Component
{
    public string $tab = 'arrivals';
    public string $date;

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $d = Carbon::parse($this->date);

        $arrivals = Reservation::where('property_id', $propertyId)
            ->whereDate('arrival_date', $d)
            ->whereIn('status', ['confirmed','checked_in','tentative'])
            ->with('rooms.roomType','rooms.room')
            ->orderBy('arrival_time')
            ->get();

        $departures = Reservation::where('property_id', $propertyId)
            ->whereDate('departure_date', $d)
            ->whereIn('status', ['checked_in','checked_out'])
            ->with('rooms.room')
            ->orderBy('departure_time')
            ->get();

        $inHouse = Reservation::where('property_id', $propertyId)
            ->where('status', 'checked_in')
            ->where('arrival_date', '<=', $d->toDateString())
            ->where('departure_date', '>', $d->toDateString())
            ->with('rooms.room')
            ->orderBy('departure_date')
            ->get();

        $noShows = Reservation::where('property_id', $propertyId)
            ->whereDate('arrival_date', $d)
            ->where('status', 'no_show')
            ->get();

        return view('livewire.front-office.movement-lists', compact('arrivals','departures','inHouse','noShows','d'));
    }
}
