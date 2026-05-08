<?php

namespace App\Livewire\HotelSite;

use Livewire\Attributes\Layout;

#[Layout('layouts.hotel-site')]
class Gallery extends HotelSiteBase
{
    public function render()
    {
        return view('livewire.hotel-site.gallery');
    }
}
