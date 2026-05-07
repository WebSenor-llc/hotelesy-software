<?php

namespace App\Livewire\Housekeeping;

use App\Models\Housekeeping\HousekeepingTask;
use App\Models\Room;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class RoomBoard extends Component
{
    public string $floorFilter = '';

    // Cleaning task form
    public bool $showTaskForm = false;
    public ?int $task_room_id = null;
    public string $task_type = 'departure_clean';
    public string $task_priority = 'normal';
    public ?int $task_assigned_to = null;
    public string $task_scheduled_date = '';
    public string $task_notes = '';

    // Lost & found form
    public bool $showLostFoundForm = false;
    public ?int $lf_room_id = null;
    public string $lf_item_description = '';
    public string $lf_found_location = '';
    public ?int $lf_found_by = null;
    public string $lf_status = 'stored';
    public string $lf_notes = '';

    public function mount(): void
    {
        $this->task_scheduled_date = today()->toDateString();
    }

    public function setRoomStatus(int $roomId, string $status): void
    {
        $valid = ['vacant_clean','vacant_dirty','occupied_clean','occupied_dirty','inspected','out_of_order','out_of_service','blocked'];
        if (!in_array($status, $valid)) return;

        $ctx = app(TenantContext::class);
        $room = Room::where('property_id', $ctx->propertyId())->findOrFail($roomId);
        $room->update(['status' => $status]);
        session()->flash('success', "Room {$room->number} marked as " . str_replace('_',' ', $status) . '.');
    }

    /** Quick action: Start (assign self) and immediately complete a cleaning task as "Assign clean" workflow. */
    public function assignClean(int $roomId, ?int $userId = null): void
    {
        $ctx = app(TenantContext::class);
        $room = Room::where('property_id', $ctx->propertyId())->findOrFail($roomId);
        $assignee = $userId ?? auth()->id();

        HousekeepingTask::create([
            'property_id' => $ctx->propertyId(),
            'room_id' => $room->id,
            'task_type' => HousekeepingTask::TYPE_DEPARTURE_CLEAN,
            'priority' => HousekeepingTask::PRIORITY_NORMAL,
            'status' => HousekeepingTask::STATUS_PENDING,
            'assigned_to' => $assignee,
            'scheduled_date' => today()->toDateString(),
        ]);
        session()->flash('success', "Cleaning task created for room {$room->number}.");
    }

    /** Mark a completed task as inspected/verified. */
    public function markInspected(int $taskId): void
    {
        $task = HousekeepingTask::findOrFail($taskId);
        try {
            $task->verify(auth()->id() ?? 0);
            session()->flash('success', "Task verified.");
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function startTask(int $taskId): void
    {
        $task = HousekeepingTask::findOrFail($taskId);
        try {
            $task->start(auth()->id());
            session()->flash('success', "Task started.");
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeTask(int $taskId): void
    {
        $task = HousekeepingTask::findOrFail($taskId);
        try {
            if ($task->status === HousekeepingTask::STATUS_PENDING) {
                $task->start(auth()->id());
            }
            $task->complete();
            session()->flash('success', "Task marked complete.");
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /* ---------- Task creation form ---------- */

    public function startCreateTask(): void
    {
        $this->reset(['task_room_id','task_assigned_to','task_notes']);
        $this->task_type = 'departure_clean';
        $this->task_priority = 'normal';
        $this->task_scheduled_date = today()->toDateString();
        $this->showTaskForm = true;
        $this->showLostFoundForm = false;
    }

    public function cancelTask(): void { $this->showTaskForm = false; }

    public function saveTask(): void
    {
        $data = $this->validate([
            'task_room_id' => 'nullable|exists:rooms,id',
            'task_type' => 'required|string',
            'task_priority' => 'required|string',
            'task_assigned_to' => 'nullable|exists:users,id',
            'task_scheduled_date' => 'required|date',
            'task_notes' => 'nullable|string|max:1000',
        ]);
        $ctx = app(TenantContext::class);
        HousekeepingTask::create([
            'property_id' => $ctx->propertyId(),
            'room_id' => $data['task_room_id'] ?: null,
            'task_type' => $data['task_type'],
            'priority' => $data['task_priority'],
            'status' => HousekeepingTask::STATUS_PENDING,
            'assigned_to' => $data['task_assigned_to'] ?: null,
            'scheduled_date' => $data['task_scheduled_date'],
            'notes' => $data['task_notes'] ?: null,
        ]);
        session()->flash('success', 'Cleaning task created.');
        $this->showTaskForm = false;
    }

    /* ---------- Lost & found form ---------- */

    public function startLostFound(): void
    {
        $this->reset(['lf_room_id','lf_item_description','lf_found_location','lf_found_by','lf_notes']);
        $this->lf_status = 'stored';
        $this->showLostFoundForm = true;
        $this->showTaskForm = false;
    }

    public function cancelLostFound(): void { $this->showLostFoundForm = false; }

    public function saveLostFound(): void
    {
        $data = $this->validate([
            'lf_room_id' => 'nullable|exists:rooms,id',
            'lf_item_description' => 'required|string|max:255',
            'lf_found_location' => 'nullable|string|max:255',
            'lf_found_by' => 'nullable|exists:users,id',
            'lf_status' => 'required|in:stored,returned,donated,disposed',
            'lf_notes' => 'nullable|string|max:1000',
        ]);
        $ctx = app(TenantContext::class);
        DB::table('housekeeping_lost_found')->insert([
            'tenant_id' => $ctx->tenantId(),
            'property_id' => $ctx->propertyId(),
            'room_id' => $data['lf_room_id'] ?: null,
            'item_description' => $data['lf_item_description'],
            'found_location' => $data['lf_found_location'] ?: null,
            'found_date' => today()->toDateString(),
            'found_by' => $data['lf_found_by'] ?: auth()->id(),
            'status' => $data['lf_status'],
            'notes' => $data['lf_notes'] ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        session()->flash('success', 'Lost & found item logged.');
        $this->showLostFoundForm = false;
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $rooms = Room::where('property_id', $propertyId)
            ->when($this->floorFilter !== '', fn ($q) => $q->where('floor', $this->floorFilter))
            ->orderBy('floor')->orderBy('number')
            ->get();

        $floors = Room::where('property_id', $propertyId)->distinct()->pluck('floor')->sort()->values();

        $stats = [
            'vacant_clean'   => $rooms->where('status','vacant_clean')->count(),
            'vacant_dirty'   => $rooms->where('status','vacant_dirty')->count(),
            'occupied_clean' => $rooms->where('status','occupied_clean')->count(),
            'occupied_dirty' => $rooms->where('status','occupied_dirty')->count(),
            'inspected'      => $rooms->where('status','inspected')->count(),
            'out_of_order'   => $rooms->whereIn('status', ['out_of_order','out_of_service','blocked'])->count(),
        ];

        $recentTasks = HousekeepingTask::where('property_id', $propertyId)
            ->with(['room', 'assignedTo'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $lostFound = DB::table('housekeeping_lost_found as lf')
            ->leftJoin('rooms', 'rooms.id', '=', 'lf.room_id')
            ->leftJoin('users', 'users.id', '=', 'lf.found_by')
            ->where('lf.property_id', $propertyId)
            ->orderByDesc('lf.created_at')
            ->limit(10)
            ->select('lf.*', 'rooms.number as room_number', 'users.name as found_by_name')
            ->get();

        $allRooms = Room::where('property_id', $propertyId)->orderBy('number')->get(['id','number']);
        $users = User::orderBy('name')->limit(200)->get(['id','name']);

        return view('livewire.housekeeping.room-board', compact('rooms','floors','stats','recentTasks','lostFound','allRooms','users'));
    }
}
