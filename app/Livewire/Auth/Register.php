<?php

namespace App\Livewire\Auth;

use App\Models\License;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Public registration — creates Tenant + Property placeholder + Owner User
 * + auto-issues a 14-day Trial license. Logs the user in, redirects to dashboard.
 */
class Register extends Component
{
    public string $company_name = '';
    public string $owner_name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $phone = '';
    public string $country = 'IN';

    protected function rules(): array
    {
        return [
            'company_name' => 'required|string|max:120',
            'owner_name'   => 'required|string|max:120',
            'email'        => 'required|email|max:160|unique:users,email',
            'password'     => 'required|string|min:8|confirmed',
            'phone'        => 'nullable|string|max:30',
            'country'      => 'required|string|size:2',
        ];
    }

    public function register()
    {
        $data = $this->validate();

        $context = app(TenantContext::class);

        $user = $context->bypass(function () use ($data) {
            return DB::transaction(function () use ($data) {
                $slug = Str::slug($data['company_name']) . '-' . Str::lower(Str::random(5));

                // 1. Tenant
                $tenant = Tenant::create([
                    'name'           => $data['company_name'],
                    'slug'           => $slug,
                    'legal_name'     => $data['company_name'],
                    'owner_email'    => $data['email'],
                    'owner_phone'    => $data['phone'] ?: null,
                    'country'        => strtoupper($data['country']),
                    'currency'       => 'INR',
                    'timezone'       => 'Asia/Kolkata',
                    'locale'         => 'en',
                    'plan'           => 'trial',
                    'status'         => 'trial',
                    'trial_ends_at'  => now()->addDays(14),
                    'property_limit' => 1,
                    'room_limit'     => 30,
                    'user_limit'     => 3,
                    'features'       => [
                        'channel_manager' => false,
                        'pos'             => true,
                        'banquet'         => false,
                    ],
                    'settings'       => [],
                ]);

                app(TenantContext::class)->set($tenant);

                // 2. Property placeholder
                $property = Property::create([
                    'tenant_id'      => $tenant->id,
                    'code'           => 'MAIN-' . strtoupper(Str::random(4)),
                    'name'           => $data['company_name'] . ' (Main Property)',
                    'legal_name'     => $data['company_name'],
                    'address'        => '',
                    'city'           => '',
                    'state'          => '',
                    'country'        => strtoupper($data['country']),
                    'postal_code'    => '',
                    'phone'          => $data['phone'] ?: null,
                    'email'          => $data['email'],
                    'check_in_time'  => '14:00',
                    'check_out_time' => '12:00',
                    'currency'       => 'INR',
                    'timezone'       => 'Asia/Kolkata',
                    'status'         => 'setup',
                ]);

                // 3. Owner user
                $user = User::create([
                    'tenant_id'           => $tenant->id,
                    'name'                => $data['owner_name'],
                    'email'               => $data['email'],
                    'phone'               => $data['phone'] ?: null,
                    'password'            => Hash::make($data['password']),
                    'default_property_id' => $property->id,
                    'is_active'           => true,
                ]);

                // Best-effort role assignment — only if seeded.
                if (Role::where('name', 'Owner / Director')->where('guard_name', 'web')->exists()) {
                    try { $user->assignRole('Owner / Director'); } catch (\Throwable $e) {}
                }

                $user->properties()->syncWithoutDetaching([$property->id]);

                // 4. Trial License
                $plan = SubscriptionPlan::where('code', 'trial')->first()
                     ?? SubscriptionPlan::where('is_active', true)->orderBy('id')->first();

                if ($plan) {
                    $key = License::generateKey();
                    License::create([
                        'tenant_id'            => $tenant->id,
                        'subscription_plan_id' => $plan->id,
                        'license_key'          => $key,
                        'license_key_hash'     => License::hashKey($key),
                        'status'               => License::STATUS_TRIAL,
                        'starts_at'            => now(),
                        'expires_at'           => now()->addDays(14),
                        'billing_cycle'        => 'monthly',
                        'last_validated_at'    => now(),
                        'issued_at'            => now(),
                        'notes'                => 'Auto-issued via public signup.',
                    ]);
                }

                return $user;
            });
        });

        Auth::login($user);
        request()->session()->regenerate();

        session()->flash('success', 'Welcome to Hotelesy! Your 14-day free trial has started.');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register')->layout('layouts.guest');
    }
}
