<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Reviews inbox</h1>
        <button type="button" wire:click="startAddReview" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Add review manually</button>
    </div>
    <p class="text-sm text-slate-600 mb-6">Aggregated guest reviews from Google, Booking.com, TripAdvisor, MMT.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded">{{ session('success') }}</div>@endif

    @if($showAddForm)
        <div class="bg-white rounded-xl border border-brand-200 ring-2 ring-brand-100 p-5 mb-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-900">Add review manually</h2>
                <button type="button" wire:click="cancelAddReview" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Source *</label>
                    <select wire:model="newSource" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="booking_com">Booking.com</option>
                        <option value="mmt">MakeMyTrip</option>
                        <option value="tripadvisor">TripAdvisor</option>
                        <option value="google">Google</option>
                        <option value="direct">Direct / In-house</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Reviewer name</label>
                    <input type="text" wire:model="newReviewerName" class="w-full px-3 py-2 border rounded text-sm" placeholder="Anonymous if blank">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Rating (1–5) *</label>
                    <input type="number" min="1" max="5" step="0.5" wire:model="newRating" class="w-full px-3 py-2 border rounded text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium mb-1">Title</label>
                    <input type="text" wire:model="newTitle" class="w-full px-3 py-2 border rounded text-sm" placeholder="Optional headline">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium mb-1">Body *</label>
                    <textarea wire:model="newBody" rows="4" class="w-full px-3 py-2 border rounded text-sm" placeholder="What did the guest say?"></textarea>
                    @error('newBody')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-2 relative">
                    <label class="block text-xs font-medium mb-1">Reservation (optional)</label>
                    @if($newReservationId)
                        <div class="flex items-center gap-2 px-3 py-2 border rounded text-sm bg-emerald-50 border-emerald-200">
                            <span class="flex-1">{{ $newReservationSearch }}</span>
                            <button type="button" wire:click="clearReservation" class="text-rose-600 text-xs">Clear</button>
                        </div>
                    @else
                        <input type="text" wire:model.live.debounce.300ms="newReservationSearch" class="w-full px-3 py-2 border rounded text-sm" placeholder="Search by guest name or reservation #">
                        @if($reservationSuggestions->count())
                            <div class="absolute z-10 left-0 right-0 mt-1 bg-white border rounded shadow-lg max-h-56 overflow-auto">
                                @foreach($reservationSuggestions as $r)
                                    <button type="button"
                                        wire:click="pickReservation({{ $r->id }}, '{{ addslashes($r->reservation_number . ' · ' . $r->guest_name) }}')"
                                        class="w-full text-left px-3 py-2 text-xs hover:bg-brand-50 border-b last:border-b-0">
                                        <span class="font-mono">{{ $r->reservation_number }}</span> · {{ $r->guest_name }}
                                        <span class="text-slate-400 ml-2">{{ \Carbon\Carbon::parse($r->arrival_date)->format('d M Y') }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Posted on *</label>
                    <input type="date" wire:model="newPostedAt" class="w-full px-3 py-2 border rounded text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t">
                <button type="button" wire:click="cancelAddReview" class="px-4 py-2 text-sm">Cancel</button>
                <button type="button" wire:click="saveManualReview" class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save review</button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Total reviews</div><div class="text-2xl font-bold">{{ $stats['total'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Avg rating</div><div class="text-2xl font-bold">{{ $stats['avgRating'] }} ★</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Awaiting reply</div><div class="text-2xl font-bold text-amber-700">{{ $stats['unreplied'] }}</div></div>
    </div>

    <div class="bg-white rounded-xl border p-4 mb-4 flex gap-3 items-center">
        <select wire:model.live="sourceFilter" class="px-3 py-1.5 border border-slate-300 rounded text-sm">
            <option value="">All sources</option>
            @foreach($sources as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
        </select>
        <select wire:model.live="sentimentFilter" class="px-3 py-1.5 border border-slate-300 rounded text-sm">
            <option value="">All sentiments</option>
            <option value="positive">Positive</option>
            <option value="neutral">Neutral</option>
            <option value="negative">Negative</option>
        </select>
    </div>

    @if($reviews->isEmpty())
        <div class="bg-white rounded-xl border p-12 text-center">
            <div class="text-sm text-slate-500 mb-2">No reviews yet.</div>
            <div class="text-xs text-slate-400">Connect a review source to start syncing reviews from Google, Booking, etc.</div>
        </div>
    @else
        <div class="space-y-3">
            @foreach($reviews as $r)
                <div class="bg-white rounded-xl border p-5">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $r->reviewer_name ?: 'Anonymous' }} <span class="text-slate-400">·</span> <span class="text-xs text-slate-500">{{ ucfirst($r->source) }}</span></div>
                            <div class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($r->review_date)->format('d M Y') }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-amber-600">{{ $r->rating }} ★</span>
                            @if($r->sentiment)
                                @php $cls = ['positive'=>'bg-emerald-100 text-emerald-700','neutral'=>'bg-slate-100 text-slate-700','negative'=>'bg-rose-100 text-rose-700'][$r->sentiment] ?? 'bg-slate-100 text-slate-700'; @endphp
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $cls }}">{{ $r->sentiment }}</span>
                            @endif
                        </div>
                    </div>
                    @if($r->title)<div class="font-medium mb-1">{{ $r->title }}</div>@endif
                    <p class="text-sm text-slate-700">{{ $r->body }}</p>
                    @if($r->response_text && $replyingToId !== $r->id)
                        <div class="mt-3 pl-4 border-l-2 border-brand-200 text-sm text-slate-600 italic">
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Hotel response · {{ $r->responded_at?->format('d M Y') }}</div>
                            {{ $r->response_text }}
                        </div>
                        <button type="button" wire:click="startReply({{ $r->id }})" class="mt-2 text-xs text-brand-600 hover:text-brand-700 font-medium">Edit response</button>
                    @elseif($replyingToId === $r->id)
                        <div class="mt-3 bg-slate-50 rounded-lg p-3 border">
                            <label class="text-[10px] uppercase tracking-wider text-slate-500 mb-1 block">Your response</label>
                            <textarea wire:model.live="replyText" rows="4" class="w-full px-3 py-2 border rounded text-sm" placeholder="Write a thoughtful reply…"></textarea>
                            @error('replyText')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                            <div class="flex gap-2 mt-2">
                                <button type="button" wire:click="saveReply" class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded">Save reply</button>
                                <button type="button" wire:click="cancelReply" class="border border-slate-300 text-xs px-3 py-1.5 rounded">Cancel</button>
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="startReply({{ $r->id }})" class="mt-3 text-xs text-brand-600 hover:text-brand-700 font-medium">+ Draft response</button>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $reviews->links() }}</div>
    @endif
</div>
