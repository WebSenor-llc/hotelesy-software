<?php

namespace App\Livewire\HotelSite;

use App\Models\RoomType;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;

#[Layout('layouts.hotel-site')]
class RoomsList extends HotelSiteBase
{
    public function render()
    {
        $roomTypes = app(TenantContext::class)->bypass(function () {
            return RoomType::where('property_id', $this->property?->id ?? 0)
                ->where('is_active', true)
                ->orderBy('base_rate')
                ->get();
        });

        return view('livewire.hotel-site.rooms-list', compact('roomTypes'));
    }
}
