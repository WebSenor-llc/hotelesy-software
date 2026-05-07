<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Accounts\NightAuditService;
use App\Services\ModuleService;
use App\Services\TenantContext;
use Illuminate\Console\Command;

/**
 * Runs night audit for properties whose configured night audit time has passed.
 *
 * Property has `night_audit_time` (e.g. '00:30') and `timezone`. Night audit
 * runs at that local time + 1 minute grace.
 *
 * Scheduled hourly via routes/console.php — checks every hour whether any
 * property is due. Idempotent if already run for that business_date.
 */
class RunNightAudit extends Command
{
    protected $signature = 'night-audit:run {--property=} {--force}';
    protected $description = 'Run night audit for properties due now';

    public function handle(NightAuditService $svc, ModuleService $modules, TenantContext $context): int
    {
        return $context->bypass(function () use ($svc, $modules, $context) {
            $query = Property::where('is_active', true);
            if ($id = $this->option('property')) {
                $query->where('id', $id);
            }

            $force = (bool) $this->option('force');
            $count = 0;
            $failed = 0;

            $query->chunk(50, function ($props) use ($svc, $modules, $context, $force, &$count, &$failed) {
                foreach ($props as $property) {
                    $context->set($property->tenant, $property);

                    if (! $force && ! $this->isDue($property)) continue;

                    $this->info("Running night audit for {$property->code}...");

                    try {
                        $log = $svc->run($property);
                        $this->line("  -> rooms_sold: {$log->rooms_sold}, occ: {$log->occupancy_pct}%, revenue: {$log->total_revenue}");
                        $count++;
                    } catch (\Throwable $e) {
                        $this->error("  -> {$e->getMessage()}");
                        $failed++;
                        report($e);
                    }
                }
            });

            $this->info("Audit complete: {$count} succeeded, {$failed} failed.");
            return self::SUCCESS;
        });
    }

    private function isDue(Property $property): bool
    {
        $localNow = now($property->timezone ?? 'Asia/Kolkata');
        $auditTime = $property->night_audit_time ?? '00:30';
        [$hh, $mm] = explode(':', $auditTime);
        $auditMoment = $localNow->copy()->setTime((int) $hh, (int) $mm, 0);

        // Audit is due if current time >= audit time but business date not yet advanced past today
        if ($localNow->lt($auditMoment)) return false;

        $currentBusinessDate = $property->current_business_date ?? $localNow->copy()->subDay()->toDateString();
        return $currentBusinessDate <= $localNow->copy()->subDay()->toDateString();
    }
}
