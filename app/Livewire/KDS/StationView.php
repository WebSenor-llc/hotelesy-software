<?php

namespace App\Livewire\KDS;

use App\Models\KDS\KdsStation;
use App\Models\KDS\KdsTicket;
use App\Models\POS\Order;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class StationView extends Component
{
    public ?int $stationId = null;
    public bool $autoRefresh = true;

    public function mount(): void
    {
        $first = KdsStation::where('property_id', app(TenantContext::class)->propertyId())->first();
        $this->stationId = $first?->id;
    }

    public function bumpTicket(int $ticketId): void
    {
        DB::transaction(function () use ($ticketId) {
            $t = KdsTicket::findOrFail($ticketId);
            $now = now();

            // Advance ticket
            if ($t->status === 'queued') {
                $t->update(['status' => 'started', 'started_at' => $now]);
                $orderStatus = Order::STATUS_PREPARING;
            } elseif ($t->status === 'started') {
                $t->update(['status' => 'ready', 'ready_at' => $now]);
                $orderStatus = Order::STATUS_READY;
            } elseif ($t->status === 'ready') {
                $t->update(['status' => 'served', 'served_at' => $now]);
                $orderStatus = Order::STATUS_SERVED;
            } else {
                return;
            }

            // Sync parent POS order status — only update if it's still in an earlier stage
            if ($t->order_id) {
                $order = Order::find($t->order_id);
                if ($order) {
                    $rank = [
                        Order::STATUS_OPEN => 0,
                        Order::STATUS_SENT => 1,
                        Order::STATUS_PREPARING => 2,
                        Order::STATUS_READY => 3,
                        Order::STATUS_SERVED => 4,
                        Order::STATUS_BILLED => 5,
                        Order::STATUS_SETTLED => 6,
                    ];
                    $current = $rank[$order->status] ?? 0;
                    $next = $rank[$orderStatus] ?? 0;
                    if ($next > $current) {
                        $update = ['status' => $orderStatus];
                        if ($orderStatus === Order::STATUS_SERVED) {
                            $update['served_at'] = $now;
                        }
                        $order->update($update);
                    }
                }
            }
        });

        session()->flash('kds_success', 'Ticket bumped.');
    }

    public function recallTicket(int $ticketId): void
    {
        $t = KdsTicket::findOrFail($ticketId);
        if ($t->status === 'served') {
            $t->update(['status' => 'ready', 'is_recall' => true, 'served_at' => null]);
            // Pull parent order back to ready
            if ($t->order_id) {
                $order = Order::find($t->order_id);
                if ($order && in_array($order->status, [Order::STATUS_SERVED])) {
                    $order->update(['status' => Order::STATUS_READY, 'served_at' => null]);
                }
            }
            session()->flash('kds_success', 'Ticket recalled.');
        }
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $stations = KdsStation::where('property_id', $propertyId)->orderBy('name')->get();

        $tickets = $this->stationId
            ? KdsTicket::where('station_id', $this->stationId)
                ->whereIn('status', ['queued', 'started', 'ready'])
                ->orderBy('queued_at')
                ->get()
            : collect();

        $recentlyCompleted = $this->stationId
            ? KdsTicket::with('order')
                ->where('station_id', $this->stationId)
                ->where('status', 'served')
                ->whereDate('served_at', today())
                ->orderByDesc('served_at')
                ->limit(8)
                ->get()
            : collect();

        $stats = [
            'queued' => $tickets->where('status', 'queued')->count(),
            'started' => $tickets->where('status', 'started')->count(),
            'ready' => $tickets->where('status', 'ready')->count(),
            'served_today' => $this->stationId
                ? KdsTicket::where('station_id', $this->stationId)
                    ->where('status', 'served')
                    ->whereDate('served_at', today())
                    ->count()
                : 0,
        ];

        return view('livewire.kds.station-view', compact('stations', 'tickets', 'recentlyCompleted', 'stats'));
    }
}
