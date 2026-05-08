<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
