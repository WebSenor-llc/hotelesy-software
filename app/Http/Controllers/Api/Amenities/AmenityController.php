<?php

namespace App\Http\Controllers\Api\Amenities;

use App\Http\Controllers\Controller;
use App\Models\Amenities\Amenity;
use App\Models\Amenities\AmenityOrder;
use App\Models\Reservation;
use App\Services\NumberGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AmenityController extends Controller
{
    public function __construct(private readonly NumberGeneratorService $numbers) {}

    public function index(Request $request): JsonResponse
    {
        $q = Amenity::where('is_active', true);
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->filled('category')) $q->where('category', $request->string('category'));
        if ($request->boolean('booking_only')) $q->where('available_at_booking', true);
        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(['transport', 'meal', 'spa', 'tour', 'experience', 'merchandise', 'utility', 'other'])],
            'pricing_type' => ['required', Rule::in(['per_stay', 'per_night', 'per_person', 'per_person_per_night', 'flat'])],
            'price' => ['required', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'available_at_booking' => ['nullable', 'boolean'],
            'available_at_checkin' => ['nullable', 'boolean'],
            'available_in_stay' => ['nullable', 'boolean'],
        ]);
        return response()->json(['data' => Amenity::create(array_merge($data, ['is_active' => true]))], 201);
    }

    public function order(Request $request, Amenity $amenity): JsonResponse
    {
        $data = $request->validate([
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'folio_id' => ['nullable', 'integer', 'exists:folios,id'],
            'service_date' => ['nullable', 'date'],
            'service_time' => ['nullable', 'date_format:H:i'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'persons' => ['nullable', 'integer', 'min:1'],
            'nights' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $quantity = $data['quantity'] ?? 1;
        $persons = $data['persons'] ?? 1;
        $nights = $data['nights'] ?? 1;
        $base = $amenity->computeTotal($nights, $persons, $quantity);
        $tax = round($base * (float) $amenity->tax_percent / 100, 2);

        $order = AmenityOrder::create([
            'property_id' => $amenity->property_id,
            'amenity_id' => $amenity->id,
            'reservation_id' => $data['reservation_id'] ?? null,
            'folio_id' => $data['folio_id'] ?? null,
            'order_number' => $this->numbers->generate($amenity->property, 'amenity_order', 'AMN/' . $amenity->property->code),
            'service_date' => $data['service_date'] ?? null,
            'service_time' => $data['service_time'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $amenity->price,
            'tax_amount' => $tax,
            'total_amount' => $base + $tax,
            'status' => 'confirmed',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $order], 201);
    }
}
