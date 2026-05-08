<?php

namespace App\Livewire\FrontOffice;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app-shell')]
class CheckIn extends Component
{
    use WithFileUploads;

    public ?int $selectedReservationId = null;
    public ?int $selectedRoomId = null;
    public string $keyCardNumber = '';

    // ID capture at check-in — required for legal compliance (Section 14
    // Foreigners Act, hotel-register rules, and good practice for domestic
    // guests too). The cashier can scan/photograph the ID and attach.
    public string $id_type = 'aadhaar';
    public string $id_number = '';
    public array $idProofUploads = [];

    // Foreign-national fields — only filled if guest is foreign. Mirrors
    // NewBooking and WalkInCheckIn so any path can capture FRRO data.
    public bool $is_foreign_national = false;
    public string $nationality = 'IN';
    public string $passport_number = '';
    public ?string $passport_expiry = null;
    public string $visa_number = '';
    public ?string $visa_expiry = null;
    public string $arrival_from_country = '';
    public ?string $arrival_date_in_india = null;
    public string $next_destination = '';

    public function selectReservation(int $id): void
    {
        $this->selectedReservationId = $id;
        $this->selectedRoomId = null;

        // Pre-fill ID / FRRO fields from the existing Guest record so the
        // cashier doesn't re-enter what was already captured at booking.
        $reservation = Reservation::with('guest')->find($id);
        if ($reservation && $reservation->guest) {
            $g = $reservation->guest;
            $this->id_type             = $g->id_type ?: 'aadhaar';
            $this->id_number           = $g->id_number ?: '';
            $this->is_foreign_national = (bool) $g->is_foreign_national;
            $this->nationality         = $g->nationality ?: 'IN';
            $this->passport_number     = $g->passport_number ?: '';
            $this->passport_expiry     = $g->passport_expiry?->toDateString();
            $this->visa_number         = $g->visa_number ?: '';
            $this->visa_expiry         = $g->visa_expiry?->toDateString();
            $this->arrival_from_country = $g->arrival_from_country ?? '';
            $this->arrival_date_in_india = $g->arrival_in_india?->toDateString();
            $this->next_destination    = $g->next_destination ?: '';
        }
    }

    public function checkIn()
    {
        $this->validate([
            'selectedReservationId' => 'required|exists:reservations,id',
            'selectedRoomId'        => 'required|exists:rooms,id',
            'id_type'               => 'required|in:aadhaar,passport,voter,driving_license,pan,other',
            'id_number'             => 'required|string|max:50',
            'idProofUploads.*'      => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,webp',
            // FRRO fields — required if foreign national
            'is_foreign_national'   => 'boolean',
            'nationality'           => 'required|string|size:2',
            'passport_number'       => 'nullable|required_if:is_foreign_national,true|string|max:30',
            'passport_expiry'       => 'nullable|date',
            'visa_number'           => 'nullable|required_if:is_foreign_national,true|string|max:30',
            'visa_expiry'           => 'nullable|date',
            'arrival_from_country'  => 'nullable|string|max:100',
            'arrival_date_in_india' => 'nullable|date',
            'next_destination'      => 'nullable|string|max:100',
        ], [
            'id_number.required' => 'ID number is required at check-in (legal compliance).',
            'passport_number.required_if' => 'Passport number is required for foreign nationals (Form C).',
            'visa_number.required_if'     => 'Visa number is required for foreign nationals (Form C).',
        ]);

        $ctx = app(TenantContext::class);
        $reservation = Reservation::with('guest')->where('property_id', $ctx->propertyId())->findOrFail($this->selectedReservationId);
        $room = Room::where('property_id', $ctx->propertyId())->findOrFail($this->selectedRoomId);

        // Create or update the linked Guest with compliance data.
        // If the reservation has no guest_id yet (older bookings), create one.
        $guest = $reservation->guest;
        if (!$guest) {
            $parts = preg_split('/\s+/', trim($reservation->guest_name), 2);
            $guest = Guest::create([
                'tenant_id'  => $reservation->tenant_id,
                'first_name' => $parts[0] ?? $reservation->guest_name,
                'last_name'  => $parts[1] ?? '',
                'phone'      => $reservation->guest_phone,
                'email'      => $reservation->guest_email,
            ]);
            $reservation->update(['guest_id' => $guest->id]);
        }

        $guest->update([
            'id_type'                  => $this->id_type,
            'id_number'                => $this->id_number,
            'is_foreign_national'      => $this->is_foreign_national,
            'nationality'              => $this->nationality ?: 'IN',
            'passport_number'          => $this->passport_number ?: null,
            'passport_expiry'          => $this->passport_expiry ?: null,
            'visa_number'              => $this->visa_number ?: null,
            'visa_expiry'              => $this->visa_expiry ?: null,
            'arrival_from_country'     => $this->arrival_from_country ?: null,
            'arrival_in_india'         => $this->arrival_date_in_india ?: null,
            'next_destination'         => $this->next_destination ?: null,
        ]);

        // Persist uploaded ID-proof files under storage/app/public/id-proofs/{guest_id}.
        // Files are stored against the Guest, not the reservation, so a returning
        // guest's existing IDs aren't duplicated. Existing files are preserved.
        $existing = (array) ($guest->id_proof_files ?? []);
        foreach ($this->idProofUploads ?? [] as $upload) {
            if ($upload) {
                $path = $upload->store("public/id-proofs/{$guest->id}");
                $existing[] = str_replace('public/', '', $path);
            }
        }
        if (!empty($existing)) {
            $guest->update(['id_proof_files' => array_values(array_unique($existing))]);
        }

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

        $this->reset([
            'selectedReservationId', 'selectedRoomId', 'keyCardNumber',
            'id_number', 'idProofUploads',
            'is_foreign_national', 'passport_number', 'passport_expiry',
            'visa_number', 'visa_expiry', 'arrival_from_country',
            'arrival_date_in_india', 'next_destination',
        ]);
        $this->id_type = 'aadhaar';
        $this->nationality = 'IN';

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
                // Include vacant_dirty rooms too — at busy properties, the cashier
                // often needs to assign a room that just freed up but housekeeping
                // hasn't yet logged the clean. The blade shows a "Needs cleaning"
                // badge so the FO knows to coordinate with HK before handing keys.
                $availableRooms = Room::where('property_id', $propertyId)
                    ->where('is_active', true)
                    ->whereIn('status', ['vacant_clean','inspected','vacant_dirty'])
                    ->where('fo_status', 'vacant')  // never assign reserved/occupied
                    ->when($rRoom?->room_type_id, fn ($q, $rtId) => $q->where('room_type_id', $rtId))
                    // Clean rooms first, dirty last. CASE WHEN works on both MySQL and SQLite.
                    ->orderByRaw("CASE status WHEN 'vacant_clean' THEN 1 WHEN 'inspected' THEN 2 WHEN 'vacant_dirty' THEN 3 ELSE 4 END")
                    ->orderBy('number')
                    ->get();
            }
        }

        return view('livewire.front-office.check-in', compact('arrivals', 'availableRooms'));
    }
}
