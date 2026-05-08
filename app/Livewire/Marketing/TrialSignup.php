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
class TrialSignup extends Component
{
    #[Validate('required|string|min:2|max:120')]   public string $name = '';
    #[Validate('required|email|max:160|unique:users,email')] public string $email = '';
    #[Validate('required|string|min:8|max:20')]    public string $phone = '';
    #[Validate('required|string|min:2|max:120')]   public string $hotel_name = '';
    #[Validate('required|string|min:2|max:80')]    public string $city = '';
    #[Validate('required|integer|min:1|max:5000')] public int $rooms_count = 0;
    #[Validate('required|string|min:6|max:100')]   public string $password = '';
    #[Validate('accepted')]                         public bool $agree = false;

    public ?array $razorpayOrder = null;
    public ?int $tenantId = null;
    public ?int $licenseId = null;
    public ?int $transactionId = null;
    public string $step = 'form'; // form | pay | success

    // Snapshot of the activated trial — used to render the success page.
    public ?string $successTenantSlug = null;
    public ?string $successLicenseKey = null;
    public ?string $successTrialEnds  = null;
    public ?string $successHotelName  = null;
    public ?string $successOwnerName  = null;
    public ?string $successOwnerEmail = null;
    public ?string $successPropertyId = null;
    public ?string $successCity       = null;

    public function mount(): void
    {
        // If logged in, send to dashboard
        if (auth()->check()) {
            redirect()->route('dashboard');
        }
    }

    /**
     * Step 1 → submit form → create tenant/license/order, then push to step 'pay'.
     */
    public function startTrial(RazorpayService $razorpay): void
    {
        $data = $this->validate();
        $context = app(TenantContext::class);

        $context->bypass(function () use (&$razorpay, $data) {
            DB::transaction(function () use (&$razorpay, $data) {
                $slug = Str::slug($data['hotel_name'] . '-' . Str::random(4));

                // Create the tenant
                $tenant = Tenant::create([
                    'name'        => $data['hotel_name'],
                    'slug'        => $slug,
                    'legal_name'  => $data['hotel_name'],
                    'owner_email' => $data['email'],
                    'country'     => 'IN',
                    'currency'    => 'INR',
                    'timezone'    => 'Asia/Kolkata',
                    'locale'      => 'en',
                    'plan'        => 'trial',
                    'status'      => 'trial',
                    'trial_ends_at' => now()->addDays(config('razorpay.trial_days', 30)),
                    'property_limit' => 1,
                    'room_limit'  => 100,
                    'user_limit'  => 10,
                    'features'    => ['channel_manager' => true, 'pos' => true, 'banquet' => false],
                    'settings'    => ['source' => 'trial_signup_landing'],
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

                // Find or create the trial plan
                $plan = SubscriptionPlan::where('code', 'trial')->orWhere('trial_days', '>', 0)->first()
                    ?: SubscriptionPlan::firstOrCreate(
                        ['code' => 'trial'],
                        [
                            'name' => 'Free Trial',
                            'price_monthly' => 0,
                            'price_yearly'  => 0,
                            'billing_currency' => 'INR',
                            'max_properties' => 1,
                            'max_rooms' => 100,
                            'max_users' => 10,
                            'trial_days' => config('razorpay.trial_days', 30),
                            'features'  => ['pms' => true, 'channel_manager' => true, 'pos' => true],
                            'is_active' => true,
                        ]
                    );

                // License — 30-day trial
                $key = License::generateKey();
                $license = License::create([
                    'tenant_id' => $tenant->id,
                    'subscription_plan_id' => $plan->id,
                    'license_key' => $key,
                    'license_key_hash' => License::hashKey($key),
                    'status' => License::STATUS_TRIAL,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays(config('razorpay.trial_days', 30)),
                    'billing_cycle' => 'trial',
                    'auto_renew' => true,
                    'next_billing_at' => now()->addDays(config('razorpay.trial_days', 30)),
                    'last_validated_at' => now(),
                    'mandate_status' => 'pending',
                    'issued_at' => now(),
                    'issued_by' => null,            // self-serve signup; no admin involved
                ]);

                // Razorpay mandate-auth order (₹1)
                $invoiceNo = SubscriptionTransaction::generateInvoiceNumber();
                $order = $razorpay->createMandateOrder($invoiceNo, [
                    'tenant_id' => $tenant->id,
                    'license_id' => $license->id,
                    'kind' => 'trial_mandate',
                ]);

                $tx = SubscriptionTransaction::create([
                    'tenant_id' => $tenant->id,
                    'license_id' => $license->id,
                    'subscription_plan_id' => $plan->id,
                    'type' => SubscriptionTransaction::TYPE_MANDATE,
                    'status' => SubscriptionTransaction::STATUS_PENDING,
                    'billing_cycle' => 'trial',
                    'amount' => (int) $order['amount'] / 100,
                    'currency' => $order['currency'] ?? 'INR',
                    'razorpay_order_id' => $order['id'],
                    'is_mandate' => true,
                    'invoice_number' => $invoiceNo,
                    'description' => '₹1 UPI mandate verification for 30-day Hotelesy trial',
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

    /**
     * Called from JS after Razorpay checkout completes successfully.
     * On success: auto-login the new user and redirect to the dashboard.
     */
    public function confirmPayment(string $razorpayPaymentId, string $razorpayOrderId, string $razorpaySignature): void
    {
        $razorpay = app(RazorpayService::class);
        if (! $razorpay->verifyPaymentSignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
            session()->flash('warning', 'Payment verification failed. Please contact support.');
            return;
        }
        $this->finalisePaymentSuccess($razorpayOrderId, $razorpayPaymentId);
    }

    /**
     * TEST helper — simulate a successful payment (used by the green "Test
     * success" button when running in stub mode without real Razorpay keys).
     */
    public function testPaymentSuccess(): void
    {
        if (! ($this->razorpayOrder['id'] ?? null)) {
            session()->flash('warning', 'No order to confirm.');
            return;
        }
        $this->finalisePaymentSuccess(
            $this->razorpayOrder['id'],
            'pay_test_' . substr(md5(uniqid()), 0, 14)
        );
    }

    /**
     * TEST helper — simulate a failed payment.
     */
    public function testPaymentFailure(): void
    {
        if (! $this->transactionId) return;
        app(TenantContext::class)->bypass(function () {
            $tx = SubscriptionTransaction::find($this->transactionId);
            if ($tx) {
                $tx->update([
                    'status' => SubscriptionTransaction::STATUS_FAILED,
                    'failure_reason' => 'Test failure (stub mode)',
                ]);
            }
        });
        session()->flash('warning', 'Payment failed. Try again or pick a different payment method.');
    }

    /**
     * Centralised "payment captured" handler. Called from real Razorpay
     * callback AND from the test-success button.
     *  - Marks the transaction captured
     *  - Activates the mandate on the license
     *  - Auto-logs-in the newly-created tenant owner
     *  - Snapshots all key info into public properties for the thank-you page
     *  - Stays on step='success' so the user sees the rich thank-you page
     *    (they then click "Continue to setup" to enter the dashboard)
     */
    private function finalisePaymentSuccess(string $orderId, string $paymentId): void
    {
        app(TenantContext::class)->bypass(function () use ($orderId, $paymentId) {
            $tx = SubscriptionTransaction::where('razorpay_order_id', $orderId)->first();
            if (! $tx) return;
            $tx->update([
                'status' => SubscriptionTransaction::STATUS_CAPTURED,
                'razorpay_payment_id' => $paymentId,
                'paid_at' => now(),
                'payment_method' => 'upi',
            ]);
            $license = License::find($tx->license_id);
            if ($license) {
                $license->update(['mandate_status' => 'active']);
            }
        });

        // Snapshot tenant + license + property for the thank-you page
        if ($this->tenantId) {
            app(TenantContext::class)->bypass(function () {
                $tenant   = \App\Models\Tenant::find($this->tenantId);
                $license  = $this->licenseId ? License::find($this->licenseId) : null;
                $property = $tenant ? \App\Models\Property::where('tenant_id', $tenant->id)->orderBy('id')->first() : null;
                $owner    = $tenant ? \App\Models\User::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('id')->first() : null;

                $this->successTenantSlug  = $tenant?->slug;
                $this->successHotelName   = $tenant?->name;
                $this->successLicenseKey  = $license?->license_key;
                $this->successTrialEnds   = $license?->expires_at?->format('d M Y');
                $this->successOwnerName   = $owner?->name;
                $this->successOwnerEmail  = $owner?->email;
                $this->successPropertyId  = (string) ($property?->id ?? '');
                $this->successCity        = $property?->city;

                // Auto-login the owner so the "Continue to setup" button takes
                // them straight to the dashboard with no login screen.
                if ($owner) {
                    auth()->login($owner, true);
                    session()->regenerate();
                    // Persist tenant+property in the session immediately so the
                    // dashboard's tenant middleware can resolve them.
                    if ($property) {
                        $owner->update(['default_property_id' => $property->id]);
                    }
                }
            });
        }

        $this->step = 'success';
    }

    public function render()
    {
        return view('livewire.marketing.trial-signup');
    }
}
