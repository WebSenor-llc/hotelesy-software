<?php

namespace App\Livewire\Compliance;

use App\Models\Compliance\FormCSubmission;
use App\Models\Reservation;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app-shell')]
class PoliceRegister extends Component
{
    public string $fromDate;
    public string $toDate;
    public bool $isRange = false;

    public function mount(): void
    {
        $this->fromDate = today()->toDateString();
        $this->toDate = today()->toDateString();
    }

    public function setSingleDay(string $date): void
    {
        $this->isRange = false;
        $this->fromDate = $date;
        $this->toDate = $date;
    }

    public function toggleRange(): void
    {
        $this->isRange = !$this->isRange;
        if (!$this->isRange) {
            $this->toDate = $this->fromDate;
        }
    }

    public function printNow(): void
    {
        $this->dispatch('print-police-register');
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->loadRows();

        $filename = 'police-register-' . $this->fromDate . ($this->isRange ? '-to-' . $this->toDate : '') . '.csv';

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Sl no', 'Name', 'Nationality', 'Passport #', 'Visa #',
                'Arrived from', 'Date in', 'Date out', 'Room',
                'Purpose', 'Form C number',
            ]);
            $i = 1;
            foreach ($rows as $r) {
                fputcsv($out, [
                    $i++,
                    $r['name'],
                    $r['nationality'],
                    $r['passport'],
                    $r['visa'],
                    $r['arrived_from'],
                    $r['date_in'],
                    $r['date_out'],
                    $r['room'],
                    $r['purpose'],
                    $r['form_c_number'],
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function loadRows(): array
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();

        $from = Carbon::parse($this->fromDate)->startOfDay();
        $to = Carbon::parse($this->isRange ? $this->toDate : $this->fromDate)->endOfDay();

        // Foreign nationals in-house at any point during the range:
        // arrival_date <= range end AND departure_date > range start AND status in (checked_in, checked_out)
        $reservations = Reservation::query()
            ->with(['guest', 'rooms.room'])
            ->where('property_id', $property->id)
            ->whereHas('guest', fn ($q) => $q->where('is_foreign_national', true))
            ->whereIn('status', [
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_CHECKED_OUT,
            ])
            ->where('arrival_date', '<=', $to)
            ->where('departure_date', '>', $from)
            ->orderBy('arrival_date')
            ->get();

        $forms = FormCSubmission::where('property_id', $property->id)
            ->whereIn('reservation_id', $reservations->pluck('id'))
            ->get()
            ->keyBy('reservation_id');

        return $reservations->map(function ($r) use ($forms) {
            $g = $r->guest;
            $room = optional($r->rooms->first()?->room)->number ?? '—';
            $form = $forms->get($r->id);
            return [
                'name' => trim(($g?->first_name ?? '') . ' ' . ($g?->last_name ?? '')) ?: $r->guest_name,
                'nationality' => $g?->nationality ?? '',
                'passport' => $g?->passport_number ?? '',
                'visa' => $g?->visa_number ?? '',
                'arrived_from' => $g?->arrival_from_country ?? '',
                'date_in' => $r->arrival_date?->format('d M Y'),
                'date_out' => $r->departure_date?->format('d M Y'),
                'room' => $room,
                'purpose' => $r->market_segment ?? $r->source_type ?? 'Tourism',
                'form_c_number' => $form?->form_number ?? '—',
            ];
        })->all();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        return view('livewire.compliance.police-register', [
            'rows' => $this->loadRows(),
            'property' => $ctx->property(),
        ]);
    }
}
