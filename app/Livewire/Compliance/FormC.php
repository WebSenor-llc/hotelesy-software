<?php

namespace App\Livewire\Compliance;

use App\Models\Compliance\FormCSubmission;
use App\Models\Guest;
use App\Models\Reservation;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class FormC extends Component
{
    public int $daysBack = 30;
    public string $statusFilter = ''; // '', 'pending', 'submitted'
    public string $search = '';

    // Per-row "mark submitted" UI
    public ?int $markingSubmissionId = null;
    public string $ackNumber = '';
    public string $submissionMethod = 'printed';
    public string $submissionNotes = '';

    public function generateForm(int $reservationId): void
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();
        $reservation = Reservation::where('property_id', $property->id)
            ->with('guest')
            ->findOrFail($reservationId);

        if (!$reservation->guest_id) {
            session()->flash('error', 'Reservation has no linked guest.');
            return;
        }
        $guest = $reservation->guest;
        if (!$guest || !$guest->is_foreign_national) {
            session()->flash('error', 'Form C only applies to foreign nationals.');
            return;
        }

        DB::transaction(function () use ($ctx, $property, $reservation, $guest) {
            $year = now()->format('Y');
            $count = FormCSubmission::where('property_id', $property->id)
                ->whereYear('generated_at', $year)
                ->count();
            $formNumber = sprintf('FC/%s/%s/%04d', $property->code ?? 'P', $year, $count + 1);

            FormCSubmission::create([
                'tenant_id' => $ctx->tenantId(),
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'reservation_id' => $reservation->id,
                'form_number' => $formNumber,
                'generated_at' => now(),
                'generated_by' => auth()->id(),
                'submission_method' => 'printed',
            ]);
        });

        session()->flash('success', 'Form C generated. Open the print view to file with FRRO.');
        // Redirect to printable view
        $this->redirect(route('compliance.form-c.print', ['reservation' => $reservationId]));
    }

    public function startMarkSubmitted(int $submissionId): void
    {
        $this->markingSubmissionId = $submissionId;
        $this->ackNumber = '';
        $this->submissionMethod = 'portal';
        $this->submissionNotes = '';
    }

    public function cancelMark(): void
    {
        $this->markingSubmissionId = null;
    }

    public function markSubmitted(): void
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();

        $sub = FormCSubmission::where('property_id', $property->id)
            ->findOrFail($this->markingSubmissionId);

        $sub->update([
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
            'frro_acknowledgement_number' => $this->ackNumber ?: null,
            'submission_method' => $this->submissionMethod,
            'notes' => $this->submissionNotes ?: null,
        ]);

        $this->markingSubmissionId = null;
        $this->ackNumber = '';
        $this->submissionNotes = '';
        session()->flash('success', 'Form C marked as submitted.');
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();

        $cutoff = now()->subDays(max(1, $this->daysBack));

        $reservations = Reservation::query()
            ->with(['guest', 'rooms.room'])
            ->where('property_id', $property->id)
            ->whereHas('guest', fn ($q) => $q->where('is_foreign_national', true))
            ->whereIn('status', [
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_CHECKED_OUT,
            ])
            ->where('arrival_date', '>=', $cutoff)
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('guest_name', 'like', $term)
                        ->orWhere('reservation_number', 'like', $term)
                        ->orWhereHas('guest', function ($g) use ($term) {
                            $g->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('passport_number', 'like', $term);
                        });
                });
            })
            ->orderByDesc('arrival_date')
            ->get();

        // Latest submission per (guest, reservation)
        $submissions = FormCSubmission::where('property_id', $property->id)
            ->whereIn('reservation_id', $reservations->pluck('id'))
            ->orderByDesc('generated_at')
            ->get()
            ->keyBy('reservation_id');

        $rows = $reservations->map(function ($r) use ($submissions) {
            $sub = $submissions->get($r->id);
            $status = $sub
                ? ($sub->submitted_at ? 'submitted' : 'generated')
                : 'pending';
            return [
                'reservation' => $r,
                'guest' => $r->guest,
                'submission' => $sub,
                'status' => $status,
            ];
        });

        if ($this->statusFilter !== '') {
            $rows = $rows->where('status', $this->statusFilter)->values();
        }

        return view('livewire.compliance.form-c', [
            'rows' => $rows,
        ]);
    }
}
