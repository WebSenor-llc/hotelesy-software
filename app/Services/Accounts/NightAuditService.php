<?php

namespace App\Services\Accounts;

use App\Models\Accounts\NightAuditLog;
use App\Models\DailyInventory;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * NightAuditService — closes the business day for a property.
 *
 * Critical operations performed in sequence:
 *   1. Lock — set night_audit_locked = true so no edits to past dates.
 *   2. Auto-roll undeparted no-shows (status: confirmed but arrival_date < today
 *      and never checked-in) → mark no_show.
 *   3. Post room revenue charges to in-house folios for the day's stay night.
 *   4. Aggregate revenue / collected / outstanding figures across all folios.
 *   5. Compute KPIs: occupancy %, ARR, RevPAR.
 *   6. Insert NightAuditLog snapshot.
 *   7. Advance current_business_date by one day, unlock.
 *
 * Failure handling: every step is in a transaction. On exception, the audit
 * is rolled back and an entry remains with status='failed' for ops to investigate.
 *
 * Reversibility: a completed audit can be rolled back by an admin within 24h.
 * After 24h, only a manual journal entry can correct figures.
 *
 * Idempotency: re-running the same business_date is a no-op (returns existing log).
 */
class NightAuditService
{
    public function __construct(private readonly ReservationService $reservations) {}

    public function run(Property $property, ?Carbon $forDate = null): NightAuditLog
    {
        $businessDate = ($forDate ?? $property->businessDate())->copy()->startOfDay();

        // Idempotency check
        $existing = NightAuditLog::where('property_id', $property->id)
            ->whereDate('business_date', $businessDate)
            ->where('status', NightAuditLog::STATUS_COMPLETED)
            ->first();
        if ($existing) return $existing;

        $log = NightAuditLog::create([
            'property_id' => $property->id,
            'business_date' => $businessDate->toDateString(),
            'started_at' => now(),
            'status' => NightAuditLog::STATUS_RUNNING,
            'performed_by' => auth()->id(),
        ]);

        try {
            return DB::transaction(function () use ($property, $businessDate, $log) {
                $property->update(['night_audit_locked' => true]);

                $exceptions = [];

                // 1. No-show conversion
                $noShowCount = $this->convertNoShows($property, $businessDate, $exceptions);

                // 2. Post room revenue for in-house guests
                $postedRevenue = $this->postRoomRevenue($property, $businessDate, $exceptions);

                // 3. Aggregate stats
                $stats = $this->computeStats($property, $businessDate);

                // 4. Update log
                $log->update(array_merge($stats, [
                    'no_shows_count' => $noShowCount,
                    'exception_log' => $exceptions,
                    'completed_at' => now(),
                    'status' => NightAuditLog::STATUS_COMPLETED,
                ]));

                // 5. Advance business date, release lock
                $property->update([
                    'current_business_date' => $businessDate->copy()->addDay()->toDateString(),
                    'night_audit_locked' => false,
                ]);

                return $log->fresh();
            });
        } catch (\Throwable $e) {
            $log->update([
                'status' => NightAuditLog::STATUS_FAILED,
                'completed_at' => now(),
                'notes' => substr($e->getMessage(), 0, 1000),
            ]);
            $property->update(['night_audit_locked' => false]);
            throw $e;
        }
    }

    /**
     * Find reservations that should have arrived today but never checked in,
     * mark them as no-shows. Releases their inventory and applies the cancellation
     * charge if configured.
     */
    private function convertNoShows(Property $property, Carbon $businessDate, array &$exceptions): int
    {
        $noShows = Reservation::where('property_id', $property->id)
            ->whereDate('arrival_date', $businessDate)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->get();

        $count = 0;
        foreach ($noShows as $r) {
            try {
                // Default: charge first night as no-show fee (configurable later)
                $firstNight = $r->nights()->orderBy('night_date')->first();
                $charge = $firstNight ? (float) $firstNight->net_amount : 0;
                $this->reservations->markNoShow($r, $charge);
                $count++;
            } catch (\Throwable $e) {
                $exceptions[] = "no_show_failed:{$r->reservation_number}:{$e->getMessage()}";
            }
        }
        return $count;
    }

    /**
     * Post the day's room rate as a folio charge for every in-house guest.
     * Skips folios that are already settled / closed.
     */
    private function postRoomRevenue(Property $property, Carbon $businessDate, array &$exceptions): float
    {
        $folios = Folio::where('property_id', $property->id)
            ->where('status', 'open')
            ->whereHas('reservation', function ($q) use ($businessDate) {
                $q->where('status', Reservation::STATUS_CHECKED_IN)
                    ->whereDate('arrival_date', '<=', $businessDate)
                    ->whereDate('departure_date', '>', $businessDate);
            })
            ->with('reservation.nights')
            ->get();

        $totalPosted = 0;
        $folioService = app(\App\Services\Billing\FolioService::class);

        foreach ($folios as $folio) {
            try {
                $resv = $folio->reservation;
                $night = $resv->nights()->whereDate('night_date', $businessDate)->first();
                if (! $night) continue;

                // Skip if already posted today
                $alreadyPosted = FolioCharge::where('folio_id', $folio->id)
                    ->where('category', 'room')
                    ->whereDate('charge_date', $businessDate)
                    ->exists();
                if ($alreadyPosted) continue;

                $folioService->postCharge($folio, [
                    'category' => 'room',
                    'description' => "Room charge — {$businessDate->toDateString()}",
                    'reference' => $resv->reservation_number,
                    'quantity' => 1,
                    'rate' => (float) $night->rate,
                    'amount' => (float) $night->rate,
                    'charge_date' => $businessDate->toDateString(),
                    'business_date' => $businessDate->toDateString(),
                ]);

                $totalPosted += (float) $night->net_amount;
            } catch (\Throwable $e) {
                $exceptions[] = "room_post_failed:folio_{$folio->id}:{$e->getMessage()}";
            }
        }

        return $totalPosted;
    }

    private function computeStats(Property $property, Carbon $businessDate): array
    {
        $startOfDay = $businessDate->copy()->startOfDay();
        $endOfDay = $businessDate->copy()->endOfDay();

        // Reservation counts
        $arrivals = Reservation::where('property_id', $property->id)
            ->whereDate('arrival_date', $businessDate)
            ->whereIn('status', [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])
            ->count();

        $departures = Reservation::where('property_id', $property->id)
            ->whereDate('departure_date', $businessDate)
            ->where('status', Reservation::STATUS_CHECKED_OUT)
            ->count();

        $inHouse = Reservation::where('property_id', $property->id)
            ->whereDate('arrival_date', '<=', $businessDate)
            ->whereDate('departure_date', '>', $businessDate)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->sum('rooms_count');

        $walkIns = Reservation::where('property_id', $property->id)
            ->whereDate('arrival_date', $businessDate)
            ->where('source_type', 'walk_in')
            ->count();

        // Revenue from charges posted for this business date
        $charges = FolioCharge::where('property_id', $property->id)
            ->whereDate('business_date', $businessDate)
            ->where('is_voided', false)
            ->get();

        $roomRevenue = (float) $charges->where('category', 'room')->sum('amount');
        $foodRevenue = (float) $charges->where('category', 'food')->sum('amount');
        $beverageRevenue = (float) $charges->where('category', 'beverage')->sum('amount');
        $banquetRevenue = 0; // banquet bookings completed today
        $otherRevenue = (float) $charges->whereNotIn('category', ['room', 'food', 'beverage'])->sum('amount');
        $totalRevenue = $roomRevenue + $foodRevenue + $beverageRevenue + $banquetRevenue + $otherRevenue;
        $totalTax = (float) $charges->sum('tax_amount');

        // Collections & outstanding
        $totalCollected = (float) Payment::where('property_id', $property->id)
            ->whereDate('business_date', $businessDate)
            ->where('status', 'completed')
            ->sum('amount');

        $outstanding = (float) Folio::where('property_id', $property->id)
            ->where('status', 'open')
            ->sum('balance');

        // Inventory KPIs
        $invSummary = DailyInventory::where('property_id', $property->id)
            ->whereDate('date', $businessDate)
            ->selectRaw('SUM(total_rooms) as total, SUM(rooms_sold) as sold')
            ->first();

        $roomsAvailable = (int) ($invSummary->total ?? 0);
        $roomsSold = (int) ($invSummary->sold ?? 0);
        $occupancyPct = $roomsAvailable > 0 ? round(($roomsSold / $roomsAvailable) * 100, 2) : 0;
        $arr = $roomsSold > 0 ? round($roomRevenue / $roomsSold, 2) : 0;
        $revpar = $roomsAvailable > 0 ? round($roomRevenue / $roomsAvailable, 2) : 0;

        return [
            'arrivals_count' => $arrivals,
            'departures_count' => $departures,
            'in_house_count' => (int) $inHouse,
            'walk_ins_count' => $walkIns,
            'room_revenue' => round($roomRevenue, 2),
            'food_revenue' => round($foodRevenue, 2),
            'beverage_revenue' => round($beverageRevenue, 2),
            'banquet_revenue' => round($banquetRevenue, 2),
            'other_revenue' => round($otherRevenue, 2),
            'total_revenue' => round($totalRevenue, 2),
            'total_tax' => round($totalTax, 2),
            'total_collected' => round($totalCollected, 2),
            'outstanding' => round($outstanding, 2),
            'rooms_sold' => $roomsSold,
            'rooms_available' => $roomsAvailable,
            'occupancy_pct' => $occupancyPct,
            'arr' => $arr,
            'revpar' => $revpar,
        ];
    }
}
