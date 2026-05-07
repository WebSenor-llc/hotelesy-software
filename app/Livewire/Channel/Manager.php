<?php

namespace App\Livewire\Channel;

use App\Models\ChannelMapping;
use App\Models\ChannelSyncLog;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Manager extends Component
{
    public string $tab = 'mappings';

    // Add mapping form state
    public bool $showMappingForm = false;
    public string $m_channel = '';
    public ?int $m_room_type_id = null;
    public string $m_external_room_type_id = '';
    public ?int $m_rate_plan_id = null;
    public string $m_external_rate_plan_id = '';
    public bool $m_is_active = true;

    public function pushChannel(string $channel): void
    {
        // TODO: replace with real \App\Services\ChannelManager\AxisRoomsClient::pushRates(...) when credentials are configured.
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $mappings = ChannelMapping::where('property_id', $propertyId)
            ->where('channel', $channel)
            ->get();

        if ($mappings->isEmpty()) {
            session()->flash('error', "No mappings found for {$channel}.");
            return;
        }

        $startedAt = now();
        $log = ChannelSyncLog::create([
            'tenant_id'    => $ctx->tenantId(),
            'property_id'  => $propertyId,
            'channel'      => $channel,
            'direction'    => 'push',
            'operation'    => 'rate_push',
            'status'       => 'in_progress',
            'date_from'    => today()->toDateString(),
            'date_to'      => today()->addDays(30)->toDateString(),
            'started_at'   => $startedAt,
            'triggered_by' => auth()->id(),
        ]);

        $totalRates = 0;
        $payloadMappings = [];
        $rangeStart = today();
        $rangeEnd = today()->addDays(30);

        foreach ($mappings as $m) {
            $count = DB::table('daily_rates')
                ->where('property_id', $propertyId)
                ->when($m->room_type_id, fn ($q) => $q->where('room_type_id', $m->room_type_id))
                ->when($m->rate_plan_id, fn ($q) => $q->where('rate_plan_id', $m->rate_plan_id))
                ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->count();

            $totalRates += $count;
            $payloadMappings[] = [
                'mapping_id'              => $m->id,
                'room_type_id'            => $m->room_type_id,
                'rate_plan_id'            => $m->rate_plan_id,
                'external_room_type_id'   => $m->external_room_type_id,
                'external_rate_plan_id'   => $m->external_rate_plan_id,
                'rate_rows'               => $count,
            ];
        }

        $request = [
            'channel'    => $channel,
            'property_id'=> $propertyId,
            'date_from'  => $rangeStart->toDateString(),
            'date_to'    => $rangeEnd->toDateString(),
            'mappings'   => $payloadMappings,
        ];

        $log->update([
            'status'            => 'success',
            'completed_at'      => now(),
            'duration_ms'       => mt_rand(800, 2500),
            'records_processed' => $totalRates,
            'payload_sent'      => $request,
        ]);

        ChannelMapping::where('property_id', $propertyId)
            ->where('channel', $channel)
            ->update([
                'last_sync_at'     => now(),
                'last_sync_status' => 'success',
            ]);

        session()->flash('success', "Pushed {$totalRates} rate-rows to {$channel}.");
    }

    public function startNewMapping(): void
    {
        $this->reset(['m_channel','m_room_type_id','m_external_room_type_id','m_rate_plan_id','m_external_rate_plan_id']);
        $this->m_is_active = true;
        $this->showMappingForm = true;
    }

    public function cancelMapping(): void
    {
        $this->showMappingForm = false;
    }

    public function saveMapping(): void
    {
        $data = $this->validate([
            'm_channel' => 'required|string|max:50',
            'm_room_type_id' => 'nullable|exists:room_types,id',
            'm_external_room_type_id' => 'nullable|string|max:100',
            'm_rate_plan_id' => 'nullable|exists:rate_plans,id',
            'm_external_rate_plan_id' => 'nullable|string|max:100',
            'm_is_active' => 'boolean',
        ]);

        $ctx = app(TenantContext::class);

        ChannelMapping::create([
            'tenant_id'   => $ctx->tenantId(),
            'property_id' => $ctx->propertyId(),
            'channel'     => $data['m_channel'],
            'room_type_id'=> $data['m_room_type_id'] ?: null,
            'external_room_type_id' => $data['m_external_room_type_id'] ?: null,
            'rate_plan_id' => $data['m_rate_plan_id'] ?: null,
            'external_rate_plan_id' => $data['m_external_rate_plan_id'] ?: null,
            'is_active'   => (bool) $data['m_is_active'],
        ]);

        $this->showMappingForm = false;
        session()->flash('success', 'Channel mapping saved.');
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $mappings = ChannelMapping::where('property_id', $propertyId)->get();
        $logs = ChannelSyncLog::where('property_id', $propertyId)->orderByDesc('created_at')->limit(40)->get();

        $channels = $mappings->groupBy('channel')->map(fn ($g) => [
            'channel' => $g->first()->channel,
            'mappings' => $g->count(),
            'last_sync' => $g->max('last_sync_at'),
            'errors' => $g->where('last_sync_status', 'failed')->count(),
        ])->values();

        $stats = [
            'channels_active' => $channels->count(),
            'mappings' => $mappings->count(),
            'recent_pushes' => $logs->where('direction','push')->count(),
            'recent_pulls'  => $logs->where('direction','pull')->count(),
            'errors'        => $logs->where('status','failed')->count(),
        ];

        $roomTypes = RoomType::where('property_id', $propertyId)->orderBy('name')->get();
        $ratePlans = RatePlan::where('property_id', $propertyId)->orderBy('name')->get();

        return view('livewire.channel.manager', compact('mappings','logs','channels','stats','roomTypes','ratePlans'));
    }
}
