<?php

namespace App\Livewire\Super;

use App\Models\SubscriptionTransaction;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class RevenueReport extends Component
{
    use WithPagination;

    public string $period = '30';   // 30 | 90 | 365 | all
    public string $statusFilter = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);
    }

    public function render()
    {
        return app(TenantContext::class)->bypass(function () {
            $now = Carbon::now();
            $cutoff = match ($this->period) {
                '30'  => $now->copy()->subDays(30),
                '90'  => $now->copy()->subDays(90),
                '365' => $now->copy()->subDays(365),
                default => null,
            };

            $base = SubscriptionTransaction::query()->orderByDesc('created_at');
            if ($cutoff) $base->where('created_at', '>=', $cutoff);
            if ($this->statusFilter !== 'all') $base->where('status', $this->statusFilter);

            $captured = (clone $base)->where('status', SubscriptionTransaction::STATUS_CAPTURED)->sum('amount');
            $refunded = (clone $base)->where('status', SubscriptionTransaction::STATUS_REFUNDED)->sum('amount');
            $pending  = (clone $base)->where('status', SubscriptionTransaction::STATUS_PENDING)->sum('amount');
            $failed   = (clone $base)->where('status', SubscriptionTransaction::STATUS_FAILED)->sum('amount');
            $netRev   = (float) $captured - (float) $refunded;

            $byType = (clone $base)->where('status', SubscriptionTransaction::STATUS_CAPTURED)
                ->select('type', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->get();

            $list = $base->with(['tenant','plan'])->paginate(50);

            return view('livewire.super.revenue-report', [
                'list'     => $list,
                'captured' => (float) $captured,
                'refunded' => (float) $refunded,
                'pending'  => (float) $pending,
                'failed'   => (float) $failed,
                'netRev'   => $netRev,
                'byType'   => $byType,
            ]);
        });
    }
}
