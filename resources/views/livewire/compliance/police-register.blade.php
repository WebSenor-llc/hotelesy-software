<div x-data="{}" @print-police-register.window="window.print()">
    <style>
        @media print {
            body { background: white !important; }
            nav, aside, header.app-header, .no-print, [data-no-print] { display: none !important; }
            .print-wrap { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .bg-white, .bg-slate-50 { background: white !important; }
            .border, .border-slate-200, .border-b, .border-t { border-color: #444 !important; }
            button, .no-print-btn { display: none !important; }
            .rounded-xl, .rounded { border-radius: 0 !important; }
            .shadow, .shadow-sm, .shadow-md { box-shadow: none !important; }
        }
        .police-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .police-table th, .police-table td { border: 1px solid #888; padding: 4px 6px; text-align: left; vertical-align: top; }
        .police-table th { background: #f4f4f5; font-weight: 600; font-size: 10px; }
    </style>

    <div class="print-wrap">
        <div class="flex items-baseline justify-between mb-1 no-print">
            <h1 class="text-2xl font-bold text-slate-900">Daily Police register — foreign guests</h1>
        </div>
        <p class="text-sm text-slate-600 mb-4 no-print">Daily register of foreign-national guests required by local police under hospitality regulations.</p>

        <div class="bg-white rounded-xl border p-4 mb-4 flex flex-wrap gap-3 items-end no-print">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">From date</label>
                <input type="date" wire:model.live="fromDate" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            @if($isRange)
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">To date</label>
                    <input type="date" wire:model.live="toDate" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
            @endif
            <button type="button" wire:click="toggleRange" class="px-3 py-2 border border-slate-300 rounded-lg text-sm hover:bg-slate-50">
                {{ $isRange ? 'Single day' : 'Date range' }}
            </button>
            <div class="flex-1"></div>
            <button type="button" wire:click="printNow" class="px-3 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded text-sm">Print</button>
            <button type="button" wire:click="exportCsv" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Download CSV</button>
        </div>

        <div class="bg-white rounded-xl border p-6">
            <div class="text-center mb-4">
                <div class="text-xs uppercase tracking-widest text-slate-500">Police register — Foreign Guests</div>
                <div class="text-base font-bold mt-1">{{ $property?->name ?? 'Hotel' }}</div>
                <div class="text-xs text-slate-700">{{ $property?->address ?? '' }}, {{ $property?->city ?? '' }}</div>
                <div class="text-xs mt-1">
                    {{ $isRange ? 'For date range: '.\Carbon\Carbon::parse($fromDate)->format('d M Y').' – '.\Carbon\Carbon::parse($toDate)->format('d M Y') : 'For date: '.\Carbon\Carbon::parse($fromDate)->format('d M Y') }}
                </div>
            </div>

            @if(count($rows))
                <table class="police-table">
                    <thead>
                        <tr>
                            <th style="width:32px">Sl</th>
                            <th>Name</th>
                            <th>Nationality</th>
                            <th>Passport #</th>
                            <th>Visa #</th>
                            <th>Arrived from</th>
                            <th>Date in</th>
                            <th>Date out</th>
                            <th>Room</th>
                            <th>Purpose</th>
                            <th>Form C</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $i => $r)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $r['name'] }}</td>
                                <td>{{ $r['nationality'] }}</td>
                                <td>{{ $r['passport'] }}</td>
                                <td>{{ $r['visa'] }}</td>
                                <td>{{ $r['arrived_from'] }}</td>
                                <td>{{ $r['date_in'] }}</td>
                                <td>{{ $r['date_out'] }}</td>
                                <td>{{ $r['room'] }}</td>
                                <td>{{ $r['purpose'] }}</td>
                                <td>{{ $r['form_c_number'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="grid grid-cols-2 gap-12 mt-12 pt-12 text-xs">
                    <div>
                        <div class="border-t border-slate-700 pt-2">Signature of Manager</div>
                        <div class="text-slate-500 mt-1">Date: {{ now()->format('d M Y') }}</div>
                    </div>
                    <div>
                        <div class="border-t border-slate-700 pt-2">Signature of Police Officer (on inspection)</div>
                    </div>
                </div>
            @else
                <div class="px-5 py-12 text-sm text-slate-500 text-center">No foreign guests in this period.</div>
            @endif
        </div>
    </div>
</div>
