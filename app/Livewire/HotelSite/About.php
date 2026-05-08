<?php

namespace App\Livewire\HotelSite;

use Livewire\Attributes\Layout;

#[Layout('layouts.hotel-site')]
class About extends HotelSiteBase
{
    public function render()
    {
        return view('livewire.hotel-site.about');
    }
}
