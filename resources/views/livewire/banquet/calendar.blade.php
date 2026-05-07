<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">Banquet calendar</h1>
        <div class="flex items-center gap-2 text-sm">
            <button type="button" wire:click="shift(-1)" class="px-3 py-1.5 border rounded">← Prev</button>
            <span class="font-semibold">{{ $monthStart->format('F Y') }}</span>
            <button type="button" wire:click="shift(1)" class="px-3 py-1.5 border rounded">Next →</button>
            <button type="button" wire:click="jumpToToday" class="px-3 py-1.5 border border-brand-300 text-brand-700 rounded font-semibold">Today</button>
            <button type="button" wire:click="startCreate" class="ml-3 bg-brand-600 hover:bg-brand-700 text-white px-4 py-1.5 rounded text-sm font-semibold">+ New event</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Halls: {{ $halls->count() }} · Events this month: {{ $stats['count'] }} · Pax: {{ $stats['pax'] }} · Revenue: ₹{{ number_format($stats['total_revenue'], 0) }} · Confirmed: {{ $stats['confirmed'] }}</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    {{-- Create / edit form --}}
    @if($showForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-brand-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">{{ $editingId ? 'Edit booking' : 'New banquet event' }}</h2>
                <button type="button" wire:click="cancelForm" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div><label class="block text-xs font-medium mb-1">Event name *</label><input type="text" wire:model="eventName" class="w-full px-3 py-2 border rounded text-sm" placeholder="Mehta Anniversary"></div>
                <div><label class="block text-xs font-medium mb-1">Event type</label>
                    <select wire:model="eventType" class="w-full px-3 py-2 border rounded text-sm">
                        @foreach(['wedding','reception','conference','meeting','birthday','anniversary','corporate','product_launch','training','other'] as $t)
                            <option value="{{ $t }}">{{ ucfirst(str_replace('_',' ',$t)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Status</label>
                    <select wire:model="status" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="enquiry">Enquiry</option><option value="tentative">Tentative</option>
                        <option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Hall *</label>
                    <select wire:model="hallId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">Select…</option>
                        @foreach($halls as $h)<option value="{{ $h->id }}">{{ $h->name }} (cap {{ $h->banquet_capacity ?? '—' }})</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Package</label>
                    <select wire:model="packageId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">No package</option>
                        @foreach($packages as $p)<option value="{{ $p->id }}">{{ $p->name }} (₹{{ number_format($p->per_pax_rate,0) }}/pax)</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Expected pax *</label><input type="number" wire:model="expectedPax" class="w-full px-3 py-2 border rounded text-sm" min="1"></div>
                <div><label class="block text-xs font-medium mb-1">Date *</label><input type="date" wire:model="eventDate" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Start</label><input type="time" wire:model="eventStart" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">End</label><input type="time" wire:model="eventEnd" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Guest (individual)</label>
                    <select wire:model="guestId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">—</option>
                        @foreach($guests as $g)<option value="{{ $g->id }}">{{ $g->first_name }} {{ $g->last_name }}</option>@endforeach
                    </select>
                </div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Company / corporate client</label>
                    <select wire:model="companyId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">—</option>
                        @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }} {{ $c->gstin ? '· GST '.$c->gstin : '' }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div class="font-semibold text-slate-900 text-sm mb-2">Charges (₹)</div>
            <div class="grid md:grid-cols-6 gap-3 mb-4">
                <div><label class="block text-xs">Hall rent</label><input type="number" step="0.01" wire:model="hallRent" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                <div><label class="block text-xs">Food</label><input type="number" step="0.01" wire:model="foodAmount" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                <div><label class="block text-xs">Beverage</label><input type="number" step="0.01" wire:model="beverageAmount" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                <div><label class="block text-xs">Decor</label><input type="number" step="0.01" wire:model="decorAmount" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                <div><label class="block text-xs">AV</label><input type="number" step="0.01" wire:model="avAmount" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                <div><label class="block text-xs">Other</label><input type="number" step="0.01" wire:model="otherAmount" class="w-full px-2 py-1.5 border rounded text-sm"></div>
            </div>

            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Menu / F&B notes</label><textarea wire:model="menuDetails" rows="2" class="w-full px-3 py-2 border rounded text-sm"></textarea></div>
                <div><label class="block text-xs font-medium mb-1">Advance received</label><input type="number" step="0.01" wire:model="advanceReceived" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Setup notes</label><textarea wire:model="setupNotes" rows="2" class="w-full px-3 py-2 border rounded text-sm"></textarea></div>
                <div><label class="block text-xs font-medium mb-1">Special requests</label><textarea wire:model="specialRequests" rows="2" class="w-full px-3 py-2 border rounded text-sm"></textarea></div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="button" wire:click="cancelForm" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="save" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded text-sm font-semibold">{{ $editingId ? 'Update event' : 'Create event' }}</button>
            </div>
        </div>
    @endif

    {{-- Detail / function sheet --}}
    @if($detail)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-brand-200">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="text-xs text-slate-500 font-mono">{{ $detail->booking_number }}</div>
                    <h2 class="text-xl font-bold">{{ $detail->event_name }}</h2>
                    <div class="text-sm text-slate-600 mt-1">
                        {{ ucfirst(str_replace('_',' ',$detail->event_type)) }} · {{ $detail->event_date->format('D, d M Y') }} · {{ substr($detail->event_start_time,0,5) }}–{{ substr($detail->event_end_time,0,5) }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @php $cls = ['enquiry'=>'bg-slate-100','tentative'=>'bg-amber-100 text-amber-800','confirmed'=>'bg-emerald-100 text-emerald-800','completed'=>'bg-violet-100 text-violet-800','cancelled'=>'bg-rose-100 text-rose-800'][$detail->status] ?? 'bg-slate-100'; @endphp
                    <span class="text-[11px] uppercase tracking-wider px-2.5 py-1 rounded-full {{ $cls }}">{{ $detail->status }}</span>
                    <button type="button" wire:click="startEdit({{ $detail->id }})" class="text-xs px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded">Edit</button>
                    <button type="button" wire:click="closeDetail" class="text-sm text-slate-500">Close</button>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                {{-- Function sheet (left col) --}}
                <div class="md:col-span-2 space-y-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-slate-50 rounded p-3"><div class="text-[10px] uppercase text-slate-500">Hall</div><div class="font-semibold text-sm">{{ $detail->hall?->name ?? '—' }}</div></div>
                        <div class="bg-slate-50 rounded p-3"><div class="text-[10px] uppercase text-slate-500">Pax</div><div class="font-semibold text-sm">{{ $detail->expected_pax }} {{ $detail->actual_pax ? '(actual '.$detail->actual_pax.')' : '' }}</div></div>
                        <div class="bg-slate-50 rounded p-3"><div class="text-[10px] uppercase text-slate-500">Package</div><div class="font-semibold text-sm">{{ $detail->package?->name ?? '—' }}</div></div>
                        <div class="bg-slate-50 rounded p-3"><div class="text-[10px] uppercase text-slate-500">Sales owner</div><div class="font-semibold text-sm">{{ $detail->salesOwner?->name ?? '—' }}</div></div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-slate-600 mb-2">Client</div>
                        <div class="bg-white border rounded p-4 text-sm">
                            @if($detail->company)
                                <div class="font-semibold text-slate-900">{{ $detail->company->name }}</div>
                                <div class="text-xs text-slate-600 mt-0.5">
                                    @if($detail->company->gstin)GSTIN: {{ $detail->company->gstin }} · @endif
                                    @if($detail->company->phone){{ $detail->company->phone }} · @endif
                                    @if($detail->company->email){{ $detail->company->email }}@endif
                                </div>
                                @if($detail->company->address)<div class="text-xs text-slate-500 mt-1">{{ $detail->company->address }}</div>@endif
                            @endif
                            @if($detail->guest)
                                <div class="font-semibold text-slate-900 {{ $detail->company ? 'mt-3' : '' }}">{{ $detail->guest->first_name }} {{ $detail->guest->last_name }}</div>
                                <div class="text-xs text-slate-600 mt-0.5">
                                    @if($detail->guest->phone){{ $detail->guest->phone }} · @endif
                                    @if($detail->guest->email){{ $detail->guest->email }}@endif
                                </div>
                                <a href="{{ route('crm.guest.show', $detail->guest->id) }}" class="text-xs text-brand-600 hover:underline">View CRM profile →</a>
                            @endif
                            @if(!$detail->guest && !$detail->company)
                                <span class="text-slate-400 text-sm">No client linked.</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-slate-600 mb-2">Operations</div>
                        <div class="grid md:grid-cols-3 gap-3 text-sm">
                            <div class="bg-white border rounded p-3"><div class="text-[10px] uppercase text-slate-500 mb-1">Menu / F&B</div>{{ $detail->menu_details ?: '—' }}</div>
                            <div class="bg-white border rounded p-3"><div class="text-[10px] uppercase text-slate-500 mb-1">Setup notes</div>{{ $detail->setup_notes ?: '—' }}</div>
                            <div class="bg-white border rounded p-3"><div class="text-[10px] uppercase text-slate-500 mb-1">Special requests</div>{{ $detail->special_requests ?: '—' }}</div>
                        </div>
                    </div>

                    {{-- Status workflow --}}
                    <div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-slate-600 mb-2">Status workflow</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['enquiry','tentative','confirmed','completed','cancelled'] as $s)
                                @if($s !== $detail->status)
                                    <button type="button" wire:click="changeStatus({{ $detail->id }}, '{{ $s }}')"
                                        class="text-xs px-3 py-1.5 rounded {{ $s==='confirmed'?'bg-emerald-100 text-emerald-800 hover:bg-emerald-200':($s==='cancelled'?'bg-rose-100 text-rose-800 hover:bg-rose-200':'bg-slate-100 hover:bg-slate-200') }}">
                                        Mark {{ ucfirst($s) }}
                                    </button>
                                @endif
                            @endforeach
                            @if($detail->status !== 'cancelled')
                                <button type="button" wire:click="deleteBooking({{ $detail->id }})" wire:confirm="Cancel this banquet event?" class="text-xs px-3 py-1.5 bg-rose-50 text-rose-700 rounded hover:bg-rose-100 ml-auto">Delete</button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Bill / payments (right col) --}}
                <div>
                    <div class="bg-slate-50 rounded-lg p-4 mb-4">
                        <div class="font-semibold text-slate-900 mb-3 text-sm">Bill summary</div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-slate-600">Hall rent</span><span>₹{{ number_format($detail->hall_rent, 0) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-600">Food</span><span>₹{{ number_format($detail->food_amount, 0) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-600">Beverage</span><span>₹{{ number_format($detail->beverage_amount, 0) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-600">Decor</span><span>₹{{ number_format($detail->decor_amount, 0) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-600">AV</span><span>₹{{ number_format($detail->av_amount, 0) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-600">Other</span><span>₹{{ number_format($detail->other_amount, 0) }}</span></div>
                            <div class="border-t pt-2 mt-2 flex justify-between font-medium"><span>Subtotal</span><span>₹{{ number_format($detail->subtotal, 2) }}</span></div>
                            <div class="flex justify-between text-xs text-slate-500"><span>GST (18% services + 5% F&B)</span><span>₹{{ number_format($detail->tax_amount, 2) }}</span></div>
                            <div class="border-t pt-2 mt-2 flex justify-between font-bold text-base"><span>Total</span><span>₹{{ number_format($detail->total_amount, 2) }}</span></div>
                            <div class="flex justify-between text-emerald-700"><span>Advance received</span><span>− ₹{{ number_format($detail->advance_received, 2) }}</span></div>
                            <div class="flex justify-between font-bold {{ $detail->balanceDue() > 0 ? 'text-rose-700' : 'text-emerald-700' }}"><span>Balance due</span><span>₹{{ number_format($detail->balanceDue(), 2) }}</span></div>
                        </div>
                    </div>

                    {{-- Take advance --}}
                    @if($detail->status !== 'cancelled' && $detail->balanceDue() > 0)
                        <div class="bg-white border rounded-lg p-4">
                            <div class="font-semibold text-sm mb-2">Record advance / payment</div>
                            <div class="space-y-2">
                                <select wire:model="newAdvanceMode" class="w-full px-2 py-1.5 border rounded text-sm">
                                    <option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option>
                                    <option value="cheque">Cheque</option><option value="upi">UPI</option><option value="card">Card</option>
                                </select>
                                <input type="number" step="0.01" wire:model="newAdvanceAmount" placeholder="Amount" class="w-full px-2 py-1.5 border rounded text-sm">
                                <input type="text" wire:model="newAdvanceRef" placeholder="Reference" class="w-full px-2 py-1.5 border rounded text-sm">
                                <button type="button" wire:click="takeAdvance" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2 rounded text-sm font-semibold">Record payment</button>
                            </div>
                        </div>
                    @endif

                    {{-- Payment history --}}
                    @if($detailPayments->isNotEmpty())
                        <div class="bg-white border rounded-lg p-4 mt-4">
                            <div class="font-semibold text-sm mb-2">Payments ({{ $detailPayments->count() }})</div>
                            <div class="space-y-1 text-xs">
                                @foreach($detailPayments as $p)
                                    <div class="flex justify-between border-b border-slate-100 pb-1">
                                        <span>{{ $p->payment_date->format('d M') }} · {{ strtoupper($p->mode) }}</span>
                                        <span class="font-medium">₹{{ number_format($p->amount, 0) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Calendar grid --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        @php $startDow = $monthStart->copy()->startOfWeek(\Carbon\Carbon::SUNDAY); $endDow = $monthEnd->copy()->endOfWeek(\Carbon\Carbon::SATURDAY); $cur = $startDow->copy(); @endphp
        <div class="grid grid-cols-7 bg-slate-100 text-xs uppercase tracking-wider text-slate-600 font-semibold border-b">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<div class="px-3 py-2 text-center">{{ $d }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @while($cur->lte($endDow))
                @php $dateStr = $cur->toDateString(); $isThisMonth = $cur->month === $monthStart->month; $todayBookings = $byDate[$dateStr] ?? collect(); @endphp
                <div class="border-r border-b border-slate-100 min-h-24 p-2 {{ !$isThisMonth ? 'bg-slate-50 text-slate-400' : '' }} {{ $cur->isToday() ? 'bg-brand-50' : '' }}">
                    <div class="text-xs font-bold mb-1">{{ $cur->day }}</div>
                    @foreach($todayBookings as $b)
                        @php $cls = match($b->status){'confirmed'=>'bg-emerald-100 text-emerald-800','tentative'=>'bg-amber-100 text-amber-800','cancelled'=>'bg-rose-100 text-rose-800 line-through','completed'=>'bg-violet-100 text-violet-800',default=>'bg-slate-100 text-slate-700'}; @endphp
                        <button type="button" wire:click="openDetail({{ $b->id }})" class="w-full text-left text-[10px] px-1.5 py-0.5 rounded mb-0.5 truncate {{ $cls }} hover:ring-2 hover:ring-brand-300" title="{{ $b->event_name }} · {{ $b->expected_pax }}pax · {{ $b->hall?->name }}">{{ $b->event_name }}</button>
                    @endforeach
                </div>
                @php $cur->addDay(); @endphp
            @endwhile
        </div>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden mt-6">
        <div class="px-5 py-3 border-b bg-slate-50 font-semibold">All bookings this month</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                <tr><th class="px-5 py-2">Booking #</th><th class="px-4 py-2">Event</th><th class="px-4 py-2">Date</th><th class="px-4 py-2">Time</th><th class="px-4 py-2">Hall</th><th class="px-4 py-2">Client</th><th class="px-4 py-2">Pax</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($bookings as $b)
                    <tr>
                        <td class="px-5 py-2 font-mono text-xs">{{ $b->booking_number }}</td>
                        <td class="px-4 py-2 font-medium">{{ $b->event_name }}<div class="text-[10px] text-slate-500">{{ str_replace('_',' ',$b->event_type) }}</div></td>
                        <td class="px-4 py-2 text-xs">{{ $b->event_date->format('d M') }}</td>
                        <td class="px-4 py-2 text-xs">{{ substr($b->event_start_time,0,5) }} - {{ substr($b->event_end_time,0,5) }}</td>
                        <td class="px-4 py-2 text-xs">{{ $b->hall?->name }}</td>
                        <td class="px-4 py-2 text-xs">{{ $b->company?->name ?: ($b->guest ? $b->guest->first_name.' '.$b->guest->last_name : '—') }}</td>
                        <td class="px-4 py-2">{{ $b->expected_pax }}</td>
                        <td class="px-4 py-2 text-right">₹{{ number_format($b->total_amount, 0) }}</td>
                        <td class="px-4 py-2">
                            @php $cls = ['enquiry'=>'bg-slate-100','tentative'=>'bg-amber-100 text-amber-800','confirmed'=>'bg-emerald-100 text-emerald-800','completed'=>'bg-violet-100 text-violet-800','cancelled'=>'bg-rose-100 text-rose-800'][$b->status] ?? 'bg-slate-100'; @endphp
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full {{ $cls }}">{{ $b->status }}</span>
                        </td>
                        <td class="px-4 py-2 text-right"><button type="button" wire:click="openDetail({{ $b->id }})" class="text-xs text-brand-600 hover:underline">View</button></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-5 py-8 text-center text-sm text-slate-500">No banquet events this month. <button type="button" wire:click="startCreate" class="text-brand-600 hover:underline">+ Create one</button></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
