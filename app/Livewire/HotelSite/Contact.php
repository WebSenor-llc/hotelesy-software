<?php

namespace App\Livewire\HotelSite;

use App\Models\MarketingLead;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

#[Layout('layouts.hotel-site')]
class Contact extends HotelSiteBase
{
    #[Validate('required|string|min:2|max:120')] public string $name = '';
    #[Validate('required|email|max:160')]        public string $email = '';
    #[Validate('required|string|min:8|max:25')]  public string $phone = '';
    #[Validate('nullable|string|max:1000')]      public ?string $message = '';
    public bool $submitted = false;

    public function submit(): void
    {
        $this->validate();
        app(TenantContext::class)->bypass(function () {
            MarketingLead::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'phone'    => $this->phone,
                'hotel_name' => $this->tenant->name . ' (guest enquiry)',
                'message'  => $this->message,
                'source'   => 'landing_page',
                'status'   => 'new',
                'utm_source' => 'hotel_site',
                'utm_campaign' => $this->tenant->slug,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 480),
            ]);
        });
        $this->submitted = true;
        $this->reset(['name','email','phone','message']);
    }

    public function render()
    {
        return view('livewire.hotel-site.contact');
    }
}
