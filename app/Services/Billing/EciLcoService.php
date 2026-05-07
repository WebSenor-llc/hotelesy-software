<?php

namespace App\Services\Billing;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

/**
 * Computes & posts Early-Check-In (ECI) / Late-Check-Out (LCO) charges
 * to the guest folio at the moment they actually happen.
 *
 * Fee bands (per property settings):
 *   delta ≤ grace_hours                          → free
 *   grace < delta ≤ half_day_threshold_hours     → half_day_pct % of nightly
 *   delta > half_day_threshold_hours             → full_day_pct % of nightly
 *
 * delta = (standard_check_in_time - actual_check_in_at) for ECI
 * delta = (actual_check_out_at - standard_check_out_time) for LCO
 *
 * Idempotent: skips if reservation already has a charge of this kind.
 */
class EciLcoService
{
    public function applyEarlyCheckIn(Reservation $reservation, ?Carbon $actualCheckInAt = null): array
    {
        $property = Property::find($reservation->property_id);
        if (! $property) return ['kind' => 'none', 'amount' => 0];

        $actualCheckInAt = $actualCheckInAt ?: now();

        // Standard check-in time on the arrival date
        $standardEciCutoff = $this->combine($reservation->arrival_date, $property->check_in_time ?: '14:00');
        if (! $standardEciCutoff || $actualCheckInAt->gte($standardEciCutoff)) {
            return ['kind' => 'none', 'amount' => 0]; // Not early
        }

        $hoursEarly = $actualCheckInAt->diffInMinutes($standardEciCutoff) / 60;
        $grace      = (int) ($property->eci_grace_hours ?? 2);
        $halfTh     = (int) ($property->eci_half_day_threshold_hours ?? 6);
        $halfPct    = (int) ($property->eci_half_day_pct ?? 50);
        $fullPct    = (int) ($property->eci_full_day_pct ?? 100);

        if ($hoursEarly <= $grace) {
            return $this->markZero($reservation, 'eci', 'grace');
        }
        if ($hoursEarly <= $halfTh) {
            $kind = 'half_day';
            $pct  = $halfPct;
        } else {
            $kind = 'full_day';
            $pct  = $fullPct;
        }

        return $this->postCharge(
            reservation: $reservation,
            type: 'eci',
            kind: $kind,
            pct: $pct,
            description: "Early check-in fee — checked in " . round($hoursEarly, 1) . "h early",
            referenceLabel: 'ECI'
        );
    }

    public function applyLateCheckOut(Reservation $reservation, ?Carbon $actualCheckOutAt = null): array
    {
        $property = Property::find($reservation->property_id);
        if (! $property) return ['kind' => 'none', 'amount' => 0];

        $actualCheckOutAt = $actualCheckOutAt ?: now();

        $standardLcoCutoff = $this->combine($reservation->departure_date, $property->check_out_time ?: '12:00');
        if (! $standardLcoCutoff || $actualCheckOutAt->lte($standardLcoCutoff)) {
            return ['kind' => 'none', 'amount' => 0]; // Not late
        }

        $hoursLate = $standardLcoCutoff->diffInMinutes($actualCheckOutAt) / 60;
        $grace     = (int) ($property->lco_grace_hours ?? 2);
        $halfTh    = (int) ($property->lco_half_day_threshold_hours ?? 6);
        $halfPct   = (int) ($property->lco_half_day_pct ?? 50);
        $fullPct   = (int) ($property->lco_full_day_pct ?? 100);

        if ($hoursLate <= $grace) {
            return $this->markZero($reservation, 'lco', 'grace');
        }
        if ($hoursLate <= $halfTh) {
            $kind = 'half_day';
            $pct  = $halfPct;
        } else {
            $kind = 'full_day';
            $pct  = $fullPct;
        }

        return $this->postCharge(
            reservation: $reservation,
            type: 'lco',
            kind: $kind,
            pct: $pct,
            description: "Late check-out fee — checked out " . round($hoursLate, 1) . "h late",
            referenceLabel: 'LCO'
        );
    }

    /**
     * Post a no-show fee for a reservation that didn't arrive.
     * Idempotent — skips if no_show_fee_amount > 0 already.
     */
    public function applyNoShow(Reservation $reservation): array
    {
        if (($reservation->no_show_fee_amount ?? 0) > 0) {
            return ['kind' => 'already_charged', 'amount' => (float) $reservation->no_show_fee_amount];
        }
        $property = Property::find($reservation->property_id);
        $pct = (int) ($property?->noshow_fee_pct_first_night ?? 100);
        if ($pct <= 0) return ['kind' => 'none', 'amount' => 0];

        $nightly = $this->nightlyRate($reservation);
        $amount  = round($nightly * $pct / 100, 2);
        if ($amount <= 0) return ['kind' => 'none', 'amount' => 0];

        $folio = $this->getOrCreateFolio($reservation);
        $taxRate = 18; // services
        $taxAmt  = round($amount * $taxRate / 100, 2);

        FolioCharge::create([
            'tenant_id'        => $reservation->tenant_id,
            'property_id'      => $reservation->property_id,
            'folio_id'         => $folio->id,
            'charge_date'      => today()->toDateString(),
            'charge_time'      => now()->toTimeString(),
            'business_date'    => today()->toDateString(),
            'category'         => 'no_show_fee',
            'description'      => "No-show fee · {$pct}% of first night",
            'reference'        => 'NOSHOW-' . ($reservation->reservation_number ?? $reservation->id),
            'quantity'         => 1,
            'rate'             => $amount,
            'amount'           => $amount,
            'discount_amount'  => 0,
            'tax_amount'       => $taxAmt,
            'net_amount'       => $amount + $taxAmt,
            'tax_breakdown'    => ['cgst' => round($taxAmt/2, 2), 'sgst' => round($taxAmt/2, 2), 'total' => $taxAmt],
            'posted_by'        => auth()->id(),
        ]);

        $reservation->forceFill([
            'no_show_fee_amount' => $amount + $taxAmt,
            'no_show_marked_at'  => now(),
            'status'             => Reservation::STATUS_NO_SHOW,
        ])->save();

        if (method_exists($folio, 'recomputeTotals')) {
            $folio->recomputeTotals();
        }
        return ['kind' => 'no_show', 'amount' => $amount + $taxAmt];
    }

    // ---------- internals ----------

    private function postCharge(
        Reservation $reservation,
        string $type,
        string $kind,
        int $pct,
        string $description,
        string $referenceLabel,
    ): array {
        // Idempotency — never double-charge for the same lifecycle event
        $existing = $type === 'eci' ? (float) $reservation->eci_charge_amount : (float) $reservation->lco_charge_amount;
        if ($existing > 0) {
            return ['kind' => 'already_charged', 'amount' => $existing];
        }

        $nightly = $this->nightlyRate($reservation);
        $amount  = round($nightly * $pct / 100, 2);
        if ($amount <= 0) return $this->markZero($reservation, $type, 'grace');

        $taxRate = 18;
        $taxAmt  = round($amount * $taxRate / 100, 2);

        $folio = $this->getOrCreateFolio($reservation);

        FolioCharge::create([
            'tenant_id'        => $reservation->tenant_id,
            'property_id'      => $reservation->property_id,
            'folio_id'         => $folio->id,
            'charge_date'      => today()->toDateString(),
            'charge_time'      => now()->toTimeString(),
            'business_date'    => today()->toDateString(),
            'category'         => $type === 'eci' ? 'early_check_in' : 'late_check_out',
            'description'      => $description,
            'reference'        => $referenceLabel . '-' . ($reservation->reservation_number ?? $reservation->id),
            'quantity'         => 1,
            'rate'             => $amount,
            'amount'           => $amount,
            'discount_amount'  => 0,
            'tax_amount'       => $taxAmt,
            'net_amount'       => $amount + $taxAmt,
            'tax_breakdown'    => ['cgst' => round($taxAmt/2, 2), 'sgst' => round($taxAmt/2, 2), 'total' => $taxAmt],
            'posted_by'        => auth()->id(),
        ]);

        $reservation->forceFill([
            ($type === 'eci' ? 'eci_charge_amount' : 'lco_charge_amount') => $amount + $taxAmt,
            ($type === 'eci' ? 'eci_charge_kind'   : 'lco_charge_kind')   => $kind,
        ])->save();

        if (method_exists($folio, 'recomputeTotals')) {
            $folio->recomputeTotals();
        }

        return ['kind' => $kind, 'amount' => $amount + $taxAmt];
    }

    private function markZero(Reservation $r, string $type, string $kind): array
    {
        $r->forceFill([
            ($type === 'eci' ? 'eci_charge_kind' : 'lco_charge_kind') => $kind,
        ])->save();
        return ['kind' => $kind, 'amount' => 0];
    }

    private function nightlyRate(Reservation $reservation): float
    {
        if ($reservation->nights && $reservation->nights > 0) {
            return (float) ($reservation->room_revenue ?? $reservation->total_amount) / $reservation->nights;
        }
        return (float) $reservation->total_amount;
    }

    private function combine($date, ?string $time): ?Carbon
    {
        if (! $date) return null;
        $time = $time ?: '14:00';
        $dateStr = $date instanceof Carbon ? $date->toDateString() : (string) $date;
        try {
            return Carbon::parse($dateStr . ' ' . $time);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getOrCreateFolio(Reservation $r): Folio
    {
        $folio = Folio::where('reservation_id', $r->id)
            ->whereIn('status', ['open','closed'])
            ->orderBy('id')
            ->first();
        if ($folio) return $folio;

        return Folio::create([
            'tenant_id'      => $r->tenant_id,
            'property_id'    => $r->property_id,
            'reservation_id' => $r->id,
            'folio_number'   => 'F-' . now()->format('ymd') . '-' . $r->id,
            'type'           => 'guest',
            'guest_id'       => $r->guest_id,
            'billing_name'   => $r->guest_name ?? 'Guest',
            'currency'       => 'INR',
            'status'         => 'open',
        ]);
    }
}
