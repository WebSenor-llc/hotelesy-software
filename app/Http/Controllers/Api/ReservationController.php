<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\Reservation\AvailabilityService;
use App\Services\Reservation\InventoryUnavailableException;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $service,
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * GET /api/reservations
     * Filterable & paginated list. Powers reservation query screen, arrival list,
     * departure list, no-show list.
     */
    public function index(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(Reservation::class)
            ->allowedFilters([
                'status',
                'source_type',
                AllowedFilter::exact('property_id'),
                AllowedFilter::exact('guest_id'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::callback('arriving_on', fn($q, $v) => $q->whereDate('arrival_date', $v)),
                AllowedFilter::callback('departing_on', fn($q, $v) => $q->whereDate('departure_date', $v)),
                AllowedFilter::callback('in_house_on', fn($q, $v) => $q->inHouseOn($v)),
                AllowedFilter::callback('search', function ($q, $v) {
                    $q->where(function ($qq) use ($v) {
                        $qq->where('reservation_number', 'ilike', "%{$v}%")
                            ->orWhere('guest_name', 'ilike', "%{$v}%")
                            ->orWhere('guest_phone', 'ilike', "%{$v}%")
                            ->orWhere('guest_email', 'ilike', "%{$v}%")
                            ->orWhere('ota_booking_id', 'ilike', "%{$v}%");
                    });
                }),
            ])
            ->allowedSorts(['arrival_date', 'departure_date', 'created_at', 'reservation_number'])
            ->defaultSort('-created_at')
            ->with(['rooms.roomType', 'guest', 'company']);

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    /**
     * GET /api/reservations/{id}
     */
    public function show(Reservation $reservation): JsonResponse
    {
        return response()->json([
            'data' => $reservation->load([
                'rooms.roomType',
                'rooms.ratePlan',
                'rooms.room',
                'nights',
                'folios.charges',
                'folios.payments',
                'guest',
                'company',
            ]),
        ]);
    }

    /**
     * POST /api/reservations
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        try {
            $reservation = $this->service->create($request->validated());
        } catch (InventoryUnavailableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'inventory_unavailable',
            ], 409);
        }

        return response()->json([
            'data' => $reservation,
            'message' => 'Reservation created.',
        ], 201);
    }

    /**
     * POST /api/reservations/{id}/cancel
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'charge' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $reservation = $this->service->cancel(
                $reservation,
                $data['reason'] ?? null,
                $data['charge'] ?? 0,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $reservation]);
    }

    /**
     * POST /api/reservations/{id}/no-show
     */
    public function noShow(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'charge' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $reservation = $this->service->markNoShow($reservation, $data['charge'] ?? 0);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $reservation]);
    }

    /**
     * GET /api/properties/{property}/availability?from=&to=
     * Returns the availability matrix for the tape chart.
     */
    public function availability(Request $request, Property $property): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $matrix = $this->availability->gridForProperty(
            $property,
            Carbon::parse($data['from']),
            Carbon::parse($data['to']),
        );

        return response()->json(['data' => $matrix]);
    }
}
