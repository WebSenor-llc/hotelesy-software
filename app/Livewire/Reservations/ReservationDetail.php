<?php

namespace App\Livewire\Reservations;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app-shell')]
class ReservationDetail extends Component
{
    use WithFileUploads;

    /**
     * Untyped — see https://github.com/livewire/livewire/issues — typed public
     * properties trigger ImplicitRouteBinding which collides with our global
     * tenant scope. We load the model explicitly in mount() instead.
     */
    public $reservation;
    public string $tab = 'stay';

    // Documents tab — upload/view/delete guest ID proofs (Aadhaar, passport,
    // visa, etc.). Files live on the Guest record (id_proof_files JSON) so
    // CheckIn / CRM / Compliance all share the same uploads.
    public array $documentUploads = [];

    // Edit tab
    public string $e_guest_name = '';
    public string $e_guest_phone = '';
    public string $e_guest_email = '';
    public string $e_arrival_date = '';
    public string $e_departure_date = '';
    public int $e_adults = 1;
    public int $e_children = 0;
    public ?int $e_room_type_id = null;
    public string $e_market_segment = '';
    public string $e_source_type = 'direct';
    public string $e_source_name = '';
    public string $e_special_requests = '';
    public string $e_internal_notes = '';
    public bool $e_is_vip = false;

    // Foreign-national / Form C fields. These live on the Guest record
    // (not the reservation), so saveEdit syncs them across.
    public bool $e_is_foreign_national = false;
    public string $e_nationality = 'IN';
    public string $e_passport_number = '';
    public ?string $e_passport_expiry = null;
    public string $e_visa_number = '';
    public ?string $e_visa_expiry = null;
    public string $e_arrival_in_india = '';
    public string $e_next_destination = '';

    // Charge tab
    public string $chargeCategory = 'food';
    public string $chargeDescription = '';
    public float $chargeRate = 0;
    public float $chargeQuantity = 1;
    public float $chargeTaxPercent = 12;
    public string $chargeReference = '';

    // Payment tab — supports split payments (multiple modes in one settlement)
    public array $payLines = [];
    public string $payNote = '';

    // Cancel tab
    public string $cancelReason = '';
    public float $cancellationCharge = 0;

    // Status tab
    public string $newStatus = '';
    public string $statusNote = '';

    // Room move
    public ?int $moveToRoomId = null;

    public function mount(int $reservation): void
    {
        $ctx = app(TenantContext::class);
        $r = Reservation::where('property_id', $ctx->propertyId())
            ->where('id', $reservation)
            ->firstOrFail();
        $this->reservation = $r->load(['guest', 'rooms.roomType', 'rooms.room', 'folios.charges', 'folios.payments']);
        $this->resetPayLines();
        $this->loadEditFromReservation();
    }

    public function resetPayLines(): void
    {
        $bal = max(0, (float) $this->reservation->balance_amount);
        $this->payLines = [
            ['mode'=>'cash','amount'=>$bal,'reference'=>''],
        ];
    }
    public function addPayLine(): void { $this->payLines[] = ['mode'=>'card','amount'=>0,'reference'=>'']; }
    public function removePayLine(int $i): void { unset($this->payLines[$i]); $this->payLines = array_values($this->payLines); }

    private function loadEditFromReservation(): void
    {
        $r = $this->reservation;
        $this->e_guest_name = $r->guest_name ?? '';
        $this->e_guest_phone = $r->guest_phone ?? '';
        $this->e_guest_email = $r->guest_email ?? '';
        $this->e_arrival_date = $r->arrival_date->toDateString();
        $this->e_departure_date = $r->departure_date->toDateString();
        $this->e_adults = (int) $r->adults;
        $this->e_children = (int) $r->children;
        $this->e_room_type_id = $r->rooms->first()?->room_type_id;
        $this->e_market_segment = $r->market_segment ?? '';
        $this->e_source_type = $r->source_type ?? 'direct';
        $this->e_source_name = $r->source_name ?? '';
        $this->e_special_requests = $r->special_requests ?? '';
        $this->e_internal_notes = $r->internal_notes ?? '';
        $this->e_is_vip = (bool) $r->is_vip;

        // Foreign-national fields come from the linked Guest, not the
        // reservation. Pre-fill so the edit tab can show + save them.
        $g = $r->guest;
        $this->e_is_foreign_national = (bool) ($g?->is_foreign_national ?? false);
        $this->e_nationality         = $g?->nationality ?? 'IN';
        $this->e_passport_number     = $g?->passport_number ?? '';
        $this->e_passport_expiry     = $g?->passport_expiry?->toDateString();
        $this->e_visa_number         = $g?->visa_number ?? '';
        $this->e_visa_expiry         = $g?->visa_expiry?->toDateString();
        $this->e_arrival_in_india    = $g?->arrival_in_india?->toDateString() ?? '';
        $this->e_next_destination    = $g?->next_destination ?? '';
    }

    public function saveEdit(): void
    {
        $data = $this->validate([
            'e_guest_name'      => 'required|string|max:255',
            'e_guest_phone'     => 'nullable|string|max:30',
            'e_guest_email'     => 'nullable|email',
            'e_arrival_date'    => 'required|date',
            'e_departure_date'  => 'required|date|after:e_arrival_date',
            'e_adults'          => 'required|integer|min:1|max:20',
            'e_children'        => 'integer|min:0|max:20',
            'e_room_type_id'    => 'required|exists:room_types,id',
            'e_market_segment'  => 'nullable|string|max:100',
            'e_source_type'     => 'required|in:direct,walk_in,phone,email,website,ota,corporate,travel_agent,gds,group',
            'e_source_name'     => 'nullable|string|max:100',
            'e_special_requests'=> 'nullable|string|max:500',
            'e_internal_notes'  => 'nullable|string|max:1000',
            'e_is_vip'          => 'boolean',
            // Foreign-national fields — required only when the toggle is on.
            'e_is_foreign_national' => 'boolean',
            'e_nationality'      => 'required|string|size:2',
            'e_passport_number'  => 'nullable|required_if:e_is_foreign_national,true|string|max:30',
            'e_passport_expiry'  => 'nullable|date',
            'e_visa_number'      => 'nullable|required_if:e_is_foreign_national,true|string|max:30',
            'e_visa_expiry'      => 'nullable|date',
            'e_arrival_in_india' => 'nullable|date',
            'e_next_destination' => 'nullable|string|max:100',
        ]);

        $arrival = Carbon::parse($data['e_arrival_date']);
        $departure = Carbon::parse($data['e_departure_date']);
        $nights = $arrival->diffInDays($departure);

        DB::transaction(function () use ($data, $arrival, $departure, $nights) {
            $this->reservation->update([
                'guest_name' => $data['e_guest_name'],
                'guest_phone' => $data['e_guest_phone'] ?: null,
                'guest_email' => $data['e_guest_email'] ?: null,
                'arrival_date' => $arrival,
                'departure_date' => $departure,
                'nights' => $nights,
                'adults' => $data['e_adults'],
                'children' => $data['e_children'] ?? 0,
                'market_segment' => $data['e_market_segment'] ?: null,
                'source_type' => $data['e_source_type'],
                'source_name' => $data['e_source_name'] ?: null,
                'special_requests' => $data['e_special_requests'] ?: null,
                'internal_notes' => $data['e_internal_notes'] ?: null,
                'is_vip' => $data['e_is_vip'] ?? false,
            ]);

            // Sync foreign-national / Form-C fields to the Guest record.
            // These fields live on `guests`, not `reservations` — the Police
            // Register and Form-C lists filter on `guest.is_foreign_national`,
            // so without this sync they'd never see this guest.
            $guest = $this->reservation->guest;
            if (!$guest) {
                // Older reservations may have no guest_id. Create one now so
                // FRRO / police compliance has something to scope on.
                $parts = preg_split('/\s+/', trim($data['e_guest_name']), 2);
                $guest = Guest::create([
                    'tenant_id'  => $this->reservation->tenant_id,
                    'first_name' => $parts[0] ?? $data['e_guest_name'],
                    'last_name'  => $parts[1] ?? '',
                    'phone'      => $data['e_guest_phone'] ?: null,
                    'email'      => $data['e_guest_email'] ?: null,
                ]);
                $this->reservation->update(['guest_id' => $guest->id]);
            }
            $guest->update([
                'is_foreign_national' => (bool) ($data['e_is_foreign_national'] ?? false),
                'nationality'         => $data['e_nationality'] ?: 'IN',
                'passport_number'     => $data['e_passport_number'] ?: null,
                'passport_expiry'     => $data['e_passport_expiry'] ?: null,
                'visa_number'         => $data['e_visa_number'] ?: null,
                'visa_expiry'         => $data['e_visa_expiry'] ?: null,
                'arrival_in_india'    => $data['e_arrival_in_india'] ?: null,
                'next_destination'    => $data['e_next_destination'] ?: null,
            ]);

            // Update first reservation_room dates / room type
            $rRoom = $this->reservation->rooms()->first();
            if ($rRoom) {
                $rRoom->update([
                    'arrival_date' => $arrival,
                    'departure_date' => $departure,
                    'nights' => $nights,
                    'adults' => $data['e_adults'],
                    'children' => $data['e_children'] ?? 0,
                    'room_type_id' => $data['e_room_type_id'],
                    'guest_name' => $data['e_guest_name'],
                ]);
            }
        });

        $this->reservation->refresh()->load(['rooms.roomType','rooms.room','folios.charges','folios.payments']);
        $this->loadEditFromReservation();
        session()->flash('success', 'Reservation updated.');
    }

    public function postCharge(): void
    {
        // Categories must match the folio_charges.category MySQL enum (see
        // 2026_05_07_300002_extend_folio_charge_categories.php). 'extra_bed' and
        // 'package' were collapsed into 'misc' in that migration — keep the UI
        // and validation in sync or INSERT silently fails on partial-string.
        $this->validate([
            'chargeCategory' => 'required|in:food,beverage,laundry,mini_bar,spa,telephone,misc,damage,other',
            'chargeDescription' => 'required|string|max:255',
            'chargeRate' => 'required|numeric|min:0',
            'chargeQuantity' => 'required|numeric|min:0.001',
            'chargeTaxPercent' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $folio = $this->reservation->folios()->where('status', 'open')->first() ?? $this->openFolio();
            $amount = round($this->chargeRate * $this->chargeQuantity, 2);
            $tax = round($amount * ($this->chargeTaxPercent/100), 2);
            $netAmt = $amount + $tax;

            DB::transaction(function () use ($folio, $amount, $tax, $netAmt) {
                FolioCharge::create([
                    'tenant_id'=>$folio->tenant_id,'property_id'=>$folio->property_id,'folio_id'=>$folio->id,
                    'charge_date'=>today(),'charge_time'=>now()->format('H:i:s'),'business_date'=>today(),
                    'category'=>$this->chargeCategory,'description'=>$this->chargeDescription,
                    'reference'=>$this->chargeReference ?: null,
                    'quantity'=>$this->chargeQuantity,'rate'=>$this->chargeRate,
                    'amount'=>$amount,'tax_amount'=>$tax,'net_amount'=>$netAmt,
                    'tax_breakdown'=>['rate'=>$this->chargeTaxPercent,'amount'=>$tax],
                    'is_voided'=>false,'posted_by'=>auth()->id(),
                ]);
                $folio->update([
                    'total_charges'=>$folio->total_charges + $amount,
                    'total_taxes'=>$folio->total_taxes + $tax,
                    'balance'=>$folio->balance + $netAmt,
                ]);
                $this->reservation->update([
                    'total_amount'=>$this->reservation->total_amount + $netAmt,
                    'balance_amount'=>$this->reservation->balance_amount + $netAmt,
                ]);
            });

            $this->reset(['chargeDescription','chargeRate','chargeQuantity','chargeReference']);
            $this->chargeQuantity = 1; $this->chargeTaxPercent = 12;
            session()->flash('success', "Charge of ₹" . number_format($netAmt, 2) . " posted to folio.");
            $this->reservation->refresh()->load(['folios.charges']);
        } catch (\Throwable $e) {
            \Log::error('postCharge failed: ' . $e->getMessage(), ['reservation_id' => $this->reservation->id]);
            session()->flash('error', 'Could not post charge: ' . $e->getMessage());
        }
    }

    public function postPayment(): void
    {
        // Split-payment: each line is one Payment row. Cash + card + UPI in one settlement allowed.
        $valid = collect($this->payLines)->filter(fn($l)=>(float)($l['amount']??0) > 0);
        if ($valid->isEmpty()) {
            session()->flash('error', 'Add at least one payment line.');
            return;
        }
        $modes = ['cash','card','upi','bank_transfer','company_credit','wallet','cheque','advance_adjustment','gift_voucher','ota_collect'];

        $folio = $this->reservation->folios()->where('status', 'open')->first() ?? $this->openFolio();

        DB::transaction(function () use ($folio, $valid, $modes) {
            $totalPaid = 0;
            foreach ($valid as $line) {
                $mode = in_array($line['mode'] ?? '', $modes) ? $line['mode'] : 'cash';
                $amt = round((float)($line['amount'] ?? 0), 2);
                Payment::create([
                    'tenant_id'=>$folio->tenant_id,'property_id'=>$folio->property_id,
                    'folio_id'=>$folio->id,'reservation_id'=>$this->reservation->id,
                    'receipt_number'=>'RC-'.now()->format('ymdHis').'-'.rand(100,999),
                    'payment_date'=>today(),'business_date'=>today(),
                    'amount'=>$amt,'currency'=>'INR',
                    'mode'=>$mode,'transaction_reference'=>$line['reference'] ?? null,
                    'notes'=>$this->payNote ?: null,
                    'received_by'=>auth()->id(),'status'=>'completed',
                    'payable_type'=>\App\Models\Reservation::class,
                    'payable_id'=>$this->reservation->id,
                ]);
                $totalPaid += $amt;
            }
            $folio->update([
                'total_payments'=>$folio->total_payments + $totalPaid,
                'balance'=>$folio->balance - $totalPaid,
            ]);
            $this->reservation->update([
                'paid_amount'=>$this->reservation->paid_amount + $totalPaid,
                'balance_amount'=>max(0, $this->reservation->balance_amount - $totalPaid),
            ]);
        });

        $this->payNote = '';
        session()->flash('success', count($this->payLines).' payment line(s) recorded.');
        $this->reservation->refresh()->load(['folios.payments']);
        $this->resetPayLines();
    }

    public function voidCharge(int $chargeId): void
    {
        $charge = FolioCharge::findOrFail($chargeId);
        if ($charge->is_voided) return;
        DB::transaction(function () use ($charge) {
            $charge->update(['is_voided'=>true,'voided_at'=>now(),'voided_by'=>auth()->id(),'void_reason'=>'Voided by '.auth()->user()->name]);
            $folio = $charge->folio;
            $folio->update([
                'total_charges'=>max(0, $folio->total_charges - $charge->amount),
                'total_taxes'=>max(0, $folio->total_taxes - $charge->tax_amount),
                'balance'=>max(0, $folio->balance - $charge->net_amount),
            ]);
            $this->reservation->update([
                'total_amount'=>max(0, $this->reservation->total_amount - $charge->net_amount),
                'balance_amount'=>max(0, $this->reservation->balance_amount - $charge->net_amount),
            ]);
        });
        session()->flash('success', 'Charge voided.');
        $this->reservation->refresh()->load(['folios.charges']);
    }

    public function cancelReservation(): void
    {
        // Hard guard: a checked-in or checked-out reservation can't be cancelled.
        // For checked-in, the guest is physically in the room — use check-out instead.
        // For checked-out, the stay is complete — use Status tab → "no_show" / refund flow if needed.
        if (in_array($this->reservation->status, ['checked_in', 'checked_out'], true)) {
            session()->flash('error',
                $this->reservation->status === 'checked_in'
                    ? 'Guest is currently checked in. Check them out first if you need to end the stay.'
                    : 'This stay is already complete (checked out). Cancellation no longer applies.'
            );
            return;
        }

        $data = $this->validate([
            'cancelReason' => 'required|string|min:3|max:500',
            'cancellationCharge' => 'numeric|min:0',
        ]);

        DB::transaction(function () use ($data) {
            $this->reservation->update([
                'status' => 'cancelled',
                'status_changed_at' => now(),
                'status_changed_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $data['cancelReason'],
                'cancellation_charge' => $data['cancellationCharge'] ?? 0,
            ]);
            // Free room if any
            $rRoom = $this->reservation->rooms()->first();
            if ($rRoom?->room_id) {
                Room::where('id', $rRoom->room_id)->update(['fo_status'=>'vacant','status'=>'vacant_dirty']);
            }
            $this->reservation->rooms()->update(['status'=>'cancelled']);
            // Close any open folio
            $this->reservation->folios()->where('status','open')->update(['status'=>'voided','closed_at'=>now(),'closed_by'=>auth()->id()]);
        });

        $this->reset(['cancelReason']);
        $this->cancellationCharge = 0;
        session()->flash('success', 'Reservation cancelled.');
        $this->reservation->refresh()->load(['rooms.room']);
    }

    public function moveRoom(): void
    {
        $this->validate(['moveToRoomId' => 'required|exists:rooms,id']);
        $rRoom = $this->reservation->rooms()->first();
        if (!$rRoom) return;
        DB::transaction(function () use ($rRoom) {
            $oldRoomId = $rRoom->room_id;
            $rRoom->update(['room_id' => $this->moveToRoomId]);
            if ($oldRoomId) Room::where('id', $oldRoomId)->update(['status'=>'vacant_dirty','fo_status'=>'vacant']);
            Room::where('id', $this->moveToRoomId)->update(['status'=>'occupied_clean','fo_status'=>'occupied']);
        });
        $this->reset(['moveToRoomId']);
        session()->flash('success', 'Room moved.');
        $this->reservation->refresh()->load(['rooms.room']);
    }

    public function changeStatus(): void
    {
        $this->validate([
            'newStatus' => 'required|in:tentative,confirmed,checked_in,checked_out,cancelled,no_show',
            'statusNote' => 'nullable|string|max:500',
        ]);

        // Block destructive shortcuts. Going to checked_in here would skip
        // room assignment + folio creation + ID capture; checked_out would
        // skip payment collection + invoice generation. Force the cashier
        // through the proper flows.
        if ($this->newStatus === 'checked_in') {
            session()->flash('error',
                'Use Front Office → Check-in to assign a room, capture ID proof, and open a folio. ' .
                'Setting "checked_in" here would leave the reservation without a room and break later check-out.'
            );
            return;
        }
        if ($this->newStatus === 'checked_out') {
            session()->flash('error',
                'Use Front Office → Check-out to collect payment, settle the folio, and free the room. ' .
                'Setting "checked_out" here would leave the folio open with no payment recorded.'
            );
            return;
        }
        // Block setting checked_in/out backwards too — once a stay has begun, the
        // status_changed_at audit trail and folio state shouldn't be rewritten by
        // hand. Refunds / corrections are accounting flows, not status flips.
        if (in_array($this->reservation->status, ['checked_in','checked_out'], true)
            && $this->newStatus !== $this->reservation->status) {
            session()->flash('error',
                "Cannot change a {$this->reservation->status} reservation to {$this->newStatus} here. " .
                "If you need to undo a check-in/out, contact accounts — the folio + room state need correcting too."
            );
            return;
        }

        $this->reservation->update([
            'status' => $this->newStatus,
            'status_changed_at' => now(),
            'status_changed_by' => auth()->id(),
        ]);
        $this->reset(['newStatus','statusNote']);
        session()->flash('success', 'Status updated.');
        $this->reservation->refresh();
    }

    /**
     * Upload one or more ID-proof documents for this reservation's guest.
     * Files are stored under storage/app/public/id-proofs/{guest_id}/ and
     * appended to guests.id_proof_files (JSON array of paths).
     */
    public function uploadDocuments(): void
    {
        $this->validate([
            'documentUploads.*' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf,webp',
        ], [
            'documentUploads.*.max' => 'Each file must be 5 MB or smaller.',
            'documentUploads.*.mimes' => 'Only JPG, PNG, PDF, or WebP files are accepted.',
        ]);

        // Make sure there's a Guest record to attach to. Older reservations
        // may have guest_id = null — create a Guest from the denormalised name.
        $guest = $this->reservation->guest;
        if (!$guest) {
            $parts = preg_split('/\s+/', trim($this->reservation->guest_name), 2);
            $guest = Guest::create([
                'tenant_id'  => $this->reservation->tenant_id,
                'first_name' => $parts[0] ?? $this->reservation->guest_name,
                'last_name'  => $parts[1] ?? '',
                'phone'      => $this->reservation->guest_phone,
                'email'      => $this->reservation->guest_email,
            ]);
            $this->reservation->update(['guest_id' => $guest->id]);
        }

        try {
            $existing = (array) ($guest->id_proof_files ?? []);
            foreach ($this->documentUploads as $upload) {
                if (!$upload) continue;
                $path = $upload->store("public/id-proofs/{$guest->id}");
                $existing[] = str_replace('public/', '', $path);
            }
            $guest->update(['id_proof_files' => array_values(array_unique($existing))]);
            $this->documentUploads = [];
            session()->flash('success', 'Document(s) uploaded.');
            $this->reservation->refresh()->load('guest');
        } catch (\Throwable $e) {
            \Log::error('Document upload failed: ' . $e->getMessage());
            session()->flash('error', 'Could not upload: ' . $e->getMessage());
        }
    }

    /**
     * Delete a single uploaded file from storage and remove its path from
     * guests.id_proof_files. Path is matched against the stored array — paths
     * not in the array are ignored (defensive).
     */
    public function deleteDocument(string $path): void
    {
        $guest = $this->reservation->guest;
        if (!$guest) return;

        $existing = (array) ($guest->id_proof_files ?? []);
        if (!in_array($path, $existing, true)) {
            session()->flash('error', 'That document is not on this guest\'s record.');
            return;
        }

        try {
            // Remove the underlying file from disk; ignore if already missing.
            Storage::disk('public')->delete($path);
            $remaining = array_values(array_filter($existing, fn ($p) => $p !== $path));
            $guest->update(['id_proof_files' => $remaining]);
            session()->flash('success', 'Document removed.');
            $this->reservation->refresh()->load('guest');
        } catch (\Throwable $e) {
            \Log::error('Document delete failed: ' . $e->getMessage());
            session()->flash('error', 'Could not delete: ' . $e->getMessage());
        }
    }

    private function openFolio(): Folio
    {
        $rRoom = $this->reservation->rooms()->first();
        return Folio::create([
            'tenant_id'=>$this->reservation->tenant_id,'property_id'=>$this->reservation->property_id,
            'reservation_id'=>$this->reservation->id,'reservation_room_id'=>$rRoom?->id,
            'folio_number'=>'F-'.$this->reservation->reservation_number,'type'=>'guest',
            'guest_id'=>$this->reservation->guest_id,'billing_name'=>$this->reservation->guest_name,
            'total_charges'=>$this->reservation->room_revenue,'total_taxes'=>$this->reservation->total_tax,
            'total_payments'=>$this->reservation->paid_amount,'balance'=>$this->reservation->balance_amount,
            'currency'=>'INR','status'=>'open',
        ]);
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $folios = $this->reservation->folios()->with(['charges' => fn ($q) => $q->orderBy('charge_date'), 'payments'])->get();

        $availableRooms = collect();
        if ($this->tab === 'stay') {
            $rRoom = $this->reservation->rooms()->first();
            $availableRooms = Room::where('property_id', $ctx->propertyId())
                ->whereIn('status', ['vacant_clean','inspected'])
                ->when($rRoom?->room_type_id, fn ($q, $rt) => $q->where('room_type_id', $rt))
                ->orderBy('number')->get();
        }
        $roomTypes = RoomType::where('property_id', $ctx->propertyId())->where('is_active', true)->get();

        return view('livewire.reservations.reservation-detail', compact('folios','availableRooms','roomTypes'));
    }
}
