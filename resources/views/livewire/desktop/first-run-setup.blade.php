<div class="min-h-screen bg-gradient-to-br from-slate-50 via-brand-50 to-slate-100 py-10 px-4">
    <div class="max-w-3xl mx-auto">
        {{-- Brand header --}}
        <div class="text-center mb-6">
            <div class="inline-flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-xl shadow-lg">H</div>
                <div class="text-left">
                    <div class="font-bold text-slate-900 text-lg leading-tight">Hotelesy <span class="text-xs font-normal text-slate-500">Desktop</span></div>
                    <div class="text-xs text-slate-500">Let's set up your hotel</div>
                </div>
            </div>
        </div>

        {{-- Stepper --}}
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="grid grid-cols-3 border-b border-slate-200">
                @foreach([
                    1 => ['Hotel basics',   '🏨'],
                    2 => ['Owner login',    '🔐'],
                    3 => ['Room types',     '🛏️'],
                ] as $idx => [$label, $icon])
                    <div class="flex items-center gap-3 px-5 py-4
                        {{ $step === $idx ? 'bg-gradient-to-br from-brand-50 to-white border-b-2 border-brand-500' : '' }}
                        {{ $step > $idx ? 'opacity-70' : '' }}">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold
                            {{ $step >= $idx ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500' }}">
                            {{ $step > $idx ? '✓' : $idx }}
                        </div>
                        <div>
                            <div class="text-[10px] uppercase text-slate-500 tracking-wider font-semibold">Step {{ $idx }}</div>
                            <div class="text-sm font-semibold text-slate-900">{{ $icon }} {{ $label }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($error)
                <div class="m-6 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-sm text-rose-800">
                    <strong>Setup failed:</strong> {{ $error }}
                </div>
            @endif

            <div class="p-7">
                {{-- ============================================== STEP 1 --}}
                @if($step === 1)
                    <h2 class="text-xl font-bold text-slate-900 mb-1">Tell us about your hotel</h2>
                    <p class="text-sm text-slate-600 mb-5">This becomes your default property. You can edit it any time from Setup → Properties.</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Hotel name <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="hotelName" placeholder="e.g. Hotel Miraj"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('hotelName')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">City <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="city" placeholder="Udaipur"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('city')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">State <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="state" placeholder="Rajasthan"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('state')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">State GST code</label>
                            <input type="text" wire:model.blur="stateCode" placeholder="08"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none font-mono">
                            <div class="text-[10px] text-slate-500 mt-1">2-digit code, e.g. 08 = Rajasthan, 27 = Maharashtra</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">GSTIN (optional)</label>
                            <input type="text" wire:model.blur="gstin" placeholder="08AABCU9603R1ZX"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none font-mono uppercase">
                            @error('gstin')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Phone</label>
                            <input type="text" wire:model.blur="phone" placeholder="+91 98XXX XXXXX"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Hotel email</label>
                            <input type="email" wire:model.blur="hotelEmail" placeholder="reservations@hotel.com"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('hotelEmail')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endif

                {{-- ============================================== STEP 2 --}}
                @if($step === 2)
                    <h2 class="text-xl font-bold text-slate-900 mb-1">Create the owner account</h2>
                    <p class="text-sm text-slate-600 mb-5">This account has full administrative rights. You can add more users later from Setup → Users.</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Owner name <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="ownerName" placeholder="e.g. Rajesh Sharma"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('ownerName')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email address <span class="text-rose-500">*</span></label>
                            <input type="email" wire:model.blur="ownerEmail" placeholder="owner@hotel.com"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('ownerEmail')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                            <div class="text-[10px] text-slate-500 mt-1">You'll use this to sign in.</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Password <span class="text-rose-500">*</span></label>
                            <input type="password" wire:model.blur="password"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('password')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                            <div class="text-[10px] text-slate-500 mt-1">Minimum 6 characters.</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Confirm password <span class="text-rose-500">*</span></label>
                            <input type="password" wire:model.blur="passwordConfirmation"
                                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            @error('passwordConfirmation')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mt-5 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900">
                        <strong>⚠ Save this password somewhere safe.</strong>
                        Hotelesy is offline-first — there's no "forgot password" email. If you lose it, you'll need to reset it via the desktop terminal.
                    </div>
                @endif

                {{-- ============================================== STEP 3 --}}
                @if($step === 3)
                    <h2 class="text-xl font-bold text-slate-900 mb-1">Configure your room types</h2>
                    <p class="text-sm text-slate-600 mb-5">Add or edit your initial inventory. Each row creates a room category — you can add individual rooms after setup.</p>

                    <div class="rounded-lg border border-slate-200 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Code</th>
                                    <th class="px-3 py-2 text-left font-semibold">Name</th>
                                    <th class="px-3 py-2 text-right font-semibold">Base rate (₹)</th>
                                    <th class="px-3 py-2 text-center font-semibold">Occupancy</th>
                                    <th class="px-3 py-2 text-center font-semibold"># rooms</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($roomTypes as $i => $rt)
                                    <tr class="border-b border-slate-100 last:border-0">
                                        <td class="px-3 py-2">
                                            <input type="text" wire:model="roomTypes.{{ $i }}.code"
                                                   class="w-20 px-2 py-1.5 text-xs font-mono uppercase border border-slate-300 rounded outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text" wire:model="roomTypes.{{ $i }}.name"
                                                   class="w-full px-2 py-1.5 text-xs border border-slate-300 rounded outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" min="0" step="100" wire:model="roomTypes.{{ $i }}.rate"
                                                   class="w-24 px-2 py-1.5 text-xs text-right border border-slate-300 rounded outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500">
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="number" min="1" max="6" wire:model="roomTypes.{{ $i }}.occupancy"
                                                   class="w-16 px-2 py-1.5 text-xs text-center border border-slate-300 rounded outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500">
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="number" min="0" max="500" wire:model="roomTypes.{{ $i }}.count"
                                                   class="w-16 px-2 py-1.5 text-xs text-center border border-slate-300 rounded outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 px-4 py-3 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600">
                        <strong class="text-slate-800">Tip:</strong> Leave a row's code or name blank to skip it. You can add more types and individual rooms once you're inside.
                    </div>
                @endif
            </div>

            {{-- Step controls --}}
            <div class="flex items-center justify-between bg-slate-50 border-t border-slate-200 px-6 py-4">
                <button type="button" wire:click="back"
                        @class([
                            'px-4 py-2 rounded-lg text-sm font-semibold border transition',
                            'bg-white border-slate-300 text-slate-700 hover:bg-slate-100' => $step > 1,
                            'invisible' => $step === 1,
                        ])>
                    ← Back
                </button>

                <div class="text-xs text-slate-500">Step {{ $step }} of 3</div>

                @if($step < 3)
                    <button type="button" wire:click="next"
                            class="px-5 py-2 rounded-lg text-sm font-semibold bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-600 hover:to-brand-800 text-white shadow-md transition">
                        Continue →
                    </button>
                @else
                    <button type="button" wire:click="finish"
                            wire:loading.attr="disabled" wire:target="finish"
                            class="px-6 py-2 rounded-lg text-sm font-bold bg-gradient-to-br from-emerald-500 to-emerald-700 hover:from-emerald-600 hover:to-emerald-800 text-white shadow-md transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="finish">Finish setup &amp; sign in</span>
                        <span wire:loading wire:target="finish">Setting up Hotelesy…</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-slate-500">
            © {{ date('Y') }} Hotelesy by WebSenor · Desktop edition
        </div>
    </div>
</div>
