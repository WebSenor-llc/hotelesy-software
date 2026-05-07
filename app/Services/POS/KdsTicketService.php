<?php

namespace App\Services\POS;

use App\Models\KDS\KdsEvent;
use App\Models\KDS\KdsStation;
use App\Models\KDS\KdsTicket;
use App\Models\KDS\KdsTicketItem;
use App\Models\POS\Order;
use App\Models\POS\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KdsTicketService — KDS ticket lifecycle.
 *
 * Tickets are created by OrderService::sendToKitchen and progress through:
 *   queued → started (cook claims) → ready → served
 *
 * Recall: server flags item as wrong/cold → status reverts to 'recalled', cook re-prepares.
 *
 * Every transition is logged to kds_events for performance analytics
 * (avg prep time per dish, station utilization, peak hours).
 */
class KdsTicketService
{
    /**
     * Create a ticket for a single station from a slice of order items.
     */
    public function createTicket(Order $order, int $stationId, Collection $orderItems): KdsTicket
    {
        return DB::transaction(function () use ($order, $stationId, $orderItems) {
            $station = KdsStation::findOrFail($stationId);

            // Estimate target prep — max of (default, longest item-specific override)
            $targetSeconds = ($station->default_prep_minutes ?? 15) * 60;

            $ticketNumber = $this->nextTicketNumber($order, $station);

            $ticket = KdsTicket::create([
                'property_id' => $order->property_id,
                'order_id' => $order->id,
                'station_id' => $station->id,
                'ticket_number' => $ticketNumber,
                'status' => KdsTicket::STATUS_QUEUED,
                'queued_at' => now(),
                'target_prep_seconds' => $targetSeconds,
                'priority' => $this->derivePriority($order),
                'special_instructions' => $orderItems->pluck('special_instructions')->filter()->implode(' | '),
            ]);

            foreach ($orderItems as $orderItem) {
                KdsTicketItem::create([
                    'ticket_id' => $ticket->id,
                    'order_item_id' => $orderItem->id,
                    'item_name' => $orderItem->item_name,
                    'quantity' => $orderItem->quantity,
                    'modifiers' => $orderItem->modifiers,
                    'special_instructions' => $orderItem->special_instructions,
                    'status' => KdsTicketItem::class === \App\Models\KDS\KdsTicketItem::class ? 'queued' : 'queued',
                ]);
            }

            $this->logEvent($ticket, 'queued', ['order_number' => $order->order_number]);
            return $ticket;
        });
    }

    /**
     * Cook claims/starts the ticket.
     */
    public function start(KdsTicket $ticket, ?int $userId = null): KdsTicket
    {
        if ($ticket->status !== KdsTicket::STATUS_QUEUED) {
            throw new \DomainException("Ticket is {$ticket->status}, cannot start.");
        }

        return DB::transaction(function () use ($ticket, $userId) {
            $ticket->update([
                'status' => KdsTicket::STATUS_STARTED,
                'started_at' => now(),
                'claimed_by' => $userId ?? auth()->id(),
            ]);
            $ticket->items()->update(['status' => 'started']);
            $this->logEvent($ticket, 'started');
            return $ticket->fresh();
        });
    }

    /**
     * Cook marks ticket ready — server is notified to pick up.
     */
    public function markReady(KdsTicket $ticket): KdsTicket
    {
        if (! in_array($ticket->status, [KdsTicket::STATUS_QUEUED, KdsTicket::STATUS_STARTED], true)) {
            throw new \DomainException("Ticket is {$ticket->status}, cannot mark ready.");
        }

        return DB::transaction(function () use ($ticket) {
            $now = now();
            $actualPrepSeconds = $ticket->started_at
                ? $ticket->started_at->diffInSeconds($now)
                : $ticket->queued_at->diffInSeconds($now);

            $ticket->update([
                'status' => KdsTicket::STATUS_READY,
                'ready_at' => $now,
                'actual_prep_seconds' => $actualPrepSeconds,
                'is_overdue' => $actualPrepSeconds > ($ticket->target_prep_seconds ?? PHP_INT_MAX),
            ]);
            $ticket->items()->update([
                'status' => 'ready',
                'ready_at' => $now,
            ]);

            $this->logEvent($ticket, 'ready', ['actual_prep_seconds' => $actualPrepSeconds]);

            // Update parent order
            $this->updateOrderStatusFromTickets($ticket->order);
            return $ticket->fresh();
        });
    }

    /**
     * Server picks up ticket and serves to table — final state.
     */
    public function markServed(KdsTicket $ticket, ?int $userId = null): KdsTicket
    {
        if ($ticket->status !== KdsTicket::STATUS_READY) {
            throw new \DomainException("Ticket is {$ticket->status}, cannot mark served.");
        }

        return DB::transaction(function () use ($ticket, $userId) {
            $ticket->update([
                'status' => KdsTicket::STATUS_SERVED,
                'served_at' => now(),
            ]);
            $ticket->items()->update(['status' => 'served']);
            $this->logEvent($ticket, 'served', ['served_by' => $userId ?? auth()->id()]);
            $this->updateOrderStatusFromTickets($ticket->order);
            return $ticket->fresh();
        });
    }

    /**
     * Recall — server reports the dish was wrong/cold/missing. Cook re-prepares.
     */
    public function recall(KdsTicket $ticket, string $reason): KdsTicket
    {
        if (! in_array($ticket->status, [KdsTicket::STATUS_READY, KdsTicket::STATUS_SERVED], true)) {
            throw new \DomainException("Ticket is {$ticket->status}, cannot recall.");
        }

        return DB::transaction(function () use ($ticket, $reason) {
            $ticket->update([
                'status' => KdsTicket::STATUS_RECALLED,
                'is_recall' => true,
                'started_at' => null,
                'ready_at' => null,
                'served_at' => null,
                'priority' => KdsTicket::PRIORITY_RUSH,
            ]);
            $ticket->items()->update(['status' => 'queued', 'ready_at' => null]);

            $this->logEvent($ticket, 'recalled', ['reason' => $reason]);
            return $ticket->fresh();
        });
    }

    /**
     * Bump priority on the ticket (manager intervention).
     */
    public function setPriority(KdsTicket $ticket, string $priority): KdsTicket
    {
        $valid = [
            KdsTicket::PRIORITY_LOW,
            KdsTicket::PRIORITY_NORMAL,
            KdsTicket::PRIORITY_HIGH,
            KdsTicket::PRIORITY_RUSH,
        ];
        if (! in_array($priority, $valid, true)) {
            throw new \InvalidArgumentException("Invalid priority {$priority}.");
        }
        $previous = $ticket->priority;
        $ticket->update(['priority' => $priority]);
        $this->logEvent($ticket, 'priority_changed', ['from' => $previous, 'to' => $priority]);
        return $ticket->fresh();
    }

    /**
     * Active queue for a station — what the cooks see on screen.
     */
    public function activeQueue(KdsStation $station): Collection
    {
        return $station->activeTickets()
            ->with('items', 'order.table')
            ->orderByRaw("FIELD(priority, 'rush', 'high', 'normal', 'low')")
            ->orderBy('queued_at')
            ->get();
    }

    /* ---- private ---- */

    private function nextTicketNumber(Order $order, KdsStation $station): string
    {
        $count = KdsTicket::where('station_id', $station->id)
            ->whereDate('queued_at', now()->toDateString())
            ->count();
        return sprintf('%s-%03d', $station->code, $count + 1);
    }

    private function derivePriority(Order $order): string
    {
        if ($order->order_type === Order::TYPE_ROOM_SERVICE) {
            return KdsTicket::PRIORITY_HIGH;
        }
        return KdsTicket::PRIORITY_NORMAL;
    }

    private function logEvent(KdsTicket $ticket, string $eventType, array $data = []): void
    {
        KdsEvent::create([
            'property_id' => $ticket->property_id,
            'ticket_id' => $ticket->id,
            'event_type' => $eventType,
            'event_data' => $data,
            'user_id' => auth()->id(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Roll up ticket states to derive parent order status.
     * - All tickets queued/started → order: preparing
     * - Any ticket ready, none served → order: ready
     * - Some tickets served, some not → order stays at preparing
     * - All tickets served → order: served
     */
    private function updateOrderStatusFromTickets(Order $order): void
    {
        $statuses = $order->tickets()->pluck('status')->all();
        if (empty($statuses)) return;

        $allServed = ! array_diff($statuses, [KdsTicket::STATUS_SERVED, KdsTicket::STATUS_VOIDED]);
        $anyReady = in_array(KdsTicket::STATUS_READY, $statuses, true);

        if ($allServed && $order->status !== Order::STATUS_BILLED) {
            $order->update(['status' => Order::STATUS_SERVED, 'served_at' => now()]);
        } elseif ($anyReady && $order->status === Order::STATUS_PREPARING) {
            $order->update(['status' => Order::STATUS_READY]);
        } elseif (in_array($order->status, [Order::STATUS_SENT], true)) {
            $order->update(['status' => Order::STATUS_PREPARING]);
        }
    }
}
