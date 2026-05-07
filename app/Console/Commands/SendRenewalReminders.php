<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send renewal reminders to tenants whose licenses are expiring.
 *
 * Cadence:
 *   - 7 days before expiry  (urgent)
 *   - 30 days before expiry (heads-up)
 *   - 90 days before expiry (long lead)
 *
 * Each license gets each reminder at most once per cadence window via
 * the `last_reminder_sent_at` timestamp.
 *
 * Schedule (in routes/console.php):
 *   Schedule::command('hotelesy:send-renewal-reminders')->dailyAt('09:00');
 */
class SendRenewalReminders extends Command
{
    protected $signature = 'hotelesy:send-renewal-reminders {--dry-run : Don\'t actually send mail}';
    protected $description = 'Send renewal reminders to tenants whose licenses expire within 7 / 30 / 90 days.';

    public function handle(): int
    {
        $sent = 0;

        app(TenantContext::class)->bypass(function () use (&$sent) {
            $now = now();

            $buckets = [
                ['label' => '7-day',  'min' => 0,  'max' => 7,  'cooldown' => 2],
                ['label' => '30-day', 'min' => 8,  'max' => 30, 'cooldown' => 7],
                ['label' => '90-day', 'min' => 31, 'max' => 90, 'cooldown' => 21],
            ];

            foreach ($buckets as $b) {
                $licenses = License::with('tenant', 'plan')
                    ->whereIn('status', [License::STATUS_TRIAL, License::STATUS_ACTIVE])
                    ->whereBetween('expires_at', [$now->copy()->addDays($b['min']), $now->copy()->addDays($b['max'])])
                    ->where(function ($q) use ($now, $b) {
                        $q->whereNull('last_reminder_sent_at')
                          ->orWhere('last_reminder_sent_at', '<=', $now->copy()->subDays($b['cooldown']));
                    })
                    ->get();

                $this->info("Bucket {$b['label']}: {$licenses->count()} eligible");

                foreach ($licenses as $license) {
                    if (! $license->tenant?->owner_email) continue;

                    $url = url('/checkout?plan=' . $license->subscription_plan_id . '&cycle=monthly');
                    $body = "Hello {$license->tenant->name},\n\n"
                          . "Your Hotelesy ({$license->plan?->name}) subscription expires on "
                          . $license->expires_at->format('d M Y')
                          . " (in {$license->daysRemaining()} days).\n\n"
                          . "Renew now to avoid interruption: {$url}\n\n"
                          . "If you have any questions, reply to this email.\n\n— Team Hotelesy";

                    if ($this->option('dry-run')) {
                        $this->line(" [dry-run] would email {$license->tenant->owner_email} ({$b['label']})");
                    } else {
                        try {
                            Mail::raw($body, function ($m) use ($license) {
                                $m->to($license->tenant->owner_email)
                                  ->subject('Your Hotelesy subscription expires in ' . $license->daysRemaining() . ' days');
                            });
                            $license->update(['last_reminder_sent_at' => now()]);
                            $sent++;
                        } catch (\Throwable $e) {
                            Log::warning('Renewal reminder send failed', [
                                'license_id' => $license->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        });

        $this->info("Done. Sent: {$sent} reminders.");
        return self::SUCCESS;
    }
}
