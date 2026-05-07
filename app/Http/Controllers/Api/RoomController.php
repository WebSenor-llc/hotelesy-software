<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RoomType::query();
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->integer('property_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'base_occupancy' => ['required', 'integer', 'min:1', 'max:10'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:20'],
            'extra_bed_capacity' => ['nullable', 'integer', 'min:0', 'max:5'],
            'total_rooms' => ['required', 'integer', 'min:0'],
            'default_rate' => ['required', 'numeric', 'min:0'],
            'extra_adult_charge' => ['nullable', 'numeric', 'min:0'],
            'extra_child_charge' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $roomType = RoomType::create($data);

        return response()->json(['data' => $roomType], 201);
    }

    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'base_occupancy' => ['nullable', 'integer', 'min:1'],
            'max_occupancy' => ['nullable', 'integer', 'min:1'],
            'total_rooms' => ['nullable', 'integer', 'min:0'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $roomType->update($data);

        return response()->json(['data' => $roomType->fresh()]);
    }
}

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Room::with('roomType');
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->integer('property_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:20'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:200'],
            'building' => ['nullable', 'string', 'max:50'],
            'view' => ['nullable', 'string', 'max:50'],
            'is_smoking' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in([
                'vacant_clean', 'vacant_dirty', 'occupied', 'inspected',
                'out_of_order', 'out_of_service',
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $room = Room::create($data);

        return response()->json(['data' => $room], 201);
    }

    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                'vacant_clean', 'vacant_dirty', 'occupied', 'inspected',
                'out_of_order', 'out_of_service',
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $room->update([
            'status' => $data['status'],
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['data' => $room->fresh()]);
    }
}
