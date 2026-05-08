<?php

namespace App\Livewire\HotelSite;

use App\Models\RoomType;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;

#[Layout('layouts.hotel-site')]
class Home extends HotelSiteBase
{
    public string $checkIn = '';
    public string $checkOut = '';
    public int $adults = 2;
    public int $children = 0;
    public int $rooms = 1;

    public function mounted(?string $roomTypeCode = null): void
    {
        $this->checkIn  = today()->copy()->addDay()->toDateString();
        $this->checkOut = today()->copy()->addDays(2)->toDateString();
    }

    public function searchAvailability(): void
    {
        $isDev = ! str_contains((string) request()->getHost(), '.');
        $route = $isDev ? 'hotel.book.dev' : 'hotel.book';
        $this->redirect(route($route, [
            'tenant_slug' => $this->tenant_slug,
            'check_in'    => $this->checkIn,
            'check_out'   => $this->checkOut,
            'adults'      => $this->adults,
            'children'    => $this->children,
            'rooms'       => $this->rooms,
        ]), navigate: true);
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $roomTypes = $ctx->bypass(function () {
            return RoomType::where('property_id', $this->property?->id ?? 0)
                ->where('is_active', true)
                ->orderBy('base_rate')
                ->limit(6)
                ->get();
        });

        return view('livewire.hotel-site.home', [
            'roomTypes' => $roomTypes,
        ]);
    }
}
