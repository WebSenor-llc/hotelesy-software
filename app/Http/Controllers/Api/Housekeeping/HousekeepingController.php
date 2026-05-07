<?php

namespace App\Http\Controllers\Api\Housekeeping;

use App\Http\Controllers\Controller;
use App\Models\Housekeeping\HousekeepingTask;
use App\Models\Property;
use App\Services\Housekeeping\HousekeepingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    public function __construct(private readonly HousekeepingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = HousekeepingTask::query()->with('room', 'assignedTo');
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('assigned_to')) $q->where('assigned_to', $request->integer('assigned_to'));
        if ($request->filled('date')) $q->whereDate('scheduled_date', $request->date('date'));
        return response()->json(['data' => $q->pending()->paginate($request->integer('per_page', 50))]);
    }

    public function generateDaily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'date' => ['nullable', 'date'],
        ]);
        $property = Property::findOrFail($data['property_id']);
        $forDate = isset($data['date']) ? Carbon::parse($data['date']) : null;
        $count = $this->service->generateDailyTasks($property, $forDate);
        return response()->json(['message' => "Generated {$count} tasks.", 'count' => $count]);
    }

    public function autoAssign(Request $request): JsonResponse
    {
        $data = $request->validate(['property_id' => ['required', 'integer'], 'date' => ['nullable', 'date']]);
        $property = Property::findOrFail($data['property_id']);
        $forDate = isset($data['date']) ? Carbon::parse($data['date']) : null;
        $count = $this->service->autoAssign($property, $forDate);
        return response()->json(['message' => "Assigned {$count} tasks.", 'count' => $count]);
    }

    public function start(HousekeepingTask $task): JsonResponse
    {
        try {
            $task = $task->start(auth()->id());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $task]);
    }

    public function complete(Request $request, HousekeepingTask $task): JsonResponse
    {
        $data = $request->validate(['checklist' => ['nullable', 'array']]);
        try {
            $task = $task->complete($data['checklist'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $task]);
    }

    public function verify(HousekeepingTask $task): JsonResponse
    {
        try {
            $task = $task->verify(auth()->id());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $task]);
    }
}
