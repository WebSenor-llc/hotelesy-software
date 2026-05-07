<?php

namespace App\Services\Housekeeping;

use App\Models\Housekeeping\HousekeepingTask;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HousekeepingService — task generation, assignment, and room-status sync.
 *
 * Auto-generation rules:
 *   - Reservation checked out → vacant_dirty room → auto-create departure_clean task
 *   - Reservation checked_in for stayover → daily stayover_clean task at 9 AM
 *   - VIP arrival today → high-priority pre-arrival inspection task
 *
 * Auto-assignment uses round-robin among on-shift housekeepers, weighted by
 * current open task count. For Phase 1 we use simple least-loaded.
 *
 * The `daily-tasks` command runs at midnight to seed today's tasks.
 */
class HousekeepingService
{
    /**
     * Generate today's housekeeping task list for a property.
     * Idempotent — duplicates skipped.
     */
    public function generateDailyTasks(Property $property, ?Carbon $forDate = null): int
    {
        $forDate = $forDate?->startOfDay() ?? now($property->timezone)->startOfDay();
        $count = 0;

        return DB::transaction(function () use ($property, $forDate, &$count) {
            // 1. Departures today → departure_clean
            $departures = Reservation::where('property_id', $property->id)
                ->whereDate('departure_date', $forDate)
                ->whereIn('status', [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])
                ->with('rooms.room')
                ->get();

            foreach ($departures as $resv) {
                foreach ($resv->rooms as $resRoom) {
                    if (! $resRoom->room_id) continue;
                    $count += $this->ensureTask($property, [
                        'room_id' => $resRoom->room_id,
                        'task_type' => HousekeepingTask::TYPE_DEPARTURE_CLEAN,
                        'priority' => HousekeepingTask::PRIORITY_HIGH,
                        'scheduled_date' => $forDate->toDateString(),
                        'notes' => "Departure clean — Reservation {$resv->reservation_number}",
                    ]);
                }
            }

            // 2. In-house stayovers → stayover_clean
            $inHouse = Reservation::where('property_id', $property->id)
                ->whereDate('arrival_date', '<=', $forDate)
                ->whereDate('departure_date', '>', $forDate)
                ->where('status', Reservation::STATUS_CHECKED_IN)
                ->with('rooms.room')
                ->get();

            foreach ($inHouse as $resv) {
                foreach ($resv->rooms as $resRoom) {
                    if (! $resRoom->room_id) continue;
                    $count += $this->ensureTask($property, [
                        'room_id' => $resRoom->room_id,
                        'task_type' => HousekeepingTask::TYPE_STAYOVER,
                        'priority' => $resv->is_vip
                            ? HousekeepingTask::PRIORITY_HIGH
                            : HousekeepingTask::PRIORITY_NORMAL,
                        'scheduled_date' => $forDate->toDateString(),
                        'notes' => "Stayover — Reservation {$resv->reservation_number}" . ($resv->is_vip ? ' [VIP]' : ''),
                    ]);
                }
            }

            // 3. VIP arrivals → pre-arrival inspection
            $vipArrivals = Reservation::where('property_id', $property->id)
                ->whereDate('arrival_date', $forDate)
                ->where('is_vip', true)
                ->with('rooms.room')
                ->get();

            foreach ($vipArrivals as $resv) {
                foreach ($resv->rooms as $resRoom) {
                    if (! $resRoom->room_id) continue;
                    $count += $this->ensureTask($property, [
                        'room_id' => $resRoom->room_id,
                        'task_type' => HousekeepingTask::TYPE_INSPECTION,
                        'priority' => HousekeepingTask::PRIORITY_URGENT,
                        'scheduled_date' => $forDate->toDateString(),
                        'notes' => "VIP pre-arrival inspection — {$resv->guest_name}",
                    ]);
                }
            }

            return $count;
        });
    }

    public function autoAssign(Property $property, ?Carbon $forDate = null): int
    {
        $forDate = $forDate?->toDateString() ?? now()->toDateString();

        $unassigned = HousekeepingTask::where('property_id', $property->id)
            ->whereNull('assigned_to')
            ->whereDate('scheduled_date', $forDate)
            ->where('status', HousekeepingTask::STATUS_PENDING)
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->get();

        if ($unassigned->isEmpty()) return 0;

        // Find on-shift housekeepers (users with role 'housekeeping')
        $housekeepers = \App\Models\User::role('housekeeping')
            ->where('tenant_id', $property->tenant_id)
            ->where('is_active', true)
            ->get();

        if ($housekeepers->isEmpty()) return 0;

        $loads = [];
        foreach ($housekeepers as $hk) {
            $loads[$hk->id] = HousekeepingTask::where('assigned_to', $hk->id)
                ->whereIn('status', [HousekeepingTask::STATUS_PENDING, HousekeepingTask::STATUS_IN_PROGRESS])
                ->count();
        }

        $assigned = 0;
        foreach ($unassigned as $task) {
            // Pick least-loaded housekeeper
            asort($loads);
            $hkId = array_key_first($loads);
            $task->update(['assigned_to' => $hkId]);
            $loads[$hkId]++;
            $assigned++;
        }

        return $assigned;
    }

    /**
     * Idempotent task creation. Skips if same room + type + date already exists.
     */
    private function ensureTask(Property $property, array $data): int
    {
        $exists = HousekeepingTask::where('property_id', $property->id)
            ->where('room_id', $data['room_id'])
            ->where('task_type', $data['task_type'])
            ->whereDate('scheduled_date', $data['scheduled_date'])
            ->exists();

        if ($exists) return 0;

        HousekeepingTask::create(array_merge(
            ['property_id' => $property->id],
            $data,
            ['status' => HousekeepingTask::STATUS_PENDING],
        ));
        return 1;
    }
}
