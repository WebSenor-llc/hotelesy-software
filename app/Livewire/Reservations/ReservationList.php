<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class ReservationList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function updating($name): void
    {
        if (in_array($name, ['search','statusFilter'])) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $query = Reservation::where('property_id', $propertyId)
            ->orderByDesc('created_at');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('guest_name', 'like', $term)
                  ->orWhere('reservation_number', 'like', $term)
                  ->orWhere('confirmation_number', 'like', $term)
                  ->orWhere('guest_phone', 'like', $term)
                  ->orWhere('guest_email', 'like', $term);
            });
        }
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return view('livewire.reservations.reservation-list', [
            'reservations' => $query->paginate(15),
            'statuses' => [
                Reservation::STATUS_TENTATIVE,
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_CHECKED_OUT,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_NO_SHOW,
            ],
        ]);
    }
}
