<?php

namespace App\Livewire\Marketing;

use App\Models\License;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RazorpayService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.marketing')]
class Checkout extends Component
{
    public ?int $planId = null;
    public string $cycle = 'monthly';

    #[Validate('required|string|min:2|max:120')]   public string $name = '';
    #[Validate('required|email|max:160|unique:users,email')] public string $email = '';
    #[Validate('required|string|min:8|max:20')]    public string $phone = '';
    #[Validate('required|string|min:2|max:120')]   public string $hotel_name = '';
    #[Validate('required|string|min:2|max:80')]    public string $city = '';
    #[Validate('required|integer|min:1|max:5000')] public int $rooms_count = 0;
    #[Validate('required|string|min:6|max:100')]   public string $password = '';
    #[Validate('accepted')] public bool $agree = false;

    public ?array $razorpayOrder = null;
    public ?int $tenantId = null;
    public ?int $licenseId = null;
    public ?int $transactionId = null;
    public string $step = 'form';
    public ?SubscriptionPlan $plan = null;
    public float $amount = 0;

    public function mount(?int $plan = null, ?string $cycle = 'monthly'): void
    {
        $this->planId = $plan;
        $this->cycle = in_array($cycle, ['monthly','yearly'], true) ? $cycle : 'monthly';

        $this->plan = app(TenantContext::class)->bypass(
            fn () => SubscriptionPlan::where('is_active', true)
                ->when($this->planId, fn($q) => $q->where('id', $this->planId))
                ->orderBy('price_monthly')
                ->first()
        );

        if (! $this->plan) {
            session()->flash('warning', 'Plan not found.');
            redirect()->route('home');
            return;
        }
        $this->amount = $this->cycle === 'yearly'
            ? (float) ($this->plan->price_yearly ?: $this->plan->price_monthly * 12 * 0.83)
            : (float) $this->plan->price_monthly;
    }

    public function startCheckout(RazorpayService $razorpay): void
    {
        $data = $this->validate();
        $context = app(TenantContext::class);

        $context->bypass(function () use (&$razorpay, $data) {
            DB::transaction(function () use (&$razorpay, $data) {
                $slug = Str::slug($data['hotel_name'] . '-' . Str::random(4));
                $tenant = Tenant::create([
                    'name'        => $data['hotel_name'],
                    'slug'        => $slug,
                    'legal_name'  => $data['hotel_name'],
                    'owner_email' => $data['email'],
                    'country'     => 'IN',
                    'currency'    => 'INR',
                    'timezone'    => 'Asia/Kolkata',
                    'locale'      => 'en',
                    'plan'        => strtolower($this->plan->code ?? 'growth'),
                    'status'      => 'active',
                    'property_limit' => $this->plan->max_properties ?: 5,
                    'room_limit'  => $this->plan->max_rooms ?: 500,
                    'user_limit'  => $this->plan->max_users ?: 50,
                    'features'    => $this->plan->features ?? [],
                    'settings'    => ['source' => 'paid_checkout'],
                ]);

                $property = Property::create([
                    'tenant_id'  => $tenant->id,
                    'code'       => 'MAIN',
                    'name'       => $data['hotel_name'],
                    'city'       => $data['city'],
                    'country'    => 'IN',
                    'currency'   => 'INR',
                    'timezone'   => 'Asia/Kolkata',
                    'check_in_time'  => '14:00',
                    'check_out_time' => '12:00',
                    'status'     => 'active',
                ]);

                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name'      => $data['name'],
                    'email'     => $data['email'],
                    'phone'     => $data['phone'],
                    'password'  => Hash::make($data['password']),
                    'default_property_id' => $property->id,
                    'is_active' => true,
                ]);
                $user->properties()->syncWithoutDetaching([$property->id]);
                if (\Spatie\Permission\Models\Role::where('name', 'Owner / Director')->exists()) {
                    $user->syncRoles(['Owner / Director']);
                }

                $duration = $this->cycle === 'yearly' ? 365 : 30;
                $key = License::generateKey();
                $license = License::create([
                    'tenant_id'            => $tenant->id,
                    'subscription_plan_id' => $this->plan->id,
                    'license_key'          => $key,
                    'license_key_hash'     => License::hashKey($key),
                    'status'               => License::STATUS_ACTIVE,
                    'starts_at'            => now(),
                    'expires_at'           => now()->addDays($duration),
                    'billing_cycle'        => $this->cycle,
                    'auto_renew'           => false,
                    'next_billing_at'      => now()->addDays($duration),
                    'last_validated_at'    => now(),
                ]);

                $invoiceNo = SubscriptionTransaction::generateInvoiceNumber();
                $order = $razorpay->createOrder((int) ($this->amount * 100), $invoiceNo, [
                    'tenant_id' => $tenant->id,
                    'license_id' => $license->id,
                    'plan' => $this->plan->code,
                    'cycle' => $this->cycle,
                ]);

                $tx = SubscriptionTransaction::create([
                    'tenant_id' => $tenant->id,
                    'license_id' => $license->id,
                    'subscription_plan_id' => $this->plan->id,
                    'type' => SubscriptionTransaction::TYPE_ONE_TIME,
                    'status' => SubscriptionTransaction::STATUS_PENDING,
                    'billing_cycle' => $this->cycle,
                    'amount' => $this->amount,
                    'currency' => 'INR',
                    'razorpay_order_id' => $order['id'],
                    'invoice_number' => $invoiceNo,
                    'description' => "Hotelesy — {$this->plan->name} ({$this->cycle})",
                    'razorpay_payload' => $order,
                ]);

                $this->razorpayOrder = $order;
                $this->tenantId = $tenant->id;
                $this->licenseId = $license->id;
                $this->transactionId = $tx->id;
            });
        });

        $this->step = 'pay';
    }

    public function confirmPayment(string $razorpayPaymentId, string $razorpayOrderId, string $razorpaySignature): void
    {
        $razorpay = app(RazorpayService::class);
        if (! $razorpay->verifyPaymentSignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
            session()->flash('warning', 'Payment verification failed.');
            return;
        }

        app(TenantContext::class)->bypass(function () use ($razorpayPaymentId, $razorpayOrderId) {
            $tx = SubscriptionTransaction::where('razorpay_order_id', $razorpayOrderId)->first();
            if (! $tx) return;
            $tx->update([
                'status' => SubscriptionTransaction::STATUS_CAPTURED,
                'razorpay_payment_id' => $razorpayPaymentId,
                'paid_at' => now(),
            ]);
            $license = License::find($tx->license_id);
            if ($license) {
                $license->update(['status' => License::STATUS_ACTIVE]);
            }
        });

        $this->step = 'success';
    }

    public function render()
    {
        return view('livewire.marketing.checkout');
    }
}
