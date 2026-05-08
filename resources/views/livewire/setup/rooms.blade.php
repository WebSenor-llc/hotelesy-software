<div>
    <div class="flex items-baseline justify-between mb-1"><h1 class="text-2xl font-bold">Rooms</h1><a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a></div>
    <p class="text-sm text-slate-600 mb-6">Physical rooms — number, floor, type, status, smoking/accessibility flags.</p>
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap gap-3 items-center">
        <input type="search" wire:model.live.debounce.300ms="filter" placeholder="Filter by room number…" class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg text-sm">
        <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add room</button>
        <button type="button" wire:click="startBulkCreate" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Bulk create</button>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4 mb-6">
            <h2 class="md:col-span-3 font-semibold">{{ $editId ? 'Edit' : 'New' }} room</h2>
            <div><label class="block text-xs font-medium mb-1">Room # *</label><input type="text" wire:model="number" class="w-full px-3 py-2 border rounded-lg text-sm">@error('number')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror</div>
            <div><label class="block text-xs font-medium mb-1">Room type *</label>
                <select wire:model="room_type_id" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select…</option>@foreach($roomTypes as $rt)<option value="{{ $rt->id }}">{{ $rt->name }}</option>@endforeach</select>
                @error('room_type_id')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div><label class="block text-xs font-medium mb-1">Floor</label><input type="number" wire:model="floor" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Wing / Block</label><input type="text" wire:model="wing" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">View</label><input type="text" wire:model="view" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Lake / Garden / Pool"></div>
            <div><label class="block text-xs font-medium mb-1">Status *</label>
                <select wire:model="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="vacant_clean">Vacant clean</option><option value="vacant_dirty">Vacant dirty</option>
                    <option value="occupied_clean">Occupied clean</option><option value="occupied_dirty">Occupied dirty</option>
                    <option value="inspected">Inspected</option><option value="out_of_order">Out of order</option>
                    <option value="out_of_service">Out of service</option><option value="blocked">Blocked</option>
                </select>
            </div>
            <div><label class="block text-xs font-medium mb-1">FO status *</label>
                <select wire:model="fo_status" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="vacant">Vacant</option><option value="reserved">Reserved</option>
                    <option value="occupied">Occupied</option><option value="due_out">Due out</option><option value="on_request">On request</option>
                </select>
            </div>
            {{-- Image upload + notes --}}
            <div class="md:col-span-3 grid md:grid-cols-2 gap-4 pt-3 border-t">
                <div>
                    <label class="block text-xs font-medium mb-1">Room image</label>
                    @if($existingImagePath && !$imageUpload)
                        <div class="flex items-start gap-3 mb-2">
                            <img src="{{ asset('storage/'.$existingImagePath) }}" alt="Room image"
                                 class="w-32 h-24 object-cover rounded-lg border border-slate-200">
                            <button type="button" wire:click="removeImage" class="text-xs text-rose-600 hover:underline">Remove</button>
                        </div>
                    @elseif($imageUpload)
                        <div class="mb-2"><img src="{{ $imageUpload->temporaryUrl() }}" class="w-32 h-24 object-cover rounded-lg border border-slate-200"></div>
                    @endif
                    <input type="file" wire:model="imageUpload" accept="image/*" class="w-full px-3 py-2 border rounded-lg text-xs">
                    <p class="text-[10px] text-slate-500 mt-1">JPG / PNG / WebP up to 4 MB. Used on the room board, booking engine, and reports.</p>
                    @error('imageUpload')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                    <div wire:loading wire:target="imageUpload" class="text-xs text-amber-600 mt-1">Uploading…</div>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Internal notes</label>
                    <textarea wire:model="notes" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Quirks, do-not-sell flags, repair history…"></textarea>
                </div>
            </div>

            <div class="md:col-span-3 flex flex-wrap gap-4 pt-3 border-t">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_smoking" class="rounded">Smoking</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_accessible" class="rounded">Accessible</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                <div class="ml-auto flex gap-2">
                    <button type="button" wire:click="cancelForm" class="px-4 py-2 text-sm">Cancel</button>
                    <button class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save</button>
                </div>
            </div>
        </form>
    @endif

    @if($showBulkForm)
        <form wire:submit.prevent="bulkSave" class="bg-white rounded-xl border border-slate-300 p-6 grid md:grid-cols-3 gap-4 mb-6">
            <h2 class="md:col-span-3 font-semibold">Bulk-create rooms</h2>
            <p class="md:col-span-3 text-xs text-slate-500 -mt-2">Generates rooms numbered <code>{prefix}{NN}</code> — e.g. prefix "1", start 1, count 10 produces 101..110.</p>
            <div><label class="block text-xs font-medium mb-1">Floor *</label><input type="number" wire:model="bulk_floor" class="w-full px-3 py-2 border rounded-lg text-sm">@error('bulk_floor')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror</div>
            <div><label class="block text-xs font-medium mb-1">Wing / Block</label><input type="text" wire:model="bulk_wing" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Room type *</label>
                <select wire:model="bulk_room_type_id" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select…</option>@foreach($roomTypes as $rt)<option value="{{ $rt->id }}">{{ $rt->name }}</option>@endforeach</select>
                @error('bulk_room_type_id')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div><label class="block text-xs font-medium mb-1">Prefix</label><input type="text" wire:model="bulk_prefix" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="1"></div>
            <div><label class="block text-xs font-medium mb-1">Starting number *</label><input type="number" wire:model="bulk_start" min="0" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Count *</label><input type="number" wire:model="bulk_count" min="1" max="200" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium mb-1">Initial HK status</label>
                <select wire:model="bulk_status" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="vacant_clean">Vacant clean</option>
                    <option value="vacant_dirty">Vacant dirty</option>
                    <option value="out_of_order">Out of order</option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end gap-2 pt-3 border-t">
                <button type="button" wire:click="cancelBulk" class="px-4 py-2 text-sm">Cancel</button>
                <button class="bg-slate-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Create rooms</button>
            </div>
        </form>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-3 py-2">Photo</th>
                    <th class="px-3 py-2">Room #</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Floor / Wing</th>
                    <th class="px-4 py-2">View</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Flags</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rooms as $r)
                <tr>
                    <td class="px-3 py-2">
                        @if($r->image_path)
                            <img src="{{ asset('storage/'.$r->image_path) }}" class="w-14 h-10 object-cover rounded border border-slate-200" alt="Room {{ $r->number }}">
                        @else
                            <div class="w-14 h-10 rounded border border-dashed border-slate-300 flex items-center justify-center text-[10px] text-slate-400 bg-slate-50">No img</div>
                        @endif
                    </td>
                    <td class="px-3 py-2 font-bold">{{ $r->number }}</td>
                    <td class="px-4 py-2">{{ $r->roomType?->name }}</td>
                    <td class="px-4 py-2 text-xs">F{{ $r->floor }}{{ $r->wing ? ' · '.$r->wing : '' }}</td>
                    <td class="px-4 py-2 text-xs">{{ $r->view ?: '—' }}</td>
                    <td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100">{{ str_replace('_',' ',$r->status) }}</span></td>
                    <td class="px-4 py-2 text-xs">
                        @if($r->is_smoking)<span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded mr-1">Smoking</span>@endif
                        @if($r->is_accessible)<span class="px-1.5 py-0.5 bg-sky-100 text-sky-700 rounded mr-1">♿</span>@endif
                        @if(!$r->is_active)<span class="px-1.5 py-0.5 bg-slate-100 rounded">Inactive</span>@endif
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <button type="button" wire:click="startEdit({{ $r->id }})" class="text-xs text-brand-600">Edit</button>
                        <button type="button" wire:click="delete({{ $r->id }})" wire:confirm="Delete room {{ $r->number }}? This cannot be undone." class="text-xs text-rose-600">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No rooms.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
