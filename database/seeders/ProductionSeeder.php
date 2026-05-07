<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Production-only minimal seeder.
 * Creates ONE tenant + ONE property + ONE owner user.
 * No demo guests, reservations, POS orders, etc.
 *
 * Run with:  php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Bootstrap subscription plans + super admin first.
        $this->call(LicensingSeeder::class);

        // Tenant — single org for first deploy
        $tenant = Tenant::firstOrCreate(
            ['code' => 'WEBSENOR'],
            [
                'name' => 'WebSenor',
                'plan' => 'growth',
                'is_active' => true,
            ]
        );

        // Property — placeholder; user edits via Setup → Properties after login
        $property = Property::firstOrCreate(
            ['code' => 'PROP-001'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'My Hotel',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'country' => 'IN',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'status' => 'active',
            ]
        );

        // Owner user — change the password immediately after first login!
        User::firstOrCreate(
            ['email' => 'admin@hotelesy.app'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin',
                'password' => Hash::make('changeme'),
                'is_active' => true,
            ]
        );

        // Auto-issue an Active license (Growth plan, 365 days) for the production tenant.
        if (!$tenant->license()->exists()) {
            $plan = SubscriptionPlan::where('code', 'growth')->first()
                 ?? SubscriptionPlan::where('is_active', true)->first();
            $superAdmin = User::where('is_super_admin', true)->first();

            if ($plan) {
                $key = License::generateKey();
                License::create([
                    'tenant_id'            => $tenant->id,
                    'subscription_plan_id' => $plan->id,
                    'license_key'          => $key,
                    'license_key_hash'     => License::hashKey($key),
                    'status'               => License::STATUS_ACTIVE,
                    'starts_at'            => now(),
                    'expires_at'           => now()->addDays(365),
                    'billing_cycle'        => 'yearly',
                    'last_validated_at'    => now(),
                    'issued_by'            => $superAdmin?->id,
                    'issued_at'            => now(),
                    'notes'                => 'Auto-issued by ProductionSeeder.',
                ]);
            }
        }

        $this->command->info('Production seed complete.');
        $this->command->warn('Login: admin@hotelesy.app / changeme');
        $this->command->warn('CHANGE THIS PASSWORD IMMEDIATELY after first login.');
        $this->command->warn('Super admin login: super@hotelesy.app / SuperSecret123!');
    }
}
