<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\Reservation;
use App\Services\Billing\EciLcoService;
use App\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-mark expired confirmed reservations as no_show + post the no-show fee.
 *
 * Logic per property:
 *   - status IN ('confirmed','tentative')
 *   - arrival_date < today
 *     OR (arrival_date = today AND now() > check_in_time + grace_hours)
 *   - property.auto_mark_no_show = true
 * Idempotent — won't re-process reservations already moved to no_show.
 *
 * Schedule: hourly (see routes/console.php).
 * Manual:   php artisan reservations:auto-no-show [--dry-run] [--property=ID]
 */
class AutoMarkNoShows extends Command
{
    protected $signature = 'reservations:auto-no-show
                            {--dry-run : Show what would change without writing}
                            {--property= : Only process this property id}';
    protected $description = 'Auto-mark unfulfilled confirmed reservations as no-show and post the no-show fee.';

    public function handle(EciLcoService $eciLco, TenantContext $ctx): int
    {
        $now = Carbon::now();
        $dry = (bool) $this->option('dry-run');
        $propertyFilter = $this->option('property');

        $stats = ['processed' => 0, 'marked' => 0, 'fees_posted_total' => 0.0, 'skipped' => 0];

        // Iterate properties so per-property policies (grace hours, auto-flag) are honoured
        $properties = Property::query()
            ->when($propertyFilter, fn ($q) => $q->where('id', $propertyFilter))
            ->where(function ($q) {
                $q->where('auto_mark_no_show', true)
                  ->orWhereNull('auto_mark_no_show');
            })
            ->get();

        foreach ($properties as $property) {
            if (! $property->auto_mark_no_show && $property->auto_mark_no_show !== null) continue;

            $checkInTime = $property->check_in_time ?: '14:00';
            $graceHrs    = (int) ($property->noshow_grace_hours_after_arrival ?? 6);

            $candidates = Reservation::query()
                ->where('property_id', $property->id)
                ->whereIn('status', [Reservation::STATUS_CONFIRMED ?? 'confirmed', Reservation::STATUS_TENTATIVE ?? 'tentative'])
                ->where(function ($q) use ($now, $checkInTime, $graceHrs) {
                    // Past arrival date entirely
                    $q->where('arrival_date', '<', $now->toDateString())
                      ->orWhere(function ($q) use ($now, $checkInTime, $graceHrs) {
                          $cutoffToday = Carbon::parse($now->toDateString() . ' ' . $checkInTime)->addHours($graceHrs);
                          $q->where('arrival_date', '=', $now->toDateString())
                            ->where(DB::raw('NOW()'), '>', $cutoffToday);
                      });
                })
                ->get();

            foreach ($candidates as $res) {
                $stats['processed']++;
                if ($res->no_show_marked_at) {
                    $stats['skipped']++;
                    continue;
                }

                if ($dry) {
                    $this->line("  [dry-run] Would mark #{$res->reservation_number} ({$res->guest_name}) "
                        . "arrival={$res->arrival_date->toDateString()}");
                    $stats['marked']++;
                    continue;
                }

                $ctx->bypass(function () use ($eciLco, $res, &$stats) {
                    $result = $eciLco->applyNoShow($res);
                    if (in_array($result['kind'] ?? 'none', ['no_show'])) {
                        $stats['fees_posted_total'] += (float) $result['amount'];
                    }
                });

                $stats['marked']++;
                $this->info("  Marked #{$res->reservation_number} ({$res->guest_name}) as no-show");
            }
        }

        $this->newLine();
        $this->info("Run summary:");
        $this->line("  Properties scanned: " . $properties->count());
        $this->line("  Reservations processed: {$stats['processed']}");
        $this->line("  Marked as no-show: {$stats['marked']}");
        $this->line("  Skipped (already marked): {$stats['skipped']}");
        $this->line("  Total no-show fees posted: ₹" . number_format($stats['fees_posted_total'], 2));
        if ($dry) $this->warn('  (dry-run — no changes written)');

        return self::SUCCESS;
    }
}
