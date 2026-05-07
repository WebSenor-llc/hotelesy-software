<div>
    {{-- Header --}}
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-2xl font-bold text-slate-900">{{ $reservation->guest_name ?: 'Guest' }}</h1>
                    @php
                        $colors = [
                            'tentative'=>'bg-slate-100 text-slate-700','confirmed'=>'bg-sky-100 text-sky-700',
                            'checked_in'=>'bg-emerald-100 text-emerald-700','checked_out'=>'bg-violet-100 text-violet-700',
                            'cancelled'=>'bg-rose-100 text-rose-700','no_show'=>'bg-amber-100 text-amber-700',
                        ];
                        $cls = $colors[$reservation->status] ?? 'bg-slate-100';
                    @endphp
                    <span class="px-3 py-1 text-xs uppercase tracking-wider rounded-full {{ $cls }}">{{ str_replace('_',' ',$reservation->status) }}</span>
                    @if($reservation->is_vip)<span class="px-2 py-0.5 text-[10px] uppercase rounded-full bg-yellow-100 text-yellow-800 font-bold">VIP</span>@endif
                </div>
                <div class="text-sm text-slate-600">
                    <code class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ $reservation->reservation_number }}</code> ·
                    Conf <code class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ $reservation->confirmation_number }}</code>
                </div>
            </div>
            <a href="{{ route('reservations.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mt-5 pt-5 border-t border-slate-200">
            <div><div class="text-xs text-slate-500 mb-0.5">Arrival</div><div class="font-semibold">{{ \Carbon\Carbon::parse($reservation->arrival_date)->format('d M Y') }}</div></div>
            <div><div class="text-xs text-slate-500 mb-0.5">Departure</div><div class="font-semibold">{{ \Carbon\Carbon::parse($reservation->departure_date)->format('d M Y') }}</div></div>
            <div><div class="text-xs text-slate-500 mb-0.5">Nights</div><div class="font-semibold">{{ $reservation->nights }}</div></div>
            <div><div class="text-xs text-slate-500 mb-0.5">Pax</div><div class="font-semibold">{{ $reservation->adults }}A · {{ $reservation->children }}C</div></div>
            <div><div class="text-xs text-slate-500 mb-0.5">Total</div><div class="font-semibold">₹{{ number_format($reservation->total_amount, 0) }}</div></div>
            <div><div class="text-xs text-slate-500 mb-0.5">Balance</div><div class="font-bold {{ $reservation->balance_amount > 0 ? 'text-rose-600' : 'text-emerald-700' }}">₹{{ number_format($reservation->balance_amount, 0) }}</div></div>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200">{{ session('success') }}</div>@endif

    {{-- Tabs --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
        <div class="border-b border-slate-200 flex flex-wrap">
            @foreach(['stay'=>'Stay','edit'=>'Edit','folio'=>'Folio','charges'=>'Post charge','payments'=>'Post payment','status'=>'Change status','cancel'=>'Cancel','guest'=>'Guest'] as $key=>$label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition {{ $tab === $key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-600 hover:text-slate-900' }} {{ $key === 'cancel' ? 'text-rose-600' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="p-6">

        {{-- TAB: Stay --}}
        @if($tab === 'stay')
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-3">Booking</h3>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between"><dt class="text-slate-600">Source</dt><dd class="font-medium">{{ $reservation->source_name ?: $reservation->source_type ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">Market segment</dt><dd>{{ $reservation->market_segment ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">Created</dt><dd>{{ $reservation->created_at->format('d M Y H:i') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">Phone</dt><dd>{{ $reservation->guest_phone ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">Email</dt><dd>{{ $reservation->guest_email ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">Special requests</dt><dd class="text-right max-w-xs">{{ $reservation->special_requests ?: '—' }}</dd></div>
                    </dl>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-3">Rooms</h3>
                    <div class="space-y-3">
                        @foreach($reservation->rooms as $rRoom)
                            <div class="border border-slate-200 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-semibold">{{ $rRoom->roomType?->name ?? 'Room' }}</div>
                                    <span class="text-xs text-slate-500">{{ str_replace('_',' ',$rRoom->status) }}</span>
                                </div>
                                <div class="text-xs text-slate-600">
                                    @if($rRoom->room) Room <code class="font-mono">{{ $rRoom->room->number }}</code> · @endif
                                    Avg ₹{{ number_format($rRoom->average_rate, 0) }}
                                </div>
                                @if($rRoom->checked_in_at)<div class="text-[10px] text-emerald-600 mt-1">Checked in: {{ $rRoom->checked_in_at->format('d M H:i') }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                    @if($reservation->status === 'checked_in' && $availableRooms->count())
                        <div class="mt-4 pt-4 border-t border-slate-200">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Move to another room</h4>
                            <div class="flex gap-2">
                                <select wire:model="moveToRoomId" class="flex-1 px-3 py-1.5 text-sm border border-slate-300 rounded">
                                    <option value="">Select…</option>
                                    @foreach($availableRooms as $room)<option value="{{ $room->id }}">{{ $room->number }} (F{{ $room->floor }})</option>@endforeach
                                </select>
                                <button type="button" wire:click="moveRoom" class="px-3 py-1.5 text-sm bg-slate-700 text-white rounded">Move</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- TAB: Edit --}}
        @if($tab === 'edit')
            @if(in_array($reservation->status, ['cancelled','no_show','checked_out']))
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800 mb-4">
                    Reservation is <strong>{{ $reservation->status }}</strong> — editing is generally not allowed once finalised. Make changes only if there's a recovery scenario.
                </div>
            @endif
            <form wire:submit.prevent="saveEdit" class="grid md:grid-cols-3 gap-4">
                <div class="md:col-span-3"><h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Guest</h3></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Guest name *</label><input type="text" wire:model="e_guest_name" class="w-full px-3 py-2 border rounded-lg text-sm">@error('e_guest_name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror</div>
                <div><label class="block text-xs font-medium mb-1">Phone</label><input type="tel" wire:model="e_guest_phone" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Email</label><input type="email" wire:model="e_guest_email" class="w-full px-3 py-2 border rounded-lg text-sm"></div>

                <div class="md:col-span-3 pt-3 border-t mt-2"><h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Stay</h3></div>
                <div><label class="block text-xs font-medium mb-1">Arrival *</label><input type="date" wire:model="e_arrival_date" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Departure *</label><input type="date" wire:model="e_departure_date" class="w-full px-3 py-2 border rounded-lg text-sm">@error('e_departure_date')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror</div>
                <div><label class="block text-xs font-medium mb-1">Room type *</label>
                    <select wire:model="e_room_type_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                        @foreach($roomTypes as $rt)<option value="{{ $rt->id }}">{{ $rt->name }} · ₹{{ number_format($rt->base_rate, 0) }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Adults *</label><input type="number" min="1" wire:model="e_adults" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Children</label><input type="number" min="0" wire:model="e_children" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="flex items-center gap-2 text-sm mt-6"><input type="checkbox" wire:model="e_is_vip" class="rounded">Mark as VIP</label></div>

                <div class="md:col-span-3 pt-3 border-t mt-2"><h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Source & segment</h3></div>
                <div><label class="block text-xs font-medium mb-1">Source type *</label>
                    <select wire:model="e_source_type" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="direct">Direct</option><option value="walk_in">Walk-in</option><option value="phone">Phone</option>
                        <option value="email">Email</option><option value="website">Website</option><option value="ota">OTA</option>
                        <option value="corporate">Corporate</option><option value="travel_agent">Travel agent</option>
                        <option value="gds">GDS</option><option value="group">Group</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Source name</label><input type="text" wire:model="e_source_name" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Booking.com, Infosys, etc."></div>
                <div><label class="block text-xs font-medium mb-1">Market segment</label><input type="text" wire:model="e_market_segment" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Leisure, Corporate, FIT…"></div>

                <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Special requests</label><textarea wire:model="e_special_requests" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
                <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Internal notes (staff-only)</label><textarea wire:model="e_internal_notes" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>

                <div class="md:col-span-3 flex justify-end gap-2 pt-4 border-t">
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Save changes</button>
                </div>
            </form>
        @endif

        {{-- TAB: Folio --}}
        @if($tab === 'folio')
            @forelse($folios as $folio)
                <div class="mb-6 last:mb-0">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <div class="font-semibold">Folio {{ $folio->folio_number }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($folio->type) }} · {{ $folio->status }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-slate-500">Balance</div>
                            <div class="font-bold {{ $folio->balance > 0 ? 'text-rose-600' : 'text-emerald-700' }}">₹{{ number_format($folio->balance, 2) }}</div>
                        </div>
                    </div>
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b">
                                <tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Cat</th><th class="px-4 py-2">Description</th><th class="px-4 py-2 text-right">Qty × Rate</th><th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-right">Tax</th><th class="px-4 py-2 text-right">Net</th><th></th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($folio->charges as $c)
                                    <tr class="{{ $c->is_voided ? 'bg-rose-50 line-through text-slate-400' : '' }}">
                                        <td class="px-4 py-2 text-xs">{{ \Carbon\Carbon::parse($c->charge_date)->format('d M') }}</td>
                                        <td class="px-4 py-2 text-xs"><span class="px-2 py-0.5 rounded bg-slate-100">{{ $c->category }}</span></td>
                                        <td class="px-4 py-2">{{ $c->description }}</td>
                                        <td class="px-4 py-2 text-right text-xs">{{ rtrim(rtrim($c->quantity, '0'), '.') }} × ₹{{ number_format($c->rate, 0) }}</td>
                                        <td class="px-4 py-2 text-right">₹{{ number_format($c->amount, 2) }}</td>
                                        <td class="px-4 py-2 text-right text-xs">₹{{ number_format($c->tax_amount, 2) }}</td>
                                        <td class="px-4 py-2 text-right font-medium">₹{{ number_format($c->net_amount, 2) }}</td>
                                        <td class="px-4 py-2 text-right">@if(!$c->is_voided)<button type="button" wire:click="voidCharge({{ $c->id }})" wire:confirm="Void this charge?" class="text-[10px] text-rose-600 hover:underline">Void</button>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-4 py-6 text-center text-sm text-slate-500">No charges yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($folio->payments->count())
                        <div class="mt-4">
                            <div class="text-xs uppercase tracking-wider text-slate-500 mb-2">Payments</div>
                            <div class="border border-slate-200 rounded-lg overflow-hidden">
                                <table class="w-full text-sm">
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($folio->payments as $p)
                                            <tr><td class="px-4 py-2 text-xs">{{ $p->payment_date?->format('d M Y') }}</td><td class="px-4 py-2 font-mono text-xs">{{ $p->receipt_number }}</td><td class="px-4 py-2">{{ ucfirst(str_replace('_',' ',$p->mode)) }}</td><td class="px-4 py-2 text-right font-medium text-emerald-700">₹{{ number_format($p->amount, 2) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-12 text-sm text-slate-500">No folios yet. Posting a charge or payment will create one.</div>
            @endforelse
        @endif

        {{-- TAB: Post charge --}}
        @if($tab === 'charges')
            <form wire:submit.prevent="postCharge" class="grid md:grid-cols-2 gap-4 max-w-3xl">
                <div><label class="block text-xs font-medium mb-1">Category *</label>
                    <select wire:model="chargeCategory" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="food">Food</option><option value="beverage">Beverage</option>
                        <option value="laundry">Laundry</option><option value="mini_bar">Mini bar</option>
                        <option value="spa">Spa</option><option value="telephone">Telephone</option>
                        <option value="extra_bed">Extra bed</option><option value="package">Package</option>
                        <option value="damage">Damage</option><option value="misc">Miscellaneous</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Reference</label><input type="text" wire:model="chargeReference" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Description *</label><input type="text" wire:model="chargeDescription" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Quantity *</label><input type="number" step="0.01" wire:model="chargeQuantity" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Rate (₹) *</label><input type="number" step="0.01" wire:model="chargeRate" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Tax % *</label>
                    <select wire:model="chargeTaxPercent" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="0">0%</option><option value="5">5%</option><option value="12">12%</option><option value="18">18%</option><option value="28">28%</option>
                    </select>
                </div>
                <div class="md:col-span-2 flex justify-end pt-2 border-t"><button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Post charge</button></div>
            </form>
        @endif

        {{-- TAB: Post payment (split — multiple modes in one settlement) --}}
        @if($tab === 'payments')
            <form wire:submit.prevent="postPayment" class="max-w-3xl">
                <div class="text-xs text-slate-500 mb-3">Settle the full balance using one or more modes. Add a line for each (e.g. partial cash + partial card).</div>
                @php $totalLines = collect($payLines)->sum(fn($l)=>(float)($l['amount'] ?? 0)); @endphp
                <div class="space-y-2 mb-3">
                    @foreach($payLines as $i => $line)
                        <div class="grid grid-cols-12 gap-2 items-end p-3 bg-slate-50 rounded-lg">
                            <div class="col-span-3"><label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Mode</label>
                                <select wire:model="payLines.{{ $i }}.mode" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                    <option value="cash">Cash</option><option value="card">Card</option><option value="upi">UPI</option>
                                    <option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option>
                                    <option value="company_credit">City ledger</option><option value="wallet">Wallet</option>
                                    <option value="advance_adjustment">Advance adj</option><option value="ota_collect">OTA collect</option>
                                    <option value="gift_voucher">Gift voucher</option>
                                </select>
                            </div>
                            <div class="col-span-3"><label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Amount (₹)</label>
                                <input type="number" step="0.01" wire:model="payLines.{{ $i }}.amount" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                            </div>
                            <div class="col-span-5"><label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Reference (txn/UTR/last-4)</label>
                                <input type="text" wire:model="payLines.{{ $i }}.reference" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                            </div>
                            <div class="col-span-1">
                                @if(count($payLines) > 1)<button type="button" wire:click="removePayLine({{ $i }})" class="text-rose-600 text-sm">×</button>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="addPayLine" class="text-sm text-brand-600 mb-4">+ Add another payment line</button>
                <div class="mb-3"><label class="block text-xs font-medium mb-1">Note</label><input type="text" wire:model="payNote" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Settlement note (optional)"></div>

                <div class="flex items-center justify-between pt-3 border-t">
                    <div class="text-sm">
                        <div class="text-slate-500">Lines total: <span class="font-bold text-slate-900">₹{{ number_format($totalLines, 2) }}</span></div>
                        <div class="text-slate-500">Balance: <span class="font-bold {{ $reservation->balance_amount > 0 ? 'text-rose-600' : 'text-emerald-700' }}">₹{{ number_format($reservation->balance_amount, 2) }}</span></div>
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Record payment(s)</button>
                </div>
            </form>
        @endif

        {{-- TAB: Status --}}
        @if($tab === 'status')
            <form wire:submit.prevent="changeStatus" class="max-w-lg">
                <div class="mb-3"><label class="block text-xs font-medium mb-1">New status *</label>
                    <select wire:model="newStatus" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">Select…</option>
                        <option value="tentative">Tentative</option><option value="confirmed">Confirmed</option>
                        <option value="checked_in">Checked in</option><option value="checked_out">Checked out</option>
                        <option value="cancelled">Cancelled</option><option value="no_show">No show</option>
                    </select>
                </div>
                <div class="mb-3"><label class="block text-xs font-medium mb-1">Note</label><textarea wire:model="statusNote" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
                <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Update status</button>
            </form>
        @endif

        {{-- TAB: Cancel --}}
        @if($tab === 'cancel')
            @if($reservation->status === 'cancelled')
                <div class="bg-rose-50 border border-rose-200 rounded-lg p-5">
                    <div class="font-semibold text-rose-900 mb-2">Reservation cancelled</div>
                    <div class="text-sm text-rose-800">Cancelled at: {{ $reservation->cancelled_at?->format('d M Y H:i') }}</div>
                    <div class="text-sm text-rose-800 mt-1">Reason: {{ $reservation->cancellation_reason ?: '—' }}</div>
                    <div class="text-sm text-rose-800 mt-1">Cancellation charge: ₹{{ number_format($reservation->cancellation_charge ?? 0, 2) }}</div>
                </div>
            @else
                <div class="bg-rose-50 border border-rose-200 rounded-lg p-5 mb-4">
                    <div class="font-semibold text-rose-900 mb-1">Cancel this reservation?</div>
                    <p class="text-sm text-rose-800">This will free the room, void any open folio, and log the cancellation. Apply a charge if your cancellation policy requires it.</p>
                </div>
                <form wire:submit.prevent="cancelReservation" class="max-w-2xl space-y-3">
                    <div><label class="block text-xs font-medium mb-1">Reason *</label><textarea wire:model="cancelReason" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Guest requested / OTA cancellation / no payment received…"></textarea>@error('cancelReason')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror</div>
                    <div><label class="block text-xs font-medium mb-1">Cancellation charge (₹)</label><input type="number" step="0.01" wire:model="cancellationCharge" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <button class="bg-rose-600 hover:bg-rose-700 text-white font-semibold px-5 py-2 rounded-lg text-sm">Cancel reservation</button>
                </form>
            @endif
        @endif

        {{-- TAB: Guest --}}
        @if($tab === 'guest')
            <dl class="grid md:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs uppercase tracking-wider text-slate-500 mb-1">Name</dt><dd class="font-semibold">{{ $reservation->guest_name }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-slate-500 mb-1">Phone</dt><dd>{{ $reservation->guest_phone ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-slate-500 mb-1">Email</dt><dd>{{ $reservation->guest_email ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-slate-500 mb-1">Source</dt><dd>{{ $reservation->source_name ?: $reservation->source_type ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-slate-500 mb-1">Market segment</dt><dd>{{ $reservation->market_segment ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-slate-500 mb-1">Business source</dt><dd>{{ $reservation->business_source ?: '—' }}</dd></div>
                @if($reservation->guest_id)
                    <div class="md:col-span-2 pt-3 border-t"><a href="{{ route('crm.guest.show', $reservation->guest_id) }}" class="text-sm text-brand-600 font-medium">View full guest profile →</a></div>
                @endif
            </dl>
        @endif

        </div>
    </div>
</div>
