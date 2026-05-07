<?php

namespace App\Livewire\FrontOffice;

use App\Models\Folio;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class CheckIn extends Component
{
    public ?int $selectedReservationId = null;
    public ?int $selectedRoomId = null;
    public string $keyCardNumber = '';

    public function selectReservation(int $id): void
    {
        $this->selectedReservationId = $id;
        $this->selectedRoomId = null;
    }

    public function checkIn()
    {
        $this->validate([
            'selectedReservationId' => 'required|exists:reservations,id',
            'selectedRoomId'        => 'required|exists:rooms,id',
        ]);

        $ctx = app(TenantContext::class);
        $reservation = Reservation::where('property_id', $ctx->propertyId())->findOrFail($this->selectedReservationId);
        $room = Room::where('property_id', $ctx->propertyId())->findOrFail($this->selectedRoomId);

        // Update reservation
        $reservation->update([
            'status' => Reservation::STATUS_CHECKED_IN,
            'status_changed_at' => now(),
            'status_changed_by' => auth()->id(),
        ]);
        // Update first reservation_room
        $rRoom = $reservation->rooms()->first();
        if ($rRoom) {
            $rRoom->update([
                'room_id'        => $room->id,
                'status'         => 'checked_in',
                'checked_in_at'  => now(),
                'checked_in_by'  => auth()->id(),
                'key_card_number'=> $this->keyCardNumber ?: null,
            ]);
        }
        // Update room status
        $room->update([
            'status'    => 'occupied_clean',
            'fo_status' => 'occupied',
        ]);
        // Open folio
        Folio::firstOrCreate([
            'tenant_id'      => $reservation->tenant_id,
            'property_id'    => $reservation->property_id,
            'reservation_id' => $reservation->id,
            'reservation_room_id' => $rRoom?->id,
        ], [
            'folio_number'   => 'F-'.$reservation->reservation_number,
            'type'           => 'guest',
            'guest_id'       => $reservation->guest_id,
            'billing_name'   => $reservation->guest_name,
            'total_charges'  => $reservation->room_revenue,
            'total_taxes'    => $reservation->total_tax,
            'total_payments' => 0,
            'balance'        => $reservation->total_amount,
            'currency'       => $reservation->currency ?: 'INR',
            'status'         => Folio::STATUS_OPEN,
        ]);

        // Auto-apply early-check-in fee if applicable
        $eciResult = app(\App\Services\Billing\EciLcoService::class)
            ->applyEarlyCheckIn($reservation->fresh());

        $this->reset(['selectedReservationId', 'selectedRoomId', 'keyCardNumber']);

        $msg = "{$reservation->guest_name} checked in to room {$room->number}.";
        if (in_array($eciResult['kind'], ['half_day','full_day']) && $eciResult['amount'] > 0) {
            $msg .= " Early-check-in fee posted: ₹" . number_format($eciResult['amount'], 2)
                . " ({$eciResult['kind']}).";
        }
        session()->flash('success', $msg);
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $today = Carbon::today();

        $arrivals = Reservation::where('property_id', $propertyId)
            ->where('arrival_date', '<=', $today->toDateString())
            ->where('departure_date', '>=', $today->toDateString())
            ->whereIn('status', ['confirmed', 'tentative'])
            ->orderBy('arrival_date')
            ->get();

        $availableRooms = collect();
        if ($this->selectedReservationId) {
            $reservation = Reservation::where('property_id', $propertyId)->find($this->selectedReservationId);
            if ($reservation) {
                $rRoom = $reservation->rooms()->first();
                $availableRooms = Room::where('property_id', $propertyId)
                    ->where('is_active', true)
                    ->whereIn('status', ['vacant_clean','inspected'])
                    ->when($rRoom?->room_type_id, fn ($q, $rtId) => $q->where('room_type_id', $rtId))
                    ->orderBy('number')
                    ->get();
            }
        }

        return view('livewire.front-office.check-in', compact('arrivals', 'availableRooms'));
    }
}
