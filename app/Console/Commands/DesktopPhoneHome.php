<?php

namespace App\Console\Commands;

use App\Services\Desktop\LicenseActivator;
use Illuminate\Console\Command;

/**
 * desktop:phone-home — silent license re-validation.
 *
 * Scheduled daily by the desktop kernel. Logs to storage/logs/laravel.log.
 *
 *   - Reads the local encrypted license file.
 *   - If the cadence threshold (config('desktop.phone_home_every_days'))
 *     has elapsed since last_validated_at, POSTs to /api/desktop/validate
 *     on the configured license server.
 *   - On success, refreshes valid_until / features and resets the offline
 *     clock.
 *   - On a hard reject (license revoked / fingerprint mismatch), marks the
 *     local file invalid so the EnforceDesktopLicense middleware kicks the
 *     user back to the activation screen.
 *
 * Failures (network down, server 5xx) are non-fatal — the offline grace
 * period kicks in via EnforceDesktopLicense.
 */
class DesktopPhoneHome extends Command
{
    protected $signature = 'desktop:phone-home {--force : ignore cadence and re-validate now}';

    protected $description = 'Re-validates the desktop license against the cloud server.';

    public function handle(LicenseActivator $activator): int
    {
        if (config('desktop.mode') !== 'desktop' && config('app.env') !== 'desktop') {
            $this->warn('Skipping: APP_MODE is not desktop.');
            return self::SUCCESS;
        }

        $state = $activator->status();

        if ($state['status'] === LicenseActivator::STATUS_UNACTIVATED) {
            $this->warn('No license file present. Skipping phone-home.');
            return self::SUCCESS;
        }

        // Respect cadence unless --force is set.
        if (! $this->option('force')) {
            $cadence = (int) config('desktop.phone_home_every_days', 7);
            $daysSince = (int) ($state['days_offline'] ?? 0);
            if ($daysSince < $cadence) {
                $this->info("Within cadence ({$daysSince}d / {$cadence}d). Nothing to do.");
                return self::SUCCESS;
            }
        }

        $this->info('Phoning home…');
        $ok = $activator->phoneHome();

        if ($ok) {
            $this->info('License re-validated successfully.');
            return self::SUCCESS;
        }

        $this->warn('Phone-home failed (server unreachable or rejected). Offline grace period applies.');
        return self::FAILURE;
    }
}
