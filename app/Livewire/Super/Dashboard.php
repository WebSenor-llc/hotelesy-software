<?php

namespace App\Livewire\Super;

use App\Models\License;
use App\Models\MarketingLead;
use App\Models\Property;
use App\Models\Room;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Dashboard extends Component
{
    public function sendReminder(int $licenseId): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        app(TenantContext::class)->bypass(function () use ($licenseId) {
            $license = License::with('tenant')->findOrFail($licenseId);
            // Mark reminder sent — actual email/SMS delivery handled by the queue
            // (see app/Console/Commands/SendRenewalReminders.php).
            $license->update(['last_reminder_sent_at' => now()]);

            // Dispatch a renewal reminder mail right now.
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "Hello {$license->tenant->name},\n\n" .
                    "Your Hotelesy subscription expires on " . $license->expires_at->format('d M Y') .
                    " (in {$license->daysRemaining()} days).\n\n" .
                    "Renew now: " . url('/checkout?plan=' . $license->subscription_plan_id . '&cycle=monthly') . "\n\n" .
                    "— Team Hotelesy",
                    function ($m) use ($license) {
                        $m->to($license->tenant->owner_email)
                          ->subject('Your Hotelesy subscription expires soon');
                    }
                );
            } catch (\Throwable $e) {
                // best-effort — don't block the UI
            }
        });

        session()->flash('success', 'Renewal reminder sent.');
    }

    public function render()
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        return app(TenantContext::class)->bypass(function () {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();

            // ===== HEADLINE METRICS =====
            $totalTenants = Tenant::count();
            $activeClients = License::whereIn('status', [License::STATUS_TRIAL, License::STATUS_ACTIVE])
                ->where('expires_at', '>', $now)
                ->distinct('tenant_id')->count('tenant_id');

            // MRR - active non-trial licenses
            $mrr = License::join('subscription_plans','subscription_plans.id','=','licenses.subscription_plan_id')
                ->where('licenses.status', License::STATUS_ACTIVE)
                ->where('licenses.expires_at', '>', $now)
                ->sum('subscription_plans.price_monthly');

            // Lifetime + this-month captured revenue
            $revenueLifetime = (float) SubscriptionTransaction::where('status', SubscriptionTransaction::STATUS_CAPTURED)
                ->where('type', '!=', SubscriptionTransaction::TYPE_REFUND)
                ->sum('amount');
            $revenueThisMonth = (float) SubscriptionTransaction::where('status', SubscriptionTransaction::STATUS_CAPTURED)
                ->where('type', '!=', SubscriptionTransaction::TYPE_REFUND)
                ->whereBetween('paid_at', [$startOfMonth, $now])
                ->sum('amount');
            $revenueLastMonth = (float) SubscriptionTransaction::where('status', SubscriptionTransaction::STATUS_CAPTURED)
                ->where('type', '!=', SubscriptionTransaction::TYPE_REFUND)
                ->whereBetween('paid_at', [$startOfMonth->copy()->subMonth(), $startOfMonth])
                ->sum('amount');
            $revenueDelta = $revenueLastMonth > 0
                ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
                : 0;

            // New subscriptions this month / new tenants
            $newSubsThisMonth = License::whereBetween('created_at', [$startOfMonth, $now])->count();
            $newTenantsThisMonth = Tenant::whereBetween('created_at', [$startOfMonth, $now])->count();

            // ===== EXPIRY BUCKETS =====
            $expiringWithin7  = License::with(['tenant','plan'])
                ->whereIn('status', [License::STATUS_TRIAL, License::STATUS_ACTIVE])
                ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
                ->orderBy('expires_at')
                ->get();
            $expiringWithin30 = License::with(['tenant','plan'])
                ->whereIn('status', [License::STATUS_TRIAL, License::STATUS_ACTIVE])
                ->whereBetween('expires_at', [$now->copy()->addDays(7), $now->copy()->addDays(30)])
                ->orderBy('expires_at')
                ->get();
            $expiringWithin90 = License::with(['tenant','plan'])
                ->whereIn('status', [License::STATUS_TRIAL, License::STATUS_ACTIVE])
                ->whereBetween('expires_at', [$now->copy()->addDays(30), $now->copy()->addDays(90)])
                ->orderBy('expires_at')
                ->get();
            $expired = License::with(['tenant','plan'])
                ->where(function ($q) use ($now) {
                    $q->whereIn('status', [License::STATUS_EXPIRED, License::STATUS_CANCELLED])
                      ->orWhere('expires_at', '<=', $now);
                })
                ->orderByDesc('expires_at')
                ->limit(20)
                ->get();

            // ===== LEADS =====
            $leadStats = [
                'total'           => MarketingLead::count(),
                'new'             => MarketingLead::where('status', 'new')->count(),
                'contacted'       => MarketingLead::where('status', 'contacted')->count(),
                'qualified'       => MarketingLead::where('status', 'qualified')->count(),
                'won'             => MarketingLead::where('status', 'won')->count(),
                'lost'            => MarketingLead::where('status', 'lost')->count(),
                'this_month'      => MarketingLead::whereBetween('created_at', [$startOfMonth, $now])->count(),
            ];
            $recentLeads = MarketingLead::orderByDesc('created_at')->limit(8)->get();

            // ===== 6-MONTH REVENUE TREND =====
            $monthly = collect(range(5, 0))->map(function ($mo) {
                $start = Carbon::now()->subMonths($mo)->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $rev = (float) SubscriptionTransaction::where('status', SubscriptionTransaction::STATUS_CAPTURED)
                    ->where('type', '!=', SubscriptionTransaction::TYPE_REFUND)
                    ->whereBetween('paid_at', [$start, $end])
                    ->sum('amount');
                $signups = Tenant::whereBetween('created_at', [$start, $end])->count();
                return [
                    'label' => $start->format('M'),
                    'rev'   => $rev,
                    'signups' => $signups,
                ];
            });

            // ===== RECENT TRANSACTIONS =====
            $recentTx = SubscriptionTransaction::with(['tenant','plan'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            // ===== PLAN MIX =====
            $planMix = License::join('subscription_plans','subscription_plans.id','=','licenses.subscription_plan_id')
                ->whereIn('licenses.status', [License::STATUS_TRIAL, License::STATUS_ACTIVE])
                ->select('subscription_plans.name as plan_name', DB::raw('COUNT(licenses.id) as count'))
                ->groupBy('subscription_plans.name')
                ->orderByDesc('count')
                ->get();
            $planMixTotal = max(1, $planMix->sum('count'));

            return view('livewire.super.dashboard', [
                'kpis' => [
                    'total_tenants'      => $totalTenants,
                    'active_clients'     => $activeClients,
                    'mrr'                => (float) $mrr,
                    'arr'                => (float) $mrr * 12,
                    'revenue_lifetime'   => $revenueLifetime,
                    'revenue_this_month' => $revenueThisMonth,
                    'revenue_delta'      => $revenueDelta,
                    'new_subs_month'     => $newSubsThisMonth,
                    'new_tenants_month'  => $newTenantsThisMonth,
                    'expired_count'      => $expired->count(),
                    'expiring_7'         => $expiringWithin7->count(),
                    'expiring_30'        => $expiringWithin30->count(),
                    'expiring_90'        => $expiringWithin90->count(),
                ],
                'expiringWithin7'  => $expiringWithin7,
                'expiringWithin30' => $expiringWithin30,
                'expiringWithin90' => $expiringWithin90,
                'expired'          => $expired,
                'leadStats'        => $leadStats,
                'recentLeads'      => $recentLeads,
                'monthlyTrend'     => $monthly,
                'recentTx'         => $recentTx,
                'planMix'          => $planMix,
                'planMixTotal'     => $planMixTotal,
            ]);
        });
    }
}
