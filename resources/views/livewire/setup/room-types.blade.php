<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Room types</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a>
            <button type="button" wire:click="startCreate" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">+ Add room type</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Categories with rates, occupancy, amenities, channel mapping.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr>
                    <th class="px-5 py-2.5 font-semibold">Code</th>
                    <th class="px-4 py-2.5 font-semibold">Name</th>
                    <th class="px-4 py-2.5 font-semibold">Occupancy</th>
                    <th class="px-4 py-2.5 font-semibold">Bed</th>
                    <th class="px-4 py-2.5 font-semibold text-right">Base rate</th>
                    <th class="px-4 py-2.5 font-semibold text-right">Rooms</th>
                    <th class="px-4 py-2.5 font-semibold">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($roomTypes as $rt)
                    <tr>
                        <td class="px-5 py-2.5 font-mono text-xs">{{ $rt->code }}</td>
                        <td class="px-4 py-2.5 font-medium">
                            {{ $rt->name }}
                            @php $photoCount = is_array($rt->photos) ? count($rt->photos) : 0; @endphp
                            @if($photoCount > 0)
                                <span class="ml-1 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 bg-sky-100 text-sky-700 rounded font-medium">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 0 1 3.25 3h13.5A2.25 2.25 0 0 1 19 5.25v9.5A2.25 2.25 0 0 1 16.75 17H3.25A2.25 2.25 0 0 1 1 14.75v-9.5Zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 0 0 .75-.75v-2.69l-2.22-2.219a.75.75 0 0 0-1.06 0l-1.91 1.909.47.47a.75.75 0 1 1-1.06 1.06L6.53 8.091a.75.75 0 0 0-1.06 0l-2.97 2.97ZM12 7a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z" clip-rule="evenodd" /></svg>
                                    {{ $photoCount }} {{ \Illuminate\Support\Str::plural('photo', $photoCount) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-xs">{{ $rt->base_occupancy }} base / {{ $rt->max_occupancy }} max</td>
                        <td class="px-4 py-2.5 text-xs">{{ $rt->bed_type ?: '—' }}{{ $rt->size_sqft ? ' · '.$rt->size_sqft.' sqft' : '' }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold">₹{{ number_format($rt->base_rate, 0) }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $rt->rooms_count }}</td>
                        <td class="px-4 py-2.5"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $rt->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $rt->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="px-4 py-2.5 text-right space-x-2">
                            <button type="button" wire:click="startEdit({{ $rt->id }})" class="text-xs text-brand-600 font-medium">Edit</button>
                            <button type="button" wire:click="delete({{ $rt->id }})" wire:confirm="Delete '{{ $rt->name }}'?" class="text-xs text-rose-600 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">No room types yet. <button type="button" wire:click="startCreate" class="text-brand-600 font-medium">Create one →</button></td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t bg-slate-50 px-5 py-2.5">
            <button type="button" wire:click="startCreate" class="text-sm font-medium text-brand-600 hover:text-brand-700">+ Add room type</button>
        </div>
    </div>

    @if($showForm)
        <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 space-y-4">
            <h2 class="font-semibold">{{ $editId ? 'Edit room type' : 'New room type' }}</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Code *</label>
                    <input type="text" wire:model="code" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="DLX">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                    <input type="text" wire:model="name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Description</label>
                    <textarea wire:model="description" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></textarea>
                </div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Base occupancy *</label><input type="number" wire:model="base_occupancy" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Max occupancy *</label><input type="number" wire:model="max_occupancy" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Extra bed capacity</label><input type="number" wire:model="extra_bed_capacity" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Base rate (₹) *</label><input type="number" step="0.01" wire:model="base_rate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Extra adult rate</label><input type="number" step="0.01" wire:model="extra_adult_rate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Extra bed rate</label><input type="number" step="0.01" wire:model="extra_bed_rate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Bed type</label>
                    <select wire:model="bed_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="king">King</option><option value="queen">Queen</option><option value="twin">Twin</option><option value="double">Double</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium text-slate-700 mb-1">Size (sqft)</label><input type="number" step="0.01" wire:model="size_sqft" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div class="flex items-end gap-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded">Active</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="sell_on_channels" class="rounded">Sell on OTAs</label>
                </div>
                <div class="md:col-span-3 flex flex-wrap items-end gap-4 pt-3 border-t border-slate-200">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="allow_overbook" class="rounded">
                        Allow overbooking
                    </label>
                    @if($allow_overbook)
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Overbook limit</label>
                            <input type="number" min="0" max="50" wire:model="overbook_limit" class="w-32 px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="2">
                            <span class="ml-1 text-[11px] text-slate-500">extra room(s) beyond inventory</span>
                        </div>
                    @endif
                </div>

                {{-- Photo gallery --}}
                <div class="md:col-span-3 pt-3 border-t border-slate-200">
                    <label class="block text-xs font-medium text-slate-700 mb-2">Photos</label>

                    @if(!empty($existingPhotos) || !empty($newPhotos))
                        <div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-3">
                            @foreach($existingPhotos as $i => $path)
                                <div class="relative group aspect-square bg-slate-100 rounded-lg overflow-hidden border border-slate-200">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($path) }}" alt="" class="w-full h-full object-cover">
                                    <button type="button" wire:click="removeExistingPhoto({{ $i }})" wire:confirm="Remove this photo?"
                                        class="absolute top-1 right-1 w-6 h-6 bg-rose-600 hover:bg-rose-700 text-white rounded-full text-xs font-bold leading-none flex items-center justify-center shadow opacity-90 hover:opacity-100">×</button>
                                </div>
                            @endforeach
                            @foreach($newPhotos as $i => $file)
                                @if($file)
                                    <div class="relative group aspect-square bg-slate-100 rounded-lg overflow-hidden border-2 border-dashed border-emerald-300">
                                        <img src="{{ $file->temporaryUrl() }}" alt="" class="w-full h-full object-cover">
                                        <span class="absolute bottom-1 left-1 text-[9px] px-1.5 py-0.5 bg-emerald-600 text-white rounded uppercase tracking-wider">New</span>
                                        <button type="button" wire:click="removeNewPhoto({{ $i }})"
                                            class="absolute top-1 right-1 w-6 h-6 bg-rose-600 hover:bg-rose-700 text-white rounded-full text-xs font-bold leading-none flex items-center justify-center shadow opacity-90 hover:opacity-100">×</button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <input type="file" wire:model="newPhotos" accept="image/*" multiple
                        class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">

                    <div wire:loading wire:target="newPhotos" class="text-xs text-slate-500 mt-1">Uploading…</div>
                    @error('newPhotos.*') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror

                    <p class="text-[11px] text-slate-500 mt-2">JPG/PNG up to 5MB each. Photos are stored under <code class="bg-slate-100 px-1 rounded">storage/app/public/room-types/</code> — make sure <code class="bg-slate-100 px-1 rounded">php artisan storage:link</code> has been run.</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
                <button type="button" wire:click="cancelForm" class="px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    @endif
</div>
