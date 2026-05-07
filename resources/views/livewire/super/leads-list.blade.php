<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Lead Management</h1>
            <p class="text-sm text-slate-500">Inquiries from the public landing page · convert to paying clients</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('super.dashboard') }}" class="text-sm text-slate-600">/super</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif

    {{-- Filter chips --}}
    <div class="flex items-center gap-2 mb-4 flex-wrap">
        @foreach([['all','All','bg-slate-100 text-slate-800'],['new','New','bg-sky-100 text-sky-800'],['contacted','Contacted','bg-amber-100 text-amber-800'],['qualified','Qualified','bg-violet-100 text-violet-800'],['won','Won','bg-emerald-100 text-emerald-800'],['lost','Lost','bg-rose-100 text-rose-800']] as $f)
            <button wire:click="$set('statusFilter','{{ $f[0] }}')" class="text-xs font-semibold px-3 py-1.5 rounded-full {{ $statusFilter === $f[0] ? $f[2].' ring-2 ring-offset-1 ring-slate-400' : 'bg-white border border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                {{ $f[1] }} <span class="opacity-60">{{ $counts[$f[0]] ?? 0 }}</span>
            </button>
        @endforeach
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, email, phone, hotel…" class="ml-auto px-3 py-1.5 text-sm border border-slate-300 rounded-lg w-72">
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Lead</th>
                    <th class="px-3 py-3">Hotel</th>
                    <th class="px-3 py-3">Source</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Submitted</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($leads as $l)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <div class="font-semibold text-slate-900">{{ $l->name }}</div>
                        <div class="text-xs text-slate-500">{{ $l->email }} · {{ $l->phone }}</div>
                    </td>
                    <td class="px-3 py-3 text-xs">
                        <div class="font-medium text-slate-800">{{ $l->hotel_name ?: '—' }}</div>
                        <div class="text-slate-500">{{ $l->city }} · {{ $l->rooms_count ? $l->rooms_count.' rooms' : 'rooms n/a' }}</div>
                    </td>
                    <td class="px-3 py-3 text-xs text-slate-600">{{ $l->utm_source ?: $l->source }}</td>
                    <td class="px-3 py-3"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $l->statusBadgeClass() }}">{{ $l->status }}</span></td>
                    <td class="px-3 py-3 text-xs">
                        <div>{{ $l->created_at->format('d M Y') }}</div>
                        <div class="text-slate-500">{{ $l->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <button wire:click="startEdit({{ $l->id }})" class="text-xs bg-brand-600 hover:bg-brand-700 text-white font-semibold px-3 py-1.5 rounded">Update</button>
                    </td>
                </tr>
                @if($editId === $l->id)
                <tr class="bg-brand-50/30">
                    <td colspan="6" class="px-5 py-4">
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Status</label>
                                <select wire:model="editStatus" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Next follow-up</label>
                                <input wire:model="editFollowup" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            </div>
                            <div></div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Add note</label>
                                <textarea wire:model="newNote" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Spoke to {{ $l->name }} — they want a demo on Friday."></textarea>
                            </div>
                        </div>
                        @if($l->notes)
                        <div class="mt-3 bg-white border border-slate-200 rounded-lg p-3 text-xs whitespace-pre-line text-slate-700 max-h-48 overflow-y-auto">{{ $l->notes }}</div>
                        @endif
                        @if($l->message)
                        <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900">
                            <div class="font-semibold mb-1">Original message:</div>{{ $l->message }}
                        </div>
                        @endif
                        <div class="mt-3 flex gap-2">
                            <button wire:click="saveEdit" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded">Save changes</button>
                            <button wire:click="cancelEdit" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-4 py-2 rounded">Cancel</button>
                        </div>
                    </td>
                </tr>
                @endif
                @empty
                <tr><td colspan="6" class="text-center py-12 text-sm text-slate-500">No leads yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3 border-t bg-slate-50">{{ $leads->links() }}</div>
    </div>
</div>
