<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
