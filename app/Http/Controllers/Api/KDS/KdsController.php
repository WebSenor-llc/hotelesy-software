<?php

namespace App\Http\Controllers\Api\KDS;

use App\Http\Controllers\Controller;
use App\Models\KDS\KdsStation;
use App\Models\KDS\KdsTicket;
use App\Services\POS\KdsTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KdsController extends Controller
{
    public function __construct(private readonly KdsTicketService $tickets) {}

    /**
     * GET /api/kds/stations
     */
    public function stations(Request $request): JsonResponse
    {
        $query = KdsStation::query()->where('is_active', true);
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->integer('property_id'));
        }
        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->integer('outlet_id'));
        }
        return response()->json(['data' => $query->get()]);
    }

    /**
     * GET /api/kds/stations/{station}/queue
     * Returns active tickets sorted by priority then queued_at.
     * Each ticket includes urgency tier so the client can color it appropriately.
     */
    public function queue(KdsStation $station): JsonResponse
    {
        $queue = $this->tickets->activeQueue($station)->map(function (KdsTicket $t) {
            $arr = $t->toArray();
            $arr['urgency_tier'] = $t->urgencyTier();
            $arr['age_seconds'] = $t->ageSeconds();
            return $arr;
        });

        return response()->json([
            'data' => $queue,
            'station' => $station,
        ]);
    }

    public function startTicket(KdsTicket $ticket): JsonResponse
    {
        try {
            $t = $this->tickets->start($ticket);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $t]);
    }

    public function markReady(KdsTicket $ticket): JsonResponse
    {
        try {
            $t = $this->tickets->markReady($ticket);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $t]);
    }

    public function markServed(KdsTicket $ticket): JsonResponse
    {
        try {
            $t = $this->tickets->markServed($ticket);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $t]);
    }

    public function recall(Request $request, KdsTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);
        try {
            $t = $this->tickets->recall($ticket, $data['reason']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $t]);
    }

    public function setPriority(Request $request, KdsTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'rush'])],
        ]);
        try {
            $t = $this->tickets->setPriority($ticket, $data['priority']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $t]);
    }
}
