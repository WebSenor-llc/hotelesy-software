<?php

namespace App\Livewire\HotelSite;

use App\Models\RoomType;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;

#[Layout('layouts.hotel-site')]
class RoomDetail extends HotelSiteBase
{
    /** Untyped — typed Model property triggers ImplicitRouteBinding which 404s. */
    public $roomType = null;

    public function mounted(?string $roomTypeCode = null): void
    {
        if (! $roomTypeCode) abort(404);
        $this->roomType = app(TenantContext::class)->bypass(function () use ($roomTypeCode) {
            return RoomType::where('property_id', $this->property?->id ?? 0)
                ->where('code', $roomTypeCode)
                ->where('is_active', true)
                ->first();
        });
        if (! $this->roomType) abort(404, 'Room not found.');
    }

    public function render()
    {
        return view('livewire.hotel-site.room-detail');
    }
}
