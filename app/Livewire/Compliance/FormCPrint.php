<?php

namespace App\Livewire\Compliance;

use App\Models\Compliance\FormCSubmission;
use App\Models\Reservation;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class FormCPrint extends Component
{
    /**
     * Untyped — typed public Reservation property triggers Livewire's
     * ImplicitRouteBinding which collides with the BelongsToTenant
     * global scope. Same workaround used in ReservationDetail.
     */
    public $reservation;
    public $submission = null;

    public function mount(int $reservation): void
    {
        $ctx = app(TenantContext::class);
        $property = $ctx->property();

        $this->reservation = Reservation::where('property_id', $property->id)
            ->with(['guest', 'rooms.room', 'property'])
            ->findOrFail($reservation);

        $this->submission = FormCSubmission::where('property_id', $property->id)
            ->where('reservation_id', $this->reservation->id)
            ->orderByDesc('generated_at')
            ->first();
    }

    public function printNow(): void
    {
        $this->dispatch('print-form-c');
    }

    public function render()
    {
        return view('livewire.compliance.form-c-print');
    }
}
