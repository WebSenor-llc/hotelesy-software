<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * LicensingSeeder — bootstraps the SaaS licensing layer:
 *  - 4 subscription plans (Trial, Starter, Growth, Enterprise)
 *  - 1 super-admin user (super@hotelesy.app / SuperSecret123!)
 *
 * Idempotent: re-running updates plans in place. Super admin is created if missing.
 */
class LicensingSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'trial',
                'name' => 'Trial',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'billing_currency' => 'INR',
                'max_properties' => 1,
                'max_rooms' => 30,
                'max_users' => 3,
                'features' => [
                    'channel_manager' => false,
                    'pos' => true,
                    'banquet' => false,
                    'compliance' => true,
                    'revenue' => false,
                    'reviews' => true,
                ],
                'trial_days' => 14,
                'is_active' => true,
            ],
            [
                'code' => 'starter',
                'name' => 'Starter',
                'price_monthly' => 4999,
                'price_yearly' => 49990,
                'billing_currency' => 'INR',
                'max_properties' => 1,
                'max_rooms' => 50,
                'max_users' => 5,
                'features' => [
                    'channel_manager' => false,
                    'pos' => true,
                    'banquet' => false,
                    'compliance' => true,
                    'revenue' => false,
                    'reviews' => true,
                ],
                'trial_days' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'growth',
                'name' => 'Growth',
                'price_monthly' => 9999,
                'price_yearly' => 99990,
                'billing_currency' => 'INR',
                'max_properties' => 3,
                'max_rooms' => 200,
                'max_users' => 25,
                'features' => [
                    'channel_manager' => true,
                    'pos' => true,
                    'banquet' => true,
                    'compliance' => true,
                    'revenue' => true,
                    'reviews' => true,
                ],
                'trial_days' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_monthly' => 24999,
                'price_yearly' => 249990,
                'billing_currency' => 'INR',
                'max_properties' => 10,
                'max_rooms' => 1000,
                'max_users' => 200,
                'features' => [
                    'channel_manager' => true,
                    'pos' => true,
                    'banquet' => true,
                    'compliance' => true,
                    'revenue' => true,
                    'reviews' => true,
                    'multi_property' => true,
                    'priority_support' => true,
                    'custom_integrations' => true,
                ],
                'trial_days' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }

        // Super admin operator
        User::updateOrCreate(
            ['email' => 'super@hotelesy.app'],
            [
                'tenant_id' => null,
                'name' => 'Super Admin',
                'password' => Hash::make('SuperSecret123!'),
                'is_super_admin' => true,
                'is_active' => true,
            ]
        );

        $this->command?->info('Licensing: 4 plans + super admin (super@hotelesy.app / SuperSecret123!) seeded.');
    }
}
