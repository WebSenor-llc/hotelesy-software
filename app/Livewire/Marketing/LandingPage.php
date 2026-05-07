<?php

namespace App\Livewire\Marketing;

use App\Models\MarketingLead;
use App\Models\SubscriptionPlan;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.marketing')]
class LandingPage extends Component
{
    // Inquiry form state
    #[Validate('required|string|min:2|max:120')] public string $name = '';
    #[Validate('required|email|max:160')]        public string $email = '';
    #[Validate('required|string|min:8|max:20')]  public string $phone = '';
    #[Validate('nullable|string|max:160')]       public ?string $hotel_name = '';
    #[Validate('nullable|string|max:80')]        public ?string $city = '';
    #[Validate('nullable|integer|min:1|max:5000')] public ?int $rooms_count = null;
    #[Validate('nullable|string|max:80')]        public ?string $current_pms = '';
    #[Validate('nullable|string|max:1000')]      public ?string $message = '';

    public string $billingToggle = 'monthly'; // monthly | yearly
    public bool $submitted = false;
    public ?int $leadId = null;

    public function submit(): void
    {
        $data = $this->validate();

        $lead = app(TenantContext::class)->bypass(function () use ($data) {
            return MarketingLead::create(array_merge($data, [
                'source'      => 'landing_page',
                'status'      => 'new',
                'ip_address'  => request()->ip(),
                'user_agent'  => substr((string) request()->userAgent(), 0, 480),
                'referrer'    => substr((string) request()->headers->get('referer'), 0, 480),
                'utm_source'  => request()->query('utm_source'),
                'utm_medium'  => request()->query('utm_medium'),
                'utm_campaign'=> request()->query('utm_campaign'),
            ]));
        });

        $this->leadId = $lead->id;
        $this->submitted = true;
        $this->reset(['name','email','phone','hotel_name','city','rooms_count','current_pms','message']);
    }

    public function setBilling(string $cycle): void
    {
        if (in_array($cycle, ['monthly','yearly'], true)) {
            $this->billingToggle = $cycle;
        }
    }

    public function render()
    {
        $plans = app(TenantContext::class)->bypass(
            fn () => SubscriptionPlan::where('is_active', true)
                ->orderBy('price_monthly')
                ->get()
        );

        return view('livewire.marketing.landing-page', [
            'plans' => $plans,
        ]);
    }
}
