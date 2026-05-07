<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\ChannelManager\InventorySyncService;
use App\Services\ModuleService;
use App\Services\TenantContext;
use Illuminate\Console\Command;

/**
 * Push current inventory + rates + restrictions to channel managers for the
 * next N days.
 *
 * Scheduled every 30 min for the rolling 90 days. Critical because OTA listings
 * fall stale within minutes of overselling, leading to overbooking.
 */
class PushChannelInventory extends Command
{
    protected $signature = 'cm:push-inventory {--property=} {--days=90}';
    protected $description = 'Push current inventory + rates to channel managers';

    public function handle(InventorySyncService $sync, ModuleService $modules, TenantContext $context): int
    {
        return $context->bypass(function () use ($sync, $modules, $context) {
            $query = Property::where('is_active', true);
            if ($id = $this->option('property')) {
                $query->where('id', $id);
            }

            $days = (int) $this->option('days');
            $from = now()->startOfDay();
            $to = now()->copy()->addDays($days)->endOfDay();

            $query->chunk(50, function ($properties) use ($sync, $modules, $context, $from, $to) {
                foreach ($properties as $property) {
                    $context->set($property->tenant, $property);
                    if (! $modules->enabled('channel_manager', $property)) {
                        continue;
                    }

                    $this->info("Pushing for {$property->code}...");

                    try {
                        $invResult = $sync->syncInventory($property, $from, $to);
                        $this->line("  inventory: " . ($invResult->success ? 'ok' : 'FAILED'));

                        $rateResult = $sync->syncRates($property, $from, $to);
                        $this->line("  rates:     " . ($rateResult->success ? 'ok' : 'FAILED'));

                        $restResult = $sync->syncRestrictions($property, $from, $to);
                        $this->line("  restr:     " . ($restResult->success ? 'ok' : 'FAILED'));
                    } catch (\Throwable $e) {
                        $this->error("  -> {$e->getMessage()}");
                        report($e);
                    }
                }
            });

            return self::SUCCESS;
        });
    }
}
