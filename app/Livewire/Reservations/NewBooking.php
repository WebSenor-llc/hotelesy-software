<?php

namespace App\Livewire\Reservations;

use App\Models\Guest;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use App\Models\Tax;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class NewBooking extends Component
{
    public string $guest_name = '';
    public string $guest_phone = '';
    public string $guest_email = '';

    // ID proof — captured at booking so check-in is friction-free.
    // Document files are uploaded later at check-in (see CheckIn flow).
    public string $id_type = 'aadhaar';
    public string $id_number = '';

    // Foreign-national / FRRO Form-C fields. Section 14 of the Foreigners Act, 1946
    // and Form C requirements. Hotels MUST report foreign guests within 24 hours.
    public bool $is_foreign_national = false;
    public string $nationality = 'IN';
    public string $passport_number = '';
    public ?string $passport_expiry = null;
    public string $visa_number = '';
    public ?string $visa_expiry = null;
    public string $arrival_from_country = '';
    public ?string $arrival_date_in_india = null;
    public string $next_destination = '';

    public ?int $room_type_id = null;
    public ?int $rate_plan_id = null;
    public string $arrival_date;
    public string $departure_date;
    public int $adults = 2;
    public int $children = 0;
    public int $rooms_count = 1;
    public string $special_requests = '';

    // Coupon state
    public string $couponCode = '';
    public ?int $appliedPromotionId = null;
    public string $couponMessage = '';

    /** Convenience accessor used in this component and the view. */
    public function getAppliedPromotionProperty(): ?Promotion
    {
        if (!$this->appliedPromotionId) return null;
        return Promotion::find($this->appliedPromotionId);
    }

    public function mount(): void
    {
        $this->arrival_date   = Carbon::tomorrow()->toDateString();
        $this->departure_date = Carbon::tomorrow()->addDays(2)->toDateString();
    }

    public function rules(): array
    {
        return [
            'guest_name'      => 'required|string|min:2|max:255',
            'guest_phone'     => 'nullable|string|max:30',
            'guest_email'     => 'nullable|email',
            'id_type'         => 'required|in:aadhaar,passport,voter,driving_license,pan,other',
            'id_number'       => 'nullable|string|max:50',
            // Foreign-national fields are required only when the toggle is on.
            // Form C / FRRO requires passport + visa for non-Indian guests.
            'is_foreign_national'      => 'boolean',
            'nationality'              => 'required|string|size:2',
            'passport_number'          => 'nullable|required_if:is_foreign_national,true|string|max:30',
            'passport_expiry'          => 'nullable|date',
            'visa_number'              => 'nullable|required_if:is_foreign_national,true|string|max:30',
            'visa_expiry'              => 'nullable|date',
            'arrival_from_country'     => 'nullable|string|max:100',
            'arrival_date_in_india'    => 'nullable|date',
            'next_destination'         => 'nullable|string|max:100',
            'room_type_id'    => 'required|exists:room_types,id',
            'rate_plan_id'    => 'nullable|exists:rate_plans,id',
            'arrival_date'    => 'required|date|after_or_equal:today',
            'departure_date'  => 'required|date|after:arrival_date',
            'adults'          => 'required|integer|min:1|max:10',
            'children'        => 'integer|min:0|max:10',
            'rooms_count'     => 'required|integer|min:1|max:10',
            'special_requests'=> 'nullable|string|max:500',
        ];
    }

    /**
     * Resolve nightly rate, tax %, tax amount and pre-tax revenue
     * given the current room type / rate plan / nights / rooms_count selection.
     * Honors RatePlan->tax_mode (inclusive vs exclusive).
     */
    public function pricingBreakdown(): array
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();
        $nights = $this->computedNights();
        $roomsCount = max(1, (int) $this->rooms_count);

        if (!$this->room_type_id || !$property) {
            return [
                'nightly'       => 0.0,
                'tax_pct'       => 0.0,
                'subtotal'      => 0.0,    // pre-tax (room revenue, ex-tax)
                'tax_total'     => 0.0,
                'discount'      => 0.0,
                'total'         => 0.0,
                'tax_mode'      => 'exclusive',
                'displayed_rate'=> 0.0,    // what the user sees per night
                'nights'        => $nights,
                'rooms_count'   => $roomsCount,
            ];
        }

        $roomType = RoomType::find($this->room_type_id);
        if (!$roomType) {
            return [
                'nightly'       => 0.0, 'tax_pct' => 0.0, 'subtotal' => 0.0,
                'tax_total'     => 0.0, 'discount' => 0.0, 'total' => 0.0,
                'tax_mode'      => 'exclusive', 'displayed_rate' => 0.0,
                'nights'        => $nights, 'rooms_count' => $roomsCount,
            ];
        }

        $ratePlan = $this->rate_plan_id ? RatePlan::find($this->rate_plan_id) : null;
        $taxMode = $ratePlan?->tax_mode ?? 'exclusive';

        // Displayed rate per night (what a user would compare across plans).
        $displayedRate = $ratePlan
            ? $ratePlan->effectiveRate($roomType)
            : (float) $roomType->base_rate;

        // GST slab: ≤ ₹7500 → 12 %, > ₹7500 → 18 %. Slab is decided on
        // the displayed (pre-discount) rate. Property tax row may override.
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

        // Split into pre-tax nightly rate vs per-night tax depending on mode.
        if ($taxMode === 'inclusive') {
            // Displayed rate is gross. Extract embedded tax.
            $exTaxNightly = $displayedRate / (1 + $taxPct / 100);
            $perNightTax  = $displayedRate - $exTaxNightly;
        } else {
            // Exclusive (default): tax is added on top.
            $exTaxNightly = $displayedRate;
            $perNightTax  = $displayedRate * ($taxPct / 100);
        }

        $subtotal = round($exTaxNightly * $nights * $roomsCount, 2);
        $taxTotal = round($perNightTax * $nights * $roomsCount, 2);

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
            'rooms_count'    => $roomsCount,
        ];
    }

    private function computedNights(): int
    {
        try {
            $a = Carbon::parse($this->arrival_date);
            $d = Carbon::parse($this->departure_date);
            $n = $a->diffInDays($d);
            return max(1, $n);
        } catch (\Throwable) {
            return 1;
        }
    }

    /**
     * Discount value for the applied promotion (in subtotal currency).
     * Non-monetary types (package_upgrade, complimentary_addon) don't deduct.
     */
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
            'free_night'  => min(
                $subtotal,
                round(min($value, $nights) * $perNightExTax * max(1, (int) $this->rooms_count), 2)
            ),
            // Non-monetary - applied at check-in
            default => 0.0,
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

        // advance_days: arrival must be at least N days from today.
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

    public function save()
    {
        $data = $this->validate();
        $ctx = app(TenantContext::class);
        $tenant = $ctx->tenant();
        $property = $ctx->property();
        abort_unless($tenant && $property, 422, 'Tenant/property context missing.');

        $arrival   = Carbon::parse($data['arrival_date']);
        $departure = Carbon::parse($data['departure_date']);
        $nights    = $arrival->diffInDays($departure);

        $roomType = RoomType::findOrFail($data['room_type_id']);

        $b = $this->pricingBreakdown();
        $nightlyExTax = (float) $b['nightly'];
        $roomRevenue  = (float) $b['subtotal'];
        $totalTax     = (float) $b['tax_total'];
        $discount     = (float) $b['discount'];
        $totalAmount  = (float) $b['total'];

        $resNumber = 'RES-'.now()->format('ymd').'-'.str_pad((string) (Reservation::where('property_id', $property->id)->count() + 1), 4, '0', STR_PAD_LEFT);

        $reservation = DB::transaction(function () use (
            $tenant, $property, $data, $roomType,
            $arrival, $departure, $nights,
            $nightlyExTax, $roomRevenue, $totalTax, $discount, $totalAmount, $resNumber
        ) {
            // Create or reuse a Guest record so FRRO / Form-C / police-register
            // queries have something to scope on. We dedupe loosely by phone+name
            // within tenant — exact dedup is later (CRM merge).
            $guest = null;
            if (!empty(trim($data['guest_name']))) {
                $parts = preg_split('/\s+/', trim($data['guest_name']), 2);
                $guest = Guest::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'phone'     => $data['guest_phone'] ?: null,
                        'first_name'=> $parts[0] ?? $data['guest_name'],
                    ],
                    [
                        'last_name' => $parts[1] ?? '',
                        'email'     => $data['guest_email'] ?: null,
                    ]
                );
                // Always update compliance fields on the Guest — these may have
                // been entered/changed since the guest was first created.
                $guest->update(array_filter([
                    'id_type'                  => $data['id_type'] ?? null,
                    'id_number'                => $data['id_number'] ?: null,
                    'nationality'              => ($data['nationality'] ?? null) ?: 'IN',
                    'is_foreign_national'      => (bool) ($data['is_foreign_national'] ?? false),
                    'passport_number'          => $data['passport_number'] ?: null,
                    'passport_expiry'          => $data['passport_expiry'] ?: null,
                    'visa_number'              => $data['visa_number'] ?: null,
                    'visa_expiry'              => $data['visa_expiry'] ?: null,
                    'arrival_in_india'         => $data['arrival_date_in_india'] ?: null,
                    'next_destination'         => $data['next_destination'] ?: null,
                ], fn($v) => $v !== null && $v !== ''));
            }

            $reservation = Reservation::create([
                'tenant_id'           => $tenant->id,
                'property_id'         => $property->id,
                'reservation_number'  => $resNumber,
                'confirmation_number' => 'CONF-'.strtoupper(substr(md5(uniqid()), 0, 8)),
                'guest_id'            => $guest?->id,
                'guest_name'          => $data['guest_name'],
                'guest_phone'         => $data['guest_phone'] ?? null,
                'guest_email'         => $data['guest_email'] ?? null,
                'source_type'         => 'walk_in',
                'source_name'         => 'Front desk',
                'arrival_date'        => $arrival,
                'departure_date'      => $departure,
                'nights'              => $nights,
                'rooms_count'         => $data['rooms_count'],
                'adults'              => $data['adults'],
                'children'            => $data['children'] ?? 0,
                'status'              => Reservation::STATUS_CONFIRMED,
                'status_changed_at'   => now(),
                'room_revenue'        => $roomRevenue,
                'total_tax'           => $totalTax,
                'total_discount'      => $discount,
                'total_amount'        => $totalAmount,
                'paid_amount'         => 0,
                'balance_amount'      => $totalAmount,
                'currency'            => 'INR',
                'special_requests'    => $data['special_requests'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            ReservationRoom::create([
                'tenant_id'      => $tenant->id,
                'property_id'    => $property->id,
                'reservation_id' => $reservation->id,
                'room_type_id'   => $roomType->id,
                'rate_plan_id'   => $data['rate_plan_id'] ?? null,
                'arrival_date'   => $arrival,
                'departure_date' => $departure,
                'nights'         => $nights,
                'adults'         => $data['adults'],
                'children'       => $data['children'] ?? 0,
                'guest_name'     => $data['guest_name'],
                'average_rate'   => $nightlyExTax,
                'total_rate'     => $roomRevenue,
                'total_tax'      => $totalTax,
                'total_amount'   => $totalAmount,
                'status'         => 'booked',
            ]);

            // Promotion redemption
            if ($this->appliedPromotionId) {
                $promo = Promotion::find($this->appliedPromotionId);
                if ($promo) {
                    $promo->increment('used_count');
                    PromotionRedemption::create([
                        'tenant_id'       => $tenant->id,
                        'property_id'     => $property->id,
                        'promotion_id'    => $promo->id,
                        'reservation_id'  => $reservation->id,
                        'discount_amount' => $discount,
                        'redeemed_at'     => now(),
                        'redeemed_by'     => auth()->id(),
                    ]);
                }
            }

            return $reservation;
        });

        session()->flash('success', "Reservation {$resNumber} created.");
        return redirect()->route('reservations.index');
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        return view('livewire.reservations.new-booking', [
            'roomTypes' => RoomType::where('property_id', $propertyId)->where('is_active', true)->orderBy('base_rate')->get(),
            'ratePlans' => RatePlan::where('property_id', $propertyId)->where('is_active', true)->get(),
            'pricing'   => $this->pricingBreakdown(),
            'appliedPromotion' => $this->appliedPromotion,
        ]);
    }
}
