<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold text-slate-900">Form C — FRRO foreign-guest registration</h1>
    </div>
    <p class="text-sm text-slate-600 mb-4">Indian hotels must report every foreign national to the Bureau of Immigration via the C-Form portal within 24 hours of check-in.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Days back</label>
            <input type="number" min="1" max="365" wire:model.live="daysBack" class="w-24 px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
            <select wire:model.live="statusFilter" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">All</option>
                <option value="pending">Pending submission</option>
                <option value="generated">Generated, not submitted</option>
                <option value="submitted">Submitted</option>
            </select>
        </div>
        <div class="flex-1 min-w-[12rem]">
            <label class="block text-xs font-medium text-slate-700 mb-1">Search (name / passport / reservation)</label>
            <input type="text" wire:model.live.debounce.400ms="search" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="e.g. Smith, A1234567">
        </div>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        @if($rows->count())
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-500 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Guest</th>
                        <th class="px-4 py-3">Nationality</th>
                        <th class="px-4 py-3">Passport / Visa</th>
                        <th class="px-4 py-3">Stay</th>
                        <th class="px-4 py-3">Reservation</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($rows as $row)
                        @php
                            $r = $row['reservation'];
                            $g = $row['guest'];
                            $sub = $row['submission'];
                            $st = $row['status'];
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ trim(($g->first_name ?? '').' '.($g->last_name ?? '')) ?: $r->guest_name }}</div>
                                <div class="text-xs text-slate-500">{{ $g->phone ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs uppercase tracking-wider">{{ $g->nationality ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">
                                <div>P: {{ $g->passport_number ?? '—' }}</div>
                                <div class="text-slate-500">V: {{ $g->visa_number ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $r->arrival_date?->format('d M') }} – {{ $r->departure_date?->format('d M Y') }}
                                <div class="text-slate-500">{{ $r->nights }}N</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                <a href="{{ route('reservations.show', $r) }}" class="text-brand-600">{{ $r->reservation_number }}</a>
                            </td>
                            <td class="px-4 py-3">
                                @if($st === 'submitted')
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Submitted</span>
                                    @if($sub?->submitted_at)
                                        <div class="text-[10px] text-slate-500">{{ $sub->submitted_at->format('d M H:i') }}</div>
                                    @endif
                                @elseif($st === 'generated')
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Generated</span>
                                @else
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Pending</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2 flex-wrap">
                                    @if($sub)
                                        <a href="{{ route('compliance.form-c.print', ['reservation' => $r->id]) }}" class="text-xs px-2 py-1 border border-slate-300 rounded hover:bg-slate-50">Open</a>
                                    @else
                                        <button type="button" wire:click="generateForm({{ $r->id }})" class="text-xs px-2 py-1 bg-brand-600 hover:bg-brand-700 text-white rounded">Generate Form C</button>
                                    @endif

                                    @if($sub && !$sub->submitted_at)
                                        <button type="button" wire:click="startMarkSubmitted({{ $sub->id }})" class="text-xs px-2 py-1 border border-emerald-300 text-emerald-700 rounded hover:bg-emerald-50">Mark submitted</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="px-5 py-12 text-sm text-slate-500 text-center">
                No foreign-national guests found in the last {{ $daysBack }} days.
                <div class="text-xs mt-2">Mark guests as foreign nationals during check-in or on the guest profile.</div>
            </div>
        @endif
    </div>

    @if($markingSubmissionId)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-4 overflow-y-auto" wire:click.self="cancelMark">
            <form wire:submit.prevent="markSubmitted" class="bg-white rounded-xl border w-full max-w-lg my-8 shadow-xl">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="font-semibold text-lg">Mark Form C submitted</h2>
                    <button type="button" wire:click="cancelMark" class="text-slate-500 hover:text-slate-700 text-xl leading-none">&times;</button>
                </div>
                <div class="p-6 space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Submission method</label>
                        <select wire:model="submissionMethod" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="portal">indianfrro.gov.in portal</option>
                            <option value="online">Direct API</option>
                            <option value="printed">Printed and submitted in person</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">FRRO acknowledgement number</label>
                        <input type="text" wire:model="ackNumber" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="If provided">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Notes</label>
                        <textarea wire:model="submissionNotes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-slate-50 flex justify-end gap-2">
                    <button type="button" wire:click="cancelMark" class="px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Mark submitted</button>
                </div>
            </form>
        </div>
    @endif
</div>
