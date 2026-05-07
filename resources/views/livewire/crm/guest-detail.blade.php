<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">
            {{ trim($guest->first_name.' '.$guest->last_name) }}
            @if($guest->segment === 'vip')<span class="ml-2 text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">VIP</span>@endif
            @if($guest->is_blacklisted)<span class="ml-2 text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Blacklisted</span>@endif
        </h1>
        <a href="{{ route('crm.guests') }}" class="text-sm text-slate-600">← All guests</a>
    </div>
    <p class="text-sm text-slate-600 mb-4">{{ $guest->phone }} {{ $guest->email ? '· '.$guest->email : '' }}</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    {{-- Quick action bar --}}
    <div class="bg-white rounded-xl border p-3 mb-4 flex flex-wrap gap-2 items-center">
        <button type="button" wire:click="startEditProfile" class="text-sm px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">Edit profile</button>
        <button type="button" wire:click="startEditPrefs" class="text-sm px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">Update preferences</button>
        <button type="button" wire:click="toggleVip" wire:confirm="{{ $guest->segment === 'vip' ? 'Remove VIP status?' : 'Mark this guest as VIP?' }}" class="text-sm px-3 py-1.5 rounded-lg border {{ $guest->segment === 'vip' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-slate-300 hover:bg-slate-50' }}">
            {{ $guest->segment === 'vip' ? 'Unmark VIP' : 'Mark VIP' }}
        </button>
        <button type="button" wire:click="toggleBlacklist" wire:confirm="{{ $guest->is_blacklisted ? 'Remove from blacklist?' : 'Blacklist this guest?' }}" class="text-sm px-3 py-1.5 rounded-lg border {{ $guest->is_blacklisted ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-slate-300 hover:bg-slate-50' }}">
            {{ $guest->is_blacklisted ? 'Remove from blacklist' : 'Blacklist' }}
        </button>
    </div>

    {{-- Tabs --}}
    <div class="bg-white rounded-xl border mb-6">
        <div class="flex border-b">
            <button type="button" wire:click="$set('activeTab','overview')" class="px-5 py-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'overview' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-600 hover:text-slate-900' }}">Overview</button>
            <button type="button" wire:click="$set('activeTab','activity')" class="px-5 py-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'activity' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-600 hover:text-slate-900' }}">Notes &amp; activity ({{ count($activity) }})</button>
        </div>
    </div>

    @if($activeTab === 'activity')
        <div class="bg-white rounded-xl border p-5 mb-6">
            <div class="flex items-baseline justify-between mb-4 gap-3 flex-wrap">
                <h3 class="font-semibold text-slate-900">Notes &amp; activity timeline</h3>
                <div class="flex items-center gap-2">
                    <input type="search" wire:model.live.debounce.300ms="activitySearch" placeholder="Filter…" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm w-64">
                </div>
            </div>

            <form wire:submit.prevent="addNote" class="mb-5 flex gap-2">
                <input type="text" wire:model="newNote" placeholder="Add a note about this guest…" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Add note</button>
            </form>
            @error('newNote')<p class="text-xs text-rose-600 -mt-3 mb-3">{{ $message }}</p>@enderror

            @if(count($activity) === 0)
                <div class="text-sm text-slate-500 py-8 text-center">No activity recorded.</div>
            @else
                <ul class="space-y-2">
                    @foreach($activity as $e)
                        @php
                            $dotCls = match($e['type']) {
                                'note'    => 'bg-amber-500',
                                'booking' => 'bg-sky-500',
                                'payment' => 'bg-emerald-500',
                                'amenity' => 'bg-violet-500',
                                default   => 'bg-slate-400',
                            };
                            $typeCls = match($e['type']) {
                                'note'    => 'text-amber-700 bg-amber-50',
                                'booking' => 'text-sky-700 bg-sky-50',
                                'payment' => 'text-emerald-700 bg-emerald-50',
                                'amenity' => 'text-violet-700 bg-violet-50',
                                default   => 'text-slate-600 bg-slate-100',
                            };
                        @endphp
                        <li class="flex gap-3 rounded-lg border border-slate-200 p-3 hover:border-brand-200">
                            <div class="flex-shrink-0 pt-1">
                                <span class="block w-2.5 h-2.5 rounded-full {{ $dotCls }}"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded {{ $typeCls }}">{{ $e['type'] }}</span>
                                        <span class="text-sm font-medium text-slate-900">{{ $e['title'] }}</span>
                                    </div>
                                    <span class="text-xs text-slate-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($e['at'])->format('d M Y, H:i') }}</span>
                                </div>
                                <div class="text-sm text-slate-700 whitespace-pre-wrap">{{ $e['body'] }}</div>
                                @if(($e['by'] ?? '—') !== '—')
                                    <div class="text-xs text-slate-500 mt-0.5">by {{ $e['by'] }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-3 font-semibold">Profile</h3>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between"><dt class="text-slate-600">Salutation</dt><dd>{{ $guest->salutation ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">DOB</dt><dd>{{ $guest->dob ? $guest->dob->format('d M Y') : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Gender</dt><dd>{{ $guest->gender ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Nationality</dt><dd>{{ $guest->nationality ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">ID type</dt><dd>{{ $guest->id_type ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">ID number</dt><dd class="font-mono text-xs">{{ $guest->id_number ?: '—' }}</dd></div>
                </dl>
            </div>
            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-3 font-semibold">Address</h3>
                <div class="text-sm">{{ $guest->address ?: '—' }}<br>{{ $guest->city }} {{ $guest->state }} {{ $guest->postal_code }}<br>{{ $guest->country }}</div>
            </div>
            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-3 font-semibold">Loyalty &amp; segment</h3>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between"><dt class="text-slate-600">Segment</dt><dd>{{ $guest->segment ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Loyalty tier</dt><dd>{{ $guest->loyalty_tier ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Loyalty no.</dt><dd class="font-mono text-xs">{{ $guest->loyalty_number ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Total visits</dt><dd>{{ $guest->total_visits ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Lifetime spend</dt><dd class="font-bold">₹{{ number_format($guest->lifetime_spend ?? 0, 0) }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-4">
            {{-- ID proofs --}}
            <div class="bg-white rounded-xl border p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">ID proofs</h3>
                </div>
                @php $idFiles = is_array($guest->id_proof_files) ? $guest->id_proof_files : []; @endphp
                @if(count($idFiles))
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        @foreach($idFiles as $i => $path)
                            <div class="relative border border-slate-200 rounded-lg overflow-hidden group">
                                @php $isImage = preg_match('/\.(jpg|jpeg|png)$/i', $path); @endphp
                                @if($isImage)
                                    <a href="{{ asset('storage/'.$path) }}" target="_blank">
                                        <img src="{{ asset('storage/'.$path) }}" class="w-full h-24 object-cover" alt="ID proof">
                                    </a>
                                @else
                                    <a href="{{ asset('storage/'.$path) }}" target="_blank" class="flex items-center justify-center h-24 bg-slate-50 text-xs text-slate-700">
                                        PDF · {{ basename($path) }}
                                    </a>
                                @endif
                                <button type="button" wire:click="deleteIdProof({{ $i }})" wire:confirm="Remove this ID proof file?" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-rose-600 text-white text-xs hover:bg-rose-700">×</button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-slate-500 mb-4">No ID proofs uploaded yet.</div>
                @endif

                <form wire:submit.prevent="uploadIdProofs" class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[16rem]">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Upload ID proof (front, back, selfie)</label>
                        <input type="file" multiple accept=".jpg,.jpeg,.png,.pdf" wire:model="newIdUploads" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Upload</button>
                </form>
                @error('newIdUploads.*')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror
                <p class="text-[11px] text-slate-500 mt-2">JPG/PNG/PDF up to 5MB. Required by Indian hospitality law (Section 14, Foreigners Act for non-Indians).</p>
            </div>

            {{-- Foreign-national / FRRO details --}}
            <div class="bg-white rounded-xl border p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Foreign-national / FRRO details</h3>
                </div>
                <form wire:submit.prevent="saveForeignNational" class="space-y-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" wire:model.live="is_foreign_national" class="rounded">
                        Foreign national (requires Form C / FRRO submission)
                    </label>

                    @if($is_foreign_national)
                        <div class="grid md:grid-cols-3 gap-3 text-sm">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Nationality (ISO 2)</label>
                                <input type="text" wire:model="nationality" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Passport number</label>
                                <input type="text" wire:model="passport_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Passport expiry</label>
                                <input type="date" wire:model="passport_expiry" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Visa number</label>
                                <input type="text" wire:model="visa_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Visa expiry</label>
                                <input type="date" wire:model="visa_expiry" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Arrived in India on</label>
                                <input type="date" wire:model="arrival_date_in_india" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Arrived from (ISO 2)</label>
                                <input type="text" wire:model="arrival_from_country" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Next destination (ISO 2)</label>
                                <input type="text" wire:model="next_destination_country" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Next destination address</label>
                                <input type="text" wire:model="next_destination_address" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Save FRRO details</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-3 font-semibold">Stay history ({{ $stays->count() }})</h3>
                @if($stays->count())
                    <div class="space-y-2">
                        @foreach($stays as $s)
                            <a href="{{ route('reservations.show', $s) }}" class="block rounded-lg border border-slate-200 p-3 hover:border-brand-300">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-mono text-xs text-brand-600">{{ $s->reservation_number }}</span>
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100">{{ str_replace('_',' ',$s->status) }}</span>
                                </div>
                                <div class="text-sm">{{ \Carbon\Carbon::parse($s->arrival_date)->format('d M Y') }} – {{ \Carbon\Carbon::parse($s->departure_date)->format('d M Y') }} · {{ $s->nights }}N · ₹{{ number_format($s->total_amount, 0) }}</div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-slate-500 py-4 text-center">No previous stays.</div>
                @endif
            </div>

            <div class="bg-white rounded-xl border p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Preferences</h3>
                    @if(!$editingPrefs)
                        <button type="button" wire:click="startEditPrefs" class="text-xs text-brand-600 font-medium">Edit</button>
                    @endif
                </div>

                @if(!$editingPrefs)
                    @if(empty($structuredPrefs))
                        <div class="text-sm text-slate-500">None recorded.</div>
                    @else
                        <ul class="text-sm text-slate-700 grid sm:grid-cols-2 gap-x-4 gap-y-1">
                            @foreach($structuredPrefs as $k => $v)
                                <li><span class="text-xs uppercase tracking-wider text-slate-500">{{ str_replace('_',' ',$k) }}:</span> {{ is_array($v) ? json_encode($v) : str_replace('_',' ',$v) }}</li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <form wire:submit.prevent="savePrefs" class="grid md:grid-cols-2 gap-3 text-sm">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Smoking</label>
                            <div class="flex gap-3 text-sm">
                                <label class="flex items-center gap-1"><input type="radio" wire:model="pref_smoking" value=""> Any</label>
                                <label class="flex items-center gap-1"><input type="radio" wire:model="pref_smoking" value="non_smoking"> Non-smoking</label>
                                <label class="flex items-center gap-1"><input type="radio" wire:model="pref_smoking" value="smoking"> Smoking</label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Floor</label>
                            <select wire:model="pref_floor" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                <option value="">Any</option><option value="low">Low floor</option><option value="high">High floor</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Pillow</label>
                            <select wire:model="pref_pillow" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                <option value="">—</option><option value="soft">Soft</option><option value="firm">Firm</option><option value="feather">Feather</option><option value="hypoallergenic">Hypoallergenic</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Bed</label>
                            <select wire:model="pref_bed" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                <option value="">—</option><option value="king">King</option><option value="queen">Queen</option><option value="twin">Twin</option><option value="double">Double</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Dietary</label>
                            <select wire:model="pref_dietary" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                <option value="">—</option><option value="veg">Vegetarian</option><option value="non_veg">Non-vegetarian</option><option value="vegan">Vegan</option><option value="jain">Jain</option><option value="gluten_free">Gluten-free</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Allergies</label>
                            <input type="text" wire:model="pref_allergies" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="e.g. peanuts">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Other notes</label>
                            <textarea wire:model="pref_other" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></textarea>
                        </div>
                        <div class="md:col-span-2 flex justify-end gap-2 pt-2">
                            <button type="button" wire:click="cancelEditPrefs" class="px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Save preferences</button>
                        </div>
                    </form>
                @endif

                @if($guest->notes)<div class="mt-3 text-xs text-slate-600 bg-amber-50 border border-amber-200 rounded p-3"><span class="font-semibold uppercase tracking-wider mr-1">Profile note:</span>{{ $guest->notes }}</div>@endif
            </div>

            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-3 font-semibold">Notes log ({{ count($notesLog) }})</h3>

                <form wire:submit.prevent="addNote" class="mb-4 flex gap-2">
                    <input type="text" wire:model="newNote" placeholder="Add a note about this guest…" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Add note</button>
                </form>
                @error('newNote')<p class="text-xs text-rose-600 -mt-3 mb-3">{{ $message }}</p>@enderror

                @if(empty($notesLog))
                    <div class="text-sm text-slate-500">No notes yet.</div>
                @else
                    <ul class="space-y-2">
                        @foreach($notesLog as $note)
                            <li class="rounded-lg border border-slate-200 p-3 bg-slate-50">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs text-slate-500">{{ $note['by'] ?? '—' }}</span>
                                    <span class="text-xs text-slate-400">{{ isset($note['at']) ? \Carbon\Carbon::parse($note['at'])->format('d M Y, H:i') : '' }}</span>
                                </div>
                                <div class="text-sm text-slate-800 whitespace-pre-wrap">{{ $note['body'] ?? '' }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @endif {{-- /activeTab overview --}}

    {{-- Edit profile modal --}}
    @if($editingProfile)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-4 overflow-y-auto" wire:click.self="cancelEditProfile">
            <form wire:submit.prevent="saveProfile" class="bg-white rounded-xl border w-full max-w-3xl my-8 shadow-xl">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="font-semibold text-lg">Edit profile</h2>
                    <button type="button" wire:click="cancelEditProfile" class="text-slate-500 hover:text-slate-700 text-xl leading-none">&times;</button>
                </div>
                <div class="p-6 grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Salutation</label>
                        <select wire:model="salutation" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option><option value="Mr">Mr</option><option value="Mrs">Mrs</option><option value="Ms">Ms</option><option value="Dr">Dr</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">First name *</label>
                        <input type="text" wire:model="first_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('first_name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Last name</label>
                        <input type="text" wire:model="last_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Gender</label>
                        <select wire:model="gender" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option><option value="M">Male</option><option value="F">Female</option><option value="O">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Date of birth</label>
                        <input type="date" wire:model="dob" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" wire:model="phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" wire:model="email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @error('email')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Country (ISO 2)</label>
                        <input type="text" wire:model="country" maxlength="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Address</label>
                        <input type="text" wire:model="address" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">City</label>
                        <input type="text" wire:model="city" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">State</label>
                        <input type="text" wire:model="state" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Postal code</label>
                        <input type="text" wire:model="postal_code" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">ID type</label>
                        <select wire:model="id_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option>
                            <option value="aadhaar">Aadhaar</option>
                            <option value="pan">PAN</option>
                            <option value="passport">Passport</option>
                            <option value="driving_license">Driving licence</option>
                            <option value="voter_id">Voter ID</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">ID number</label>
                        <input type="text" wire:model="id_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Segment</label>
                        <select wire:model="segment" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">—</option><option value="walk_in">Walk-in</option><option value="leisure">Leisure</option><option value="corporate">Corporate</option><option value="ota">OTA</option><option value="group">Group</option><option value="vip">VIP</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Loyalty tier</label>
                        <input type="text" wire:model="loyalty_tier" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Loyalty number</label>
                        <input type="text" wire:model="loyalty_number" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-slate-50 flex justify-end gap-2">
                    <button type="button" wire:click="cancelEditProfile" class="px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Save changes</button>
                </div>
            </form>
        </div>
    @endif
</div>
