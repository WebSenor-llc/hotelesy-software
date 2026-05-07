<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\ChannelManager\InventorySyncService;
use App\Services\ModuleService;
use App\Services\TenantContext;
use Illuminate\Console\Command;

/**
 * Scheduled every 5 minutes via routes/console.php.
 * Pulls new/modified/cancelled bookings from each property's channel manager
 * and imports them as Reservations.
 *
 * For 100s of properties this should run with --queue to avoid sequential
 * blocking. Each property is dispatched to a queued job.
 */
class PullChannelBookings extends Command
{
    protected $signature = 'cm:pull-bookings {--property=}';
    protected $description = 'Pull new bookings from channel managers';

    public function handle(InventorySyncService $sync, ModuleService $modules, TenantContext $context): int
    {
        return $context->bypass(function () use ($sync, $modules, $context) {
            $query = Property::where('is_active', true);

            if ($id = $this->option('property')) {
                $query->where('id', $id);
            }

            $count = 0;
            $query->chunk(50, function ($properties) use ($sync, $modules, $context, &$count) {
                foreach ($properties as $property) {
                    $context->set($property->tenant, $property);

                    if (! $modules->enabled('channel_manager', $property)) {
                        continue;
                    }

                    $this->info("Pulling bookings for {$property->code}...");

                    try {
                        $stats = $sync->pullAndImport($property, now()->subHour());
                        $this->line("  -> created: {$stats['created']}, modified: {$stats['modified']}, cancelled: {$stats['cancelled']}, failed: {$stats['failed']}");
                        $count++;
                    } catch (\Throwable $e) {
                        $this->error("  -> {$e->getMessage()}");
                        report($e);
                    }
                }
            });

            $this->info("Processed {$count} properties.");
            return self::SUCCESS;
        });
    }
}
