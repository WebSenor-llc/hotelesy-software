<div x-data="{}" @print-form-c.window="window.print()">
    <style>
        @media print {
            body { background: white !important; }
            nav, aside, header.app-header, .no-print, [data-no-print] { display: none !important; }
            .print-wrap { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .bg-white, .bg-slate-50 { background: white !important; }
            .border, .border-slate-200, .border-b, .border-t { border-color: #444 !important; }
            button, .no-print-btn { display: none !important; }
            .rounded-xl, .rounded, .rounded-full { border-radius: 0 !important; }
            .shadow, .shadow-sm, .shadow-md { box-shadow: none !important; }
            .form-c-doc { font-size: 11pt; line-height: 1.4; }
        }
        .form-c-doc table { width: 100%; border-collapse: collapse; }
        .form-c-doc th, .form-c-doc td { border: 1px solid #888; padding: 6px 8px; text-align: left; vertical-align: top; }
        .form-c-doc th { background: #f4f4f5; font-weight: 600; width: 30%; }
    </style>

    @php
        $r = $reservation;
        $g = $r->guest;
        $p = $r->property;
        $room = optional($r->rooms->first()?->room)->number ?? '—';
        $today = now()->format('d M Y');
    @endphp

    <div class="max-w-4xl mx-auto print-wrap">
        <div class="flex items-center justify-between mb-4 no-print">
            <h1 class="text-2xl font-bold text-slate-900">Form C — FRRO submission</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('compliance.form-c') }}" class="text-sm text-slate-600">← Back to list</a>
                <button type="button" wire:click="printNow" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded text-sm">Print</button>
            </div>
        </div>

        <div class="form-c-doc bg-white rounded-xl border p-8 shadow-sm">
            <div class="text-center mb-6">
                <div class="text-xs uppercase tracking-widest text-slate-500">Government of India · Bureau of Immigration</div>
                <div class="text-xl font-bold mt-1">FORM C</div>
                <div class="text-sm text-slate-700">Arrival Report of Foreigner in Hotel (Section 14, Foreigners Act, 1946)</div>
                @if($submission)
                    <div class="text-xs mt-2 font-mono">Form #: <strong>{{ $submission->form_number }}</strong> · Generated {{ $submission->generated_at->format('d M Y H:i') }}</div>
                @endif
            </div>

            <h3 class="font-semibold text-sm uppercase tracking-wider mb-2 mt-6">Hotel details</h3>
            <table>
                <tr><th>Hotel name</th><td>{{ $p->name }} ({{ $p->legal_name ?? '' }})</td></tr>
                <tr><th>Address</th><td>{{ $p->address }}, {{ $p->city }}, {{ $p->state }} {{ $p->postal_code }}, {{ $p->country }}</td></tr>
                <tr><th>Phone / Email</th><td>{{ $p->phone ?? '—' }} · {{ $p->email ?? '—' }}</td></tr>
                <tr><th>License / Registration</th><td>GSTIN: {{ $p->gst_number ?? '—' }} · PAN: {{ $p->pan_number ?? '—' }} · Liquor: {{ $p->liquor_license ?? '—' }}</td></tr>
            </table>

            <h3 class="font-semibold text-sm uppercase tracking-wider mb-2 mt-6">Guest details</h3>
            <table>
                <tr><th>Salutation</th><td>{{ $g->salutation ?? '—' }}</td></tr>
                <tr><th>Full name</th><td>{{ trim(($g->first_name ?? '').' '.($g->last_name ?? '')) ?: $r->guest_name }}</td></tr>
                <tr><th>Nationality</th><td>{{ $g->nationality ?? '—' }}</td></tr>
                <tr><th>Date of birth</th><td>{{ $g->dob?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Sex</th><td>{{ $g->gender ?? '—' }}</td></tr>
                <tr><th>Passport number</th><td>{{ $g->passport_number ?? '—' }}</td></tr>
                <tr><th>Passport expiry</th><td>{{ $g->passport_expiry?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Visa number</th><td>{{ $g->visa_number ?? '—' }}</td></tr>
                <tr><th>Visa expiry</th><td>{{ $g->visa_expiry?->format('d M Y') ?? '—' }}</td></tr>
            </table>

            <h3 class="font-semibold text-sm uppercase tracking-wider mb-2 mt-6">Arrival in India</h3>
            <table>
                <tr><th>Arrived from (country)</th><td>{{ $g->arrival_from_country ?? '—' }}</td></tr>
                <tr><th>Date of arrival in India</th><td>{{ $g->arrival_date_in_india?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Next destination country</th><td>{{ $g->next_destination_country ?? '—' }}</td></tr>
                <tr><th>Next destination address</th><td>{{ $g->next_destination ?? '—' }}</td></tr>
            </table>

            <h3 class="font-semibold text-sm uppercase tracking-wider mb-2 mt-6">Stay details</h3>
            <table>
                <tr><th>Reservation number</th><td>{{ $r->reservation_number }}</td></tr>
                <tr><th>Date of check-in</th><td>{{ $r->arrival_date?->format('d M Y') }} {{ $r->arrival_time }}</td></tr>
                <tr><th>Date of check-out (proposed)</th><td>{{ $r->departure_date?->format('d M Y') }}</td></tr>
                <tr><th>Room number</th><td>{{ $room }}</td></tr>
                <tr><th>Purpose of visit</th><td>{{ $r->market_segment ?? $r->source_type ?? 'Tourism' }}</td></tr>
            </table>

            <div class="grid grid-cols-2 gap-12 mt-12 pt-12">
                <div>
                    <div class="border-t border-slate-700 pt-2 text-sm">Signature of Manager / Owner of Hotel</div>
                    <div class="text-xs text-slate-500 mt-2">Name: __________________________</div>
                    <div class="text-xs text-slate-500">Date: {{ $today }}</div>
                </div>
                <div>
                    <div class="border-t border-slate-700 pt-2 text-sm">Signature of the Foreigner</div>
                    <div class="text-xs text-slate-500 mt-2">Name: {{ trim(($g->first_name ?? '').' '.($g->last_name ?? '')) }}</div>
                    <div class="text-xs text-slate-500">Date: {{ $today }}</div>
                </div>
            </div>

            <div class="text-[10px] text-slate-500 mt-8 border-t pt-2">
                File this Form C with FRRO/FRO via <strong>indianfrro.gov.in</strong> within 24 hours of guest's arrival.
                Failure to comply attracts penalties under the Foreigners Act, 1946.
            </div>
        </div>
    </div>
</div>
