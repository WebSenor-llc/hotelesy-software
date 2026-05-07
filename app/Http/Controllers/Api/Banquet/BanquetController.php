<?php

namespace App\Http\Controllers\Api\Banquet;

use App\Http\Controllers\Controller;
use App\Models\Banquet\BanquetBooking;
use App\Models\Banquet\BanquetHall;
use App\Models\Property;
use App\Services\Banquet\BanquetBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BanquetController extends Controller
{
    public function __construct(private readonly BanquetBookingService $service) {}

    public function halls(Request $request): JsonResponse
    {
        $q = BanquetHall::where('is_active', true);
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        return response()->json(['data' => $q->get()]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $q = BanquetBooking::query()->with('hall', 'package', 'guest', 'company');
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('from')) $q->whereDate('event_date', '>=', $request->date('from'));
        if ($request->filled('to')) $q->whereDate('event_date', '<=', $request->date('to'));
        return response()->json(['data' => $q->orderBy('event_date')->paginate($request->integer('per_page', 25))]);
    }

    public function show(BanquetBooking $booking): JsonResponse
    {
        return response()->json(['data' => $booking->load('hall', 'package', 'guest', 'company')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'hall_id' => ['required', 'integer', 'exists:banquet_halls,id'],
            'package_id' => ['nullable', 'integer', 'exists:banquet_packages,id'],
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'event_name' => ['required', 'string', 'max:200'],
            'event_type' => ['required', Rule::in(['wedding', 'reception', 'conference', 'meeting', 'birthday', 'anniversary', 'corporate', 'product_launch', 'training', 'other'])],
            'event_date' => ['required', 'date'],
            'event_start_time' => ['required', 'date_format:H:i'],
            'event_end_time' => ['required', 'date_format:H:i', 'after:event_start_time'],
            'expected_pax' => ['required', 'integer', 'min:1'],
            'beverage_amount' => ['nullable', 'numeric', 'min:0'],
            'decor_amount' => ['nullable', 'numeric', 'min:0'],
            'av_amount' => ['nullable', 'numeric', 'min:0'],
            'other_amount' => ['nullable', 'numeric', 'min:0'],
            'menu_details' => ['nullable', 'string'],
            'setup_notes' => ['nullable', 'string'],
            'special_requests' => ['nullable', 'string'],
            'sales_owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $property = Property::findOrFail($data['property_id']);
        try {
            $booking = $this->service->createEnquiry($property, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $booking], 201);
    }

    public function transition(Request $request, BanquetBooking $booking): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['tentative', 'confirmed', 'completed', 'cancelled'])],
            'actual_pax' => ['nullable', 'integer', 'min:0'],
        ]);
        try {
            $booking = $this->service->moveTo($booking, $data['status'], $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $booking]);
    }

    public function recordAdvance(Request $request, BanquetBooking $booking): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        return response()->json(['data' => $this->service->recordAdvance($booking, (float) $data['amount'])]);
    }
}
