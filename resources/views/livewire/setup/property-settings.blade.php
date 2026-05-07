<div>
    <div class="flex items-baseline justify-between mb-1"><h1 class="text-2xl font-bold">Property settings</h1><a href="{{ route('setup.hub') }}" class="text-sm text-slate-600">← Setup</a></div>
    <p class="text-sm text-slate-600 mb-6">Address, GST, FSSAI, business hours.</p>
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    <form wire:submit.prevent="save" class="bg-white rounded-xl border p-6 grid md:grid-cols-3 gap-4">
        <div class="md:col-span-3"><h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Identity</h2></div>
        <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Legal name</label><input type="text" wire:model="legal_name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>

        <div class="md:col-span-3 mt-2 pt-3 border-t"><h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Address</h2></div>
        <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Address</label><textarea wire:model="address" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
        <div><label class="block text-xs font-medium mb-1">City</label><input type="text" wire:model="city" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">State</label><input type="text" wire:model="state" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Postal code</label><input type="text" wire:model="postal_code" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Country (ISO-2) *</label><input type="text" wire:model="country" maxlength="2" class="w-full px-3 py-2 border rounded-lg text-sm"></div>

        <div class="md:col-span-3 mt-2 pt-3 border-t"><h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Contact</h2></div>
        <div><label class="block text-xs font-medium mb-1">Phone</label><input type="tel" wire:model="phone" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Email</label><input type="email" wire:model="email" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Website</label><input type="url" wire:model="website" class="w-full px-3 py-2 border rounded-lg text-sm"></div>

        <div class="md:col-span-3 mt-2 pt-3 border-t"><h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Statutory</h2></div>
        <div>
            <label class="block text-xs font-medium mb-1">GSTIN</label>
            <input type="text" wire:model="gst_number" class="w-full px-3 py-2 border rounded-lg text-sm font-mono uppercase" placeholder="29ABCDE1234F1Z5">
            @error('gst_number')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium mb-1">PAN</label>
            <input type="text" wire:model="pan_number" class="w-full px-3 py-2 border rounded-lg text-sm font-mono uppercase" placeholder="ABCDE1234F">
            @error('pan_number')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
        </div>
        <div><label class="block text-xs font-medium mb-1">FSSAI</label><input type="text" wire:model="fssai_number" class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></div>
        <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Liquor license</label><input type="text" wire:model="liquor_license" class="w-full px-3 py-2 border rounded-lg text-sm"></div>

        <div class="md:col-span-3 mt-2 pt-3 border-t"><h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Operations</h2></div>
        <div><label class="block text-xs font-medium mb-1">Check-in time *</label><input type="time" wire:model="check_in_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Check-out time *</label><input type="time" wire:model="check_out_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Night audit time *</label><input type="time" wire:model="night_audit_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Currency *</label><input type="text" wire:model="currency" maxlength="3" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Timezone *</label><input type="text" wire:model="timezone" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Status *</label>
            <select wire:model="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="active">Active</option><option value="inactive">Inactive</option><option value="setup">Setup mode</option>
            </select>
        </div>
        <div><label class="block text-xs font-medium mb-1">Total rooms</label><input type="number" wire:model="total_rooms" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Floors</label><input type="number" wire:model="floors" class="w-full px-3 py-2 border rounded-lg text-sm"></div>

        {{-- ============= ECI / LCO / NO-SHOW POLICY ============= --}}
        <div class="md:col-span-3 mt-4 pt-4 border-t">
            <h3 class="text-sm font-semibold text-slate-900 mb-1">Early-check-in, late-check-out & no-show policy</h3>
            <p class="text-xs text-slate-500 mb-3">Fees are auto-posted to the guest folio at check-in/check-out time. % is calculated on one night's room rate.</p>
        </div>

        <div class="md:col-span-3 grid md:grid-cols-4 gap-3 p-4 rounded-xl bg-amber-50 border border-amber-200">
            <div class="md:col-span-4 text-xs font-semibold uppercase tracking-wider text-amber-800">Early Check-In (ECI)</div>
            <div><label class="block text-xs font-medium mb-1">Free grace (hours)</label>
                <input type="number" min="0" max="24" wire:model="eci_grace_hours" class="w-full px-3 py-2 border rounded-lg text-sm">
                <p class="text-[10px] text-slate-500 mt-0.5">Earlier than this → free</p>
            </div>
            <div><label class="block text-xs font-medium mb-1">Half-day threshold (hours)</label>
                <input type="number" min="0" max="24" wire:model="eci_half_day_threshold_hours" class="w-full px-3 py-2 border rounded-lg text-sm">
                <p class="text-[10px] text-slate-500 mt-0.5">Past grace, up to this many hrs early → half-day fee</p>
            </div>
            <div><label class="block text-xs font-medium mb-1">Half-day fee (% of nightly)</label>
                <input type="number" min="0" max="100" wire:model="eci_half_day_pct" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div><label class="block text-xs font-medium mb-1">Full-day fee (% of nightly)</label>
                <input type="number" min="0" max="100" wire:model="eci_full_day_pct" class="w-full px-3 py-2 border rounded-lg text-sm">
                <p class="text-[10px] text-slate-500 mt-0.5">Beyond half-day threshold</p>
            </div>
        </div>

        <div class="md:col-span-3 grid md:grid-cols-4 gap-3 p-4 rounded-xl bg-rose-50 border border-rose-200">
            <div class="md:col-span-4 text-xs font-semibold uppercase tracking-wider text-rose-800">Late Check-Out (LCO)</div>
            <div><label class="block text-xs font-medium mb-1">Free grace (hours)</label>
                <input type="number" min="0" max="24" wire:model="lco_grace_hours" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div><label class="block text-xs font-medium mb-1">Half-day threshold (hours)</label>
                <input type="number" min="0" max="24" wire:model="lco_half_day_threshold_hours" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div><label class="block text-xs font-medium mb-1">Half-day fee (% of nightly)</label>
                <input type="number" min="0" max="100" wire:model="lco_half_day_pct" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div><label class="block text-xs font-medium mb-1">Full-day fee (% of nightly)</label>
                <input type="number" min="0" max="100" wire:model="lco_full_day_pct" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
        </div>

        <div class="md:col-span-3 grid md:grid-cols-4 gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200 items-end">
            <div class="md:col-span-4 text-xs font-semibold uppercase tracking-wider text-slate-700">No-Show Policy</div>
            <div><label class="block text-xs font-medium mb-1">No-show fee (% of first night)</label>
                <input type="number" min="0" max="100" wire:model="noshow_fee_pct_first_night" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div><label class="block text-xs font-medium mb-1">Auto-mark after (hours past arrival time)</label>
                <input type="number" min="0" max="48" wire:model="noshow_grace_hours_after_arrival" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="auto_mark_no_show">
                    <span>Auto-mark no-shows hourly via cron</span>
                </label>
                <p class="text-[10px] text-slate-500 mt-0.5">Disable to handle manually from the reservation list.</p>
            </div>
        </div>

        <div class="md:col-span-3 flex justify-end pt-4 border-t"><button class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">Save settings</button></div>
    </form>
</div>
