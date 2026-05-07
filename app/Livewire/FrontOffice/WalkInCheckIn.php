<?php

namespace App\Livewire\FrontOffice;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tax;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app-shell')]
class WalkInCheckIn extends Component
{
    use WithFileUploads;

    public string $guest_name = '';
    public string $guest_phone = '';
    public string $guest_email = '';
    public string $id_type = 'aadhaar';
    public string $id_number = '';
    public string $address = '';

    // ID proof file uploads — front, back, selfie etc. Multi-file Livewire upload.
    public array $idProofUploads = [];

    // Foreign-national / FRRO fields
    public bool $is_foreign_national = false;
    public string $nationality = 'IN';
    public string $passport_number = '';
    public ?string $passport_expiry = null;
    public string $visa_number = '';
    public ?string $visa_expiry = null;
    public string $arrival_from_country = '';
    public ?string $arrival_date_in_india = null;
    public string $next_destination_country = '';
    public string $next_destination = '';

    public ?int $room_type_id = null;
    public ?int $rate_plan_id = null;
    public ?int $room_id = null;
    public string $arrival_date;
    public string $departure_date;
    public int $adults = 1;
    public int $children = 0;

    public string $payMode = 'cash';
    public float $advance = 0;
    public string $special_requests = '';

    // Coupon state
    public string $couponCode = '';
    public ?int $appliedPromotionId = null;
    public string $couponMessage = '';

    public function getAppliedPromotionProperty(): ?Promotion
    {
        if (!$this->appliedPromotionId) return null;
        return Promotion::find($this->appliedPromotionId);
    }

    public function mount(): void
    {
        $this->arrival_date   = today()->toDateString();
        $this->departure_date = today()->addDay()->toDateString();
    }

    public function updatedRoomTypeId(): void
    {
        $this->room_id = null;
        $this->rate_plan_id = null;
    }

    public function pricingBreakdown(): array
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();
        $nights = $this->computedNights();

        if (!$this->room_type_id || !$property) {
            return [
                'nightly' => 0.0, 'tax_pct' => 0.0, 'subtotal' => 0.0,
                'tax_total' => 0.0, 'discount' => 0.0, 'total' => 0.0,
                'tax_mode' => 'exclusive', 'displayed_rate' => 0.0,
                'nights' => $nights, 'rooms_count' => 1,
            ];
        }

        $roomType = RoomType::find($this->room_type_id);
        if (!$roomType) {
            return [
                'nightly' => 0.0, 'tax_pct' => 0.0, 'subtotal' => 0.0,
                'tax_total' => 0.0, 'discount' => 0.0, 'total' => 0.0,
                'tax_mode' => 'exclusive', 'displayed_rate' => 0.0,
                'nights' => $nights, 'rooms_count' => 1,
            ];
        }

        $ratePlan = $this->rate_plan_id ? RatePlan::find($this->rate_plan_id) : null;
        $taxMode = $ratePlan?->tax_mode ?? 'exclusive';

        $displayedRate = $ratePlan
            ? $ratePlan->effectiveRate($roomType)
            : (float) $roomType->base_rate;

        $taxPct = $displayedRate <= 7500 ? 12.0 : 18.0;
        $taxRow = Tax::where('property_id', $property->id)
            ->where('is_active', true)
            ->where('applies_to_room', true)
            ->where(function ($q) use ($displayedRate) {
                $q->whereNull('threshold_min')->orWhere('threshold_min', '<=', $displayedRate);
            })
            ->where(function ($q) use ($displayedRate) {
                $q->whereNull('threshold_max')->orWhere('threshold_max', '>=', $displayedRate);
            })
            ->first();
        if ($taxRow) {
            $taxPct = (float) $taxRow->rate;
        }

        if ($taxMode === 'inclusive') {
            $exTaxNightly = $displayedRate / (1 + $taxPct / 100);
            $perNightTax  = $displayedRate - $exTaxNightly;
        } else {
            $exTaxNightly = $displayedRate;
            $perNightTax  = $displayedRate * ($taxPct / 100);
        }

        $subtotal = round($exTaxNightly * $nights, 2);
        $taxTotal = round($perNightTax * $nights, 2);

        $discount = $this->computeDiscount($subtotal, $nights, $exTaxNightly);

        $total = round($subtotal + $taxTotal - $discount, 2);
        if ($total < 0) { $total = 0.0; }

        return [
            'nightly'        => round($exTaxNightly, 2),
            'tax_pct'        => $taxPct,
            'subtotal'       => $subtotal,
            'tax_total'      => $taxTotal,
            'discount'       => round($discount, 2),
            'total'          => $total,
            'tax_mode'       => $taxMode,
            'displayed_rate' => round($displayedRate, 2),
            'nights'         => $nights,
            'rooms_count'    => 1,
        ];
    }

    private function computedNights(): int
    {
        try {
            $a = Carbon::parse($this->arrival_date);
            $d = Carbon::parse($this->departure_date);
            return max(1, $a->diffInDays($d));
        } catch (\Throwable) {
            return 1;
        }
    }

    private function computeDiscount(float $subtotal, int $nights, float $perNightExTax): float
    {
        if (!$this->appliedPromotion) {
            return 0.0;
        }

        $p = $this->appliedPromotion;
        $value = (float) $p->value;

        return match ($p->type) {
            'percentage'  => min($subtotal, round($subtotal * $value / 100, 2)),
            'flat_amount' => min($subtotal, $value),
            'free_night'  => min($subtotal, round(min($value, $nights) * $perNightExTax, 2)),
            default       => 0.0,
        };
    }

    public function applyCoupon(): void
    {
        $this->couponMessage = '';
        $this->appliedPromotionId = null;

        $code = strtoupper(trim($this->couponCode));
        if ($code === '') {
            $this->couponMessage = 'Enter a coupon code.';
            return;
        }

        $ctx = app(TenantContext::class);
        $property = $ctx->property();
        if (!$property) {
            $this->couponMessage = 'Property context missing.';
            return;
        }

        $promo = Promotion::where('property_id', $property->id)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->first();

        if (!$promo || !$promo->isCurrentlyValid()) {
            $this->couponMessage = 'Invalid or expired code';
            return;
        }

        $nights = $this->computedNights();
        // appliedPromotion is null here, so this is the pre-discount subtotal.
        $breakdown = $this->pricingBreakdown();
        $subtotalBeforeDiscount = (float) $breakdown['subtotal'];

        if ($nights < (int) ($promo->min_nights ?? 1)) {
            $this->couponMessage = "Minimum {$promo->min_nights} night(s) required for this code.";
            return;
        }
        if ($subtotalBeforeDiscount < (float) ($promo->min_amount ?? 0)) {
            $this->couponMessage = 'Order total too low for this code.';
            return;
        }

        $advance = (int) ($promo->advance_days ?? 0);
        if ($advance > 0) {
            try {
                $arrival = Carbon::parse($this->arrival_date);
                if (now()->startOfDay()->diffInDays($arrival, false) < $advance) {
                    $this->couponMessage = "Must be booked at least {$advance} day(s) in advance.";
                    return;
                }
            } catch (\Throwable) {}
        }

        if ($promo->max_uses !== null && $promo->used_count >= $promo->max_uses) {
            $this->couponMessage = 'This code has reached its usage limit.';
            return;
        }

        $this->appliedPromotionId = $promo->id;

        if (in_array($promo->type, ['package_upgrade', 'complimentary_addon'], true)) {
            $this->couponMessage = "{$promo->code} accepted - will be applied at check-in.";
        } else {
            $this->couponMessage = "{$promo->code} applied.";
        }
    }

    public function removeCoupon(): void
    {
        $this->couponCode = '';
        $this->appliedPromotionId = null;
        $this->couponMessage = '';
    }

    public function checkIn()
    {
        $data = $this->validate([
            'guest_name'      => 'required|string|min:2|max:255',
            'guest_phone'     => 'required|string|max:30',
            'guest_email'     => 'nullable|email',
            'id_type'         => 'required|in:aadhaar,passport,voter,driving_license,pan,other',
            'id_number'       => 'required|string|max:50',
            'address'         => 'nullable|string|max:500',
            'idProofUploads.*'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'is_foreign_national'      => 'boolean',
            'nationality'              => 'nullable|string|max:2',
            'passport_number'          => 'nullable|required_if:is_foreign_national,true|string|max:30',
            'passport_expiry'          => 'nullable|date',
            'visa_number'              => 'nullable|required_if:is_foreign_national,true|string|max:30',
            'visa_expiry'              => 'nullable|date',
            'arrival_from_country'     => 'nullable|string|max:2',
            'arrival_date_in_india'    => 'nullable|date',
            'next_destination_country' => 'nullable|string|max:2',
            'next_destination'         => 'nullable|string|max:255',
            'room_type_id'    => 'required|exists:room_types,id',
            'rate_plan_id'    => 'nullable|exists:rate_plans,id',
            'room_id'         => 'required|exists:rooms,id',
            'arrival_date'    => 'required|date',
            'departure_date'  => 'required|date|after:arrival_date',
            'adults'          => 'required|integer|min:1|max:10',
            'children'        => 'integer|min:0|max:10',
            'payMode'         => 'required|in:cash,card,upi,bank_transfer,company_credit',
            'advance'         => 'numeric|min:0',
        ]);

        $ctx = app(TenantContext::class);
        $tenant = $ctx->tenant();
        $property = $ctx->property();

        $arrival   = Carbon::parse($data['arrival_date']);
        $departure = Carbon::parse($data['departure_date']);
        $nights    = $arrival->diffInDays($departure);

        $roomType = RoomType::findOrFail($data['room_type_id']);

        $b = $this->pricingBreakdown();
        $rate         = (float) $b['nightly'];        // pre-tax per-night
        $roomRevenue  = (float) $b['subtotal'];
        $tax          = (float) $b['tax_total'];
        $discount     = (float) $b['discount'];
        $total        = (float) $b['total'];

        DB::transaction(function () use ($data, $tenant, $property, $arrival, $departure, $nights, $roomType, $rate, $roomRevenue, $tax, $discount, $total) {
            $parts = preg_split('/\s+/', trim($data['guest_name']), 2);
            $guest = Guest::create([
                'tenant_id'           => $tenant->id,
                'first_name'          => $parts[0] ?? $data['guest_name'],
                'last_name'           => $parts[1] ?? '',
                'phone'               => $data['guest_phone'],
                'email'               => $data['guest_email'] ?: null,
                'id_type'             => $data['id_type'],
                'id_number'           => $data['id_number'],
                'address'             => $data['address'] ?: null,
                'segment'             => 'walk_in',
                'is_foreign_national' => $data['is_foreign_national'] ?? false,
                'nationality'         => $data['nationality'] ?: 'IN',
                'passport_number'     => $data['passport_number'] ?: null,
                'passport_expiry'     => $data['passport_expiry'] ?: null,
                'visa_number'         => $data['visa_number'] ?: null,
                'visa_expiry'         => $data['visa_expiry'] ?: null,
                'arrival_from_country'    => $data['arrival_from_country'] ?: null,
                'arrival_date_in_india'   => $data['arrival_date_in_india'] ?: null,
                'next_destination_country' => $data['next_destination_country'] ?: null,
                'next_destination'    => $data['next_destination'] ?: null,
            ]);

            // Persist ID proof uploads (front/back/selfie) under storage/app/public/id-proofs/{guest_id}/
            $stored = [];
            foreach ($this->idProofUploads ?? [] as $upload) {
                if ($upload) {
                    $path = $upload->store("public/id-proofs/{$guest->id}");
                    $stored[] = str_replace('public/', '', $path);
                }
            }
            if (!empty($stored)) {
                $guest->update(['id_proof_files' => $stored]);
            }

            $resNumber = 'RES-'.now()->format('ymd').'-'.str_pad((string) (Reservation::where('property_id', $property->id)->count() + 1), 4, '0', STR_PAD_LEFT);

            $reservation = Reservation::create([
                'tenant_id'           => $tenant->id,
                'property_id'         => $property->id,
                'reservation_number'  => $resNumber,
                'confirmation_number' => 'CONF-'.strtoupper(substr(md5(uniqid()),0,8)),
                'guest_id'            => $guest->id,
                'guest_name'          => $data['guest_name'],
                'guest_phone'         => $data['guest_phone'],
                'guest_email'         => $data['guest_email'] ?: null,
                'source_type'         => 'walk_in',
                'source_name'         => 'Front desk',
                'arrival_date'        => $arrival,
                'arrival_time'        => now()->format('H:i:s'),
                'departure_date'      => $departure,
                'nights'              => $nights,
                'rooms_count'         => 1,
                'adults'              => $data['adults'],
                'children'            => $data['children'] ?? 0,
                'status'              => 'checked_in',
                'status_changed_at'   => now(),
                'status_changed_by'   => auth()->id(),
                'room_revenue'        => $roomRevenue,
                'total_tax'           => $tax,
                'total_discount'      => $discount,
                'total_amount'        => $total,
                'paid_amount'         => $data['advance'] ?? 0,
                'balance_amount'      => max(0, $total - ($data['advance'] ?? 0)),
                'currency'            => 'INR',
                'special_requests'    => $this->special_requests ?: null,
                'created_by'          => auth()->id(),
            ]);

            $rRoom = ReservationRoom::create([
                'tenant_id'      => $tenant->id,
                'property_id'    => $property->id,
                'reservation_id' => $reservation->id,
                'room_type_id'   => $roomType->id,
                'rate_plan_id'   => $data['rate_plan_id'] ?? null,
                'room_id'        => $data['room_id'],
                'arrival_date'   => $arrival,
                'departure_date' => $departure,
                'nights'         => $nights,
                'adults'         => $data['adults'],
                'children'       => $data['children'] ?? 0,
                'guest_name'     => $data['guest_name'],
                'guest_id'       => $guest->id,
                'average_rate'   => $rate,
                'total_rate'     => $roomRevenue,
                'total_tax'      => $tax,
                'total_amount'   => $total,
                'status'         => 'checked_in',
                'checked_in_at'  => now(),
                'checked_in_by'  => auth()->id(),
            ]);

            Room::where('id', $data['room_id'])->update([
                'status'    => 'occupied_clean',
                'fo_status' => 'occupied',
            ]);

            $folio = Folio::create([
                'tenant_id'      => $tenant->id,
                'property_id'    => $property->id,
                'reservation_id' => $reservation->id,
                'reservation_room_id' => $rRoom->id,
                'folio_number'   => 'F-'.$reservation->reservation_number,
                'type'           => 'guest',
                'guest_id'       => $guest->id,
                'billing_name'   => $data['guest_name'],
                'total_charges'  => $roomRevenue,
                'total_taxes'    => $tax,
                'total_payments' => $data['advance'] ?? 0,
                'balance'        => $total - ($data['advance'] ?? 0),
                'currency'       => 'INR',
                'status'         => 'open',
            ]);

            if (($data['advance'] ?? 0) > 0) {
                \App\Models\Payment::create([
                    'tenant_id'      => $tenant->id,
                    'property_id'    => $property->id,
                    'folio_id'       => $folio->id,
                    'reservation_id' => $reservation->id,
                    'receipt_number' => 'RC-'.now()->format('ymdHis'),
                    'payment_date'   => today(),
                    'business_date'  => today(),
                    'amount'         => $data['advance'],
                    'currency'       => 'INR',
                    'mode'           => $data['payMode'],
                    'received_by'    => auth()->id(),
                    'status'         => 'completed',
                    'payable_type'   => \App\Models\Reservation::class,
                    'payable_id'     => $reservation->id,
                ]);
            }

            if ($this->appliedPromotionId) {
                $promo = Promotion::find($this->appliedPromotionId);
                if ($promo) {
                    $promo->increment('used_count');
                    PromotionRedemption::create([
                        'tenant_id'       => $tenant->id,
                        'property_id'     => $property->id,
                        'promotion_id'    => $promo->id,
                        'reservation_id'  => $reservation->id,
                        'guest_id'        => $guest->id,
                        'discount_amount' => $discount,
                        'redeemed_at'     => now(),
                        'redeemed_by'     => auth()->id(),
                    ]);
                }
            }

            session()->flash('success', "{$data['guest_name']} checked in to room ".Room::find($data['room_id'])?->number);
            $this->redirect(route('reservations.show', $reservation));
        });
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $roomTypes = RoomType::where('property_id', $propertyId)->where('is_active', true)->orderBy('base_rate')->get();
        $ratePlans = $this->room_type_id
            ? RatePlan::where('property_id', $propertyId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('room_type_id', $this->room_type_id);
                })
                ->get()
            : collect();
        $rooms = $this->room_type_id
            ? Room::where('property_id', $propertyId)
                ->where('room_type_id', $this->room_type_id)
                ->whereIn('status', ['vacant_clean','inspected'])
                ->where('is_active', true)
                ->orderBy('number')->get()
            : collect();

        return view('livewire.front-office.walk-in-check-in', [
            'roomTypes' => $roomTypes,
            'ratePlans' => $ratePlans,
            'rooms'     => $rooms,
            'pricing'   => $this->pricingBreakdown(),
            'appliedPromotion' => $this->appliedPromotion,
        ]);
    }
}
