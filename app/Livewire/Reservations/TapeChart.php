<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class TapeChart extends Component
{
    public string $startDate;
    public int $days = 21;

    public function mount(): void
    {
        $this->startDate = today()->copy()->subDays(2)->toDateString();
    }

    public function shift(int $offset): void
    {
        $this->startDate = Carbon::parse($this->startDate)->addDays($offset)->toDateString();
    }

    public function jumpToday(): void
    {
        $this->startDate = today()->copy()->subDays(2)->toDateString();
    }

    public function reassignRoom(int $reservationRoomId, int $newRoomId, ?string $droppedDate = null): void
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $rr = ReservationRoom::where('property_id', $propertyId)->find($reservationRoomId);
        if (!$rr) { session()->flash('error', 'Reservation-room not found.'); return; }

        $newRoom = Room::where('property_id', $propertyId)->find($newRoomId);
        if (!$newRoom) { session()->flash('error', 'Target room not found.'); return; }

        if ($rr->room_id === $newRoomId) {
            return; // no-op
        }

        // Compatibility: same room type (or fall back to allow when target room_type matches)
        if ((int) $newRoom->room_type_id !== (int) $rr->room_type_id) {
            session()->flash('error', "Cannot reassign — room {$newRoom->number} is a different room type.");
            return;
        }

        $arrival   = Carbon::parse($rr->arrival_date)->toDateString();
        $departure = Carbon::parse($rr->departure_date)->toDateString();

        // Conflict check: any *other* reservation_room on the new room overlapping these dates
        $conflict = ReservationRoom::where('room_id', $newRoomId)
            ->where('id', '!=', $rr->id)
            ->whereHas('reservation', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'voided', 'no_show']);
            })
            ->where('arrival_date', '<', $departure)
            ->where('departure_date', '>', $arrival)
            ->exists();

        if ($conflict) {
            session()->flash('error', "Room {$newRoom->number} is occupied for those dates — reassignment blocked.");
            return;
        }

        DB::transaction(function () use ($rr, $newRoomId) {
            $rr->update(['room_id' => $newRoomId]);
        });

        session()->flash('success', "Reservation moved to room {$newRoom->number}.");
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $start = Carbon::parse($this->startDate);
        $end   = $start->copy()->addDays($this->days - 1);
        $dates = collect(range(0, $this->days - 1))->map(fn ($i) => $start->copy()->addDays($i));

        $rooms = Room::where('property_id', $propertyId)
            ->where('is_active', true)
            ->with('roomType')
            ->orderBy('floor')->orderBy('number')
            ->get();

        // Reservations whose stay overlaps the window AND has a room assigned
        $reservations = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $end->toDateString())
            ->where('departure_date', '>=', $start->toDateString())
            ->whereNotIn('status', ['cancelled','voided'])
            ->with(['rooms' => fn ($q) => $q->whereNotNull('room_id')])
            ->get();

        // Build {room_id => [{reservation, start_offset, length, color}]}
        $bars = [];
        foreach ($reservations as $r) {
            foreach ($r->rooms as $rr) {
                if (!$rr->room_id) continue;
                $a = Carbon::parse($rr->arrival_date);
                $d = Carbon::parse($rr->departure_date);
                // Clamp to window
                $barStart = $a->lt($start) ? $start : $a;
                $barEnd   = $d->gt($end->copy()->addDay()) ? $end->copy()->addDay() : $d;
                $offset   = $barStart->diffInDays($start);
                $length   = max(1, $barStart->diffInDays($barEnd));
                $color = match($r->status) {
                    'tentative'   => 'bg-slate-300 text-slate-900',
                    'confirmed'   => 'bg-sky-400 text-white',
                    'checked_in'  => 'bg-emerald-500 text-white',
                    'checked_out' => 'bg-violet-400 text-white',
                    'no_show'     => 'bg-amber-400 text-amber-900',
                    default       => 'bg-slate-300 text-slate-900',
                };
                $bars[$rr->room_id][] = [
                    'r' => $r, 'rr' => $rr, 'offset' => $offset, 'length' => $length, 'color' => $color,
                    'rr_id' => $rr->id,
                    'starts_in_window' => $a->gte($start),
                    'ends_in_window' => $d->lte($end->copy()->addDay()),
                ];
            }
        }

        return view('livewire.reservations.tape-chart', compact('rooms','dates','bars','start','end'));
    }
}
