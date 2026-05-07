<div>
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Housekeeping</h1>
    <p class="text-sm text-slate-600 mb-6">Update room cleanliness + service status across the property.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
        @foreach([
            ['vacant_clean','Vacant clean','bg-emerald-100 text-emerald-800'],
            ['vacant_dirty','Vacant dirty','bg-amber-100 text-amber-800'],
            ['occupied_clean','Occupied','bg-rose-100 text-rose-800'],
            ['occupied_dirty','Occ. dirty','bg-orange-100 text-orange-800'],
            ['inspected','Inspected','bg-sky-100 text-sky-800'],
            ['out_of_order','OOO/OOS','bg-slate-200 text-slate-700'],
        ] as $stat)
            <div class="rounded-xl p-3 {{ $stat[2] }}">
                <div class="text-2xl font-bold">{{ $stats[$stat[0]] ?? 0 }}</div>
                <div class="text-xs">{{ $stat[1] }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-4 mb-4 flex flex-wrap gap-3 items-center">
        <label class="text-xs font-medium text-slate-700">Floor:</label>
        <select wire:model.live="floorFilter" class="px-3 py-1.5 border border-slate-300 rounded text-sm">
            <option value="">All floors</option>
            @foreach($floors as $f)<option value="{{ $f }}">Floor {{ $f }}</option>@endforeach
        </select>
        <button type="button" wire:click="startCreateTask" class="ml-auto bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded text-sm font-semibold">+ Create cleaning task</button>
        <button type="button" wire:click="startLostFound" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded text-sm font-semibold">+ Log lost &amp; found</button>
    </div>

    @if($showTaskForm)
        <form wire:submit.prevent="saveTask" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4 mb-6">
            <h2 class="md:col-span-3 font-semibold">New cleaning task</h2>
            <div><label class="block text-xs font-medium mb-1">Room</label>
                <select wire:model="task_room_id" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">— None —</option>@foreach($allRooms as $r)<option value="{{ $r->id }}">{{ $r->number }}</option>@endforeach</select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Task type *</label>
                <select wire:model="task_type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="departure_clean">Departure clean</option>
                    <option value="turn_down">Turn-down</option>
                    <option value="stayover_clean">Stayover clean</option>
                    <option value="inspection">Inspection</option>
                    <option value="deep_clean">Deep clean</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="minibar_restock">Minibar restock</option>
                    <option value="linen_change">Linen change</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Priority</label>
                <select wire:model="task_priority" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="low">Low</option><option value="normal">Normal</option>
                    <option value="high">High</option><option value="urgent">Urgent</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Assigned to</label>
                <select wire:model="task_assigned_to" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">— Unassigned —</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Scheduled date *</label><input type="date" wire:model="task_scheduled_date" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Notes</label><textarea wire:model="task_notes" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="md:col-span-3 flex justify-end gap-2 pt-3 border-t">
                <button type="button" wire:click="cancelTask" class="px-4 py-2 text-sm">Cancel</button>
                <button class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Create task</button>
            </div>
        </form>
    @endif

    @if($showLostFoundForm)
        <form wire:submit.prevent="saveLostFound" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4 mb-6">
            <h2 class="md:col-span-3 font-semibold">Log lost &amp; found item</h2>
            <div><label class="block text-xs font-medium mb-1">Room</label>
                <select wire:model="lf_room_id" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">— Unknown —</option>@foreach($allRooms as $r)<option value="{{ $r->id }}">{{ $r->number }}</option>@endforeach</select>
            </div>
            <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Item description *</label><input type="text" wire:model="lf_item_description" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Black leather wallet">@error('lf_item_description')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror</div>
            <div><label class="block text-xs font-medium mb-1">Found location</label><input type="text" wire:model="lf_found_location" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Bathroom counter"></div>
            <div><label class="block text-xs font-medium mb-1">Found by</label>
                <select wire:model="lf_found_by" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">— Self —</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
            </div>
            <div><label class="block text-xs font-medium mb-1">Status *</label>
                <select wire:model="lf_status" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="stored">Stored</option><option value="returned">Returned</option>
                    <option value="donated">Donated</option><option value="disposed">Disposed</option>
                </select>
            </div>
            <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Notes</label><textarea wire:model="lf_notes" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="md:col-span-3 flex justify-end gap-2 pt-3 border-t">
                <button type="button" wire:click="cancelLostFound" class="px-4 py-2 text-sm">Cancel</button>
                <button class="bg-amber-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Log item</button>
            </div>
        </form>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-4 py-3 font-semibold">Room</th>
                    <th class="px-4 py-3 font-semibold">Floor</th>
                    <th class="px-4 py-3 font-semibold">Type</th>
                    <th class="px-4 py-3 font-semibold">Current status</th>
                    <th class="px-4 py-3 font-semibold">Quick actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($rooms as $room)
                    @php
                        $cls = match($room->status) {
                            'occupied_clean','occupied_dirty' => 'bg-rose-100 text-rose-700',
                            'vacant_dirty' => 'bg-amber-100 text-amber-700',
                            'out_of_order','out_of_service','blocked' => 'bg-slate-200 text-slate-600',
                            'inspected' => 'bg-sky-100 text-sky-700',
                            default => 'bg-emerald-100 text-emerald-700',
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-2.5 font-bold text-slate-900">{{ $room->number }}</td>
                        <td class="px-4 py-2.5">F{{ $room->floor }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $room->roomType?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $cls }}">{{ str_replace('_',' ',$room->status) }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex gap-1.5 flex-wrap">
                                <button type="button" wire:click="setRoomStatus({{ $room->id }}, 'vacant_clean')" class="text-[10px] px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded">Clean</button>
                                <button type="button" wire:click="setRoomStatus({{ $room->id }}, 'vacant_dirty')" class="text-[10px] px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded">Dirty</button>
                                <button type="button" wire:click="setRoomStatus({{ $room->id }}, 'inspected')" class="text-[10px] px-2 py-1 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded">Inspected</button>
                                <button type="button" wire:click="setRoomStatus({{ $room->id }}, 'out_of_order')" class="text-[10px] px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded">OOO</button>
                                <button type="button" wire:click="assignClean({{ $room->id }})" class="text-[10px] px-2 py-1 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded">Assign clean</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Recent tasks --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Recent housekeeping tasks</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Room</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Priority</th><th class="px-4 py-2">Assigned</th><th class="px-4 py-2">Scheduled</th><th class="px-4 py-2">Status</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentTasks as $t)
                    <tr>
                        <td class="px-5 py-2 font-bold">{{ $t->room?->number ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs">{{ str_replace('_',' ',$t->task_type) }}</td>
                        <td class="px-4 py-2 text-xs"><span class="px-2 py-0.5 rounded {{ $t->priority === 'urgent' ? 'bg-rose-100 text-rose-700' : ($t->priority === 'high' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $t->priority }}</span></td>
                        <td class="px-4 py-2 text-xs">{{ $t->assignedTo?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs text-slate-500">{{ $t->scheduled_date?->format('d M') }}</td>
                        <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $t->status === 'verified' ? 'bg-emerald-100 text-emerald-700' : ($t->status === 'completed' ? 'bg-sky-100 text-sky-700' : ($t->status === 'in_progress' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600')) }}">{{ str_replace('_',' ',$t->status) }}</span></td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @if($t->status === 'pending')
                                <button type="button" wire:click="startTask({{ $t->id }})" class="text-xs text-amber-600">Start</button>
                            @endif
                            @if(in_array($t->status, ['pending','in_progress']))
                                <button type="button" wire:click="completeTask({{ $t->id }})" class="text-xs text-emerald-600">Mark complete</button>
                            @endif
                            @if($t->status === 'completed')
                                <button type="button" wire:click="markInspected({{ $t->id }})" class="text-xs text-sky-600">Verify</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">No tasks yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Lost & found --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold text-slate-900">Lost &amp; found</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Found</th><th class="px-4 py-2">Room</th><th class="px-4 py-2">Item</th><th class="px-4 py-2">Location</th><th class="px-4 py-2">By</th><th class="px-4 py-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($lostFound as $lf)
                    <tr>
                        <td class="px-5 py-2 text-xs text-slate-500">{{ \Carbon\Carbon::parse($lf->found_date)->format('d M') }}</td>
                        <td class="px-4 py-2 font-bold">{{ $lf->room_number ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $lf->item_description }}</td>
                        <td class="px-4 py-2 text-xs text-slate-500">{{ $lf->found_location ?: '—' }}</td>
                        <td class="px-4 py-2 text-xs">{{ $lf->found_by_name ?? '—' }}</td>
                        <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $lf->status === 'returned' ? 'bg-emerald-100 text-emerald-700' : ($lf->status === 'stored' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-700') }}">{{ $lf->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No lost &amp; found items logged.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
