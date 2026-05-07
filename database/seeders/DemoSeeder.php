<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * DemoSeeder bootstraps a runnable demo. IDEMPOTENT — safe to re-run.
 *  - 1 tenant: "Miraj Hotels Demo"
 *  - 1 property: Miraj Lake Palace, Udaipur
 *  - 4 room types (Deluxe, Premium, Suite, Presidential)
 *  - 30 rooms
 *  - Roles + permissions per spec
 *  - 1 admin user, 1 front office manager, 1 cashier
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);

        $context->bypass(function () use ($context) {
            // 1. Tenant — idempotent on slug
            $tenant = Tenant::updateOrCreate(
                ['slug' => 'miraj-demo'],
                [
                    'name' => 'Miraj Hotels Demo',
                    'legal_name' => 'Miraj Hospitality Pvt Ltd',
                    'owner_email' => 'piyush@websenor.com',
                    'country' => 'IN',
                    'currency' => 'INR',
                    'timezone' => 'Asia/Kolkata',
                    'locale' => 'en',
                    'plan' => 'growth',
                    'status' => 'trial',
                    'trial_ends_at' => now()->addDays(30),
                    'property_limit' => 5,
                    'room_limit' => 500,
                    'user_limit' => 50,
                    'features' => [
                        'channel_manager' => true,
                        'booking_engine' => true,
                        'pos' => true,
                        'banquet' => true,
                    ],
                    'settings' => [],
                ]
            );

            // Make sure subsequent tenant-scoped creates resolve correctly.
            $context->set($tenant);

            // 2. Roles + permissions (the 14 roles from the spec) — always re-sync
            $this->seedRolesAndPermissions();

            // 3. Property — idempotent on (tenant_id, code)
            $property = Property::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'MLPU'],
                [
                    'name'            => 'Miraj Lake Palace, Udaipur',
                    'legal_name'      => 'Miraj Hospitality Pvt Ltd',
                    'address'         => '12 Pichola Marg, Udaipur',
                    'city'            => 'Udaipur',
                    'state'           => 'Rajasthan',
                    'country'         => 'IN',
                    'postal_code'     => '313001',
                    'gst_number'      => '08AABCM1234X1Z5',
                    'phone'           => '+91 294 123 4567',
                    'email'           => 'reservations@mirajlakepalace.com',
                    'check_in_time'   => '14:00',
                    'check_out_time'  => '12:00',
                    'currency'        => 'INR',
                    'timezone'        => 'Asia/Kolkata',
                    'status'          => 'active',
                ]
            );

            $context->setProperty($property);

            // 4. Tax setup (GST) — idempotent on (property_id, code)
            Tax::updateOrCreate(
                ['property_id' => $property->id, 'code' => 'GST_12'],
                [
                    'name'        => 'GST 12% (room <= 7500)',
                    'type'        => 'gst',
                    'rate'        => 12,
                    'threshold_max' => 7500,
                    'applies_to_room' => true,
                    'is_active'   => true,
                ]
            );
            Tax::updateOrCreate(
                ['property_id' => $property->id, 'code' => 'GST_18'],
                [
                    'name'        => 'GST 18% (room > 7500)',
                    'type'        => 'gst',
                    'rate'        => 18,
                    'threshold_min' => 7500.01,
                    'applies_to_room' => true,
                    'is_active'   => true,
                ]
            );

            // 5. Room types — idempotent on (property_id, code)
            $deluxe = RoomType::updateOrCreate(
                ['property_id' => $property->id, 'code' => 'DLX'],
                [
                    'name'        => 'Deluxe Lake View',
                    'description' => 'Lake-facing room with king bed, balcony, traditional Rajasthani decor.',
                    'base_occupancy' => 2, 'max_occupancy' => 3, 'max_adults' => 2, 'max_children' => 2,
                    'extra_bed_capacity' => 1,
                    'base_rate'        => 6500,
                    'extra_adult_rate' => 1500,
                    'extra_bed_rate'   => 1000,
                    'bed_type'         => 'king',
                    'is_active'        => true,
                ]
            );
            $premium = RoomType::updateOrCreate(
                ['property_id' => $property->id, 'code' => 'PRM'],
                [
                    'name'        => 'Premium Suite',
                    'description' => 'Spacious suite with separate living area and lake view.',
                    'base_occupancy' => 2, 'max_occupancy' => 4, 'max_adults' => 3, 'max_children' => 2,
                    'extra_bed_capacity' => 2,
                    'base_rate'        => 11500,
                    'extra_adult_rate' => 2000,
                    'extra_bed_rate'   => 1500,
                    'bed_type'         => 'king',
                    'is_active'        => true,
                ]
            );
            $suite = RoomType::updateOrCreate(
                ['property_id' => $property->id, 'code' => 'STE'],
                [
                    'name'        => 'Heritage Suite',
                    'description' => 'Top-floor heritage suite with private terrace.',
                    'base_occupancy' => 2, 'max_occupancy' => 4, 'max_adults' => 3, 'max_children' => 2,
                    'extra_bed_capacity' => 2,
                    'base_rate'        => 18000,
                    'extra_adult_rate' => 2500,
                    'extra_bed_rate'   => 1500,
                    'bed_type'         => 'king',
                    'is_active'        => true,
                ]
            );
            $presidential = RoomType::updateOrCreate(
                ['property_id' => $property->id, 'code' => 'PRS'],
                [
                    'name'        => 'Presidential Suite',
                    'description' => 'Two-bedroom presidential suite with butler service.',
                    'base_occupancy' => 2, 'max_occupancy' => 6, 'max_adults' => 4, 'max_children' => 2,
                    'extra_bed_capacity' => 2,
                    'base_rate'        => 35000,
                    'extra_adult_rate' => 3500,
                    'extra_bed_rate'   => 2000,
                    'bed_type'         => 'king',
                    'is_active'        => true,
                ]
            );

            // 6. Physical rooms — only seed if missing
            $this->seedRooms($property, $deluxe, ['floor' => 1, 'count' => 8, 'start' => 101]);
            $this->seedRooms($property, $deluxe, ['floor' => 2, 'count' => 8, 'start' => 201]);
            $this->seedRooms($property, $premium, ['floor' => 3, 'count' => 8, 'start' => 301]);
            $this->seedRooms($property, $suite, ['floor' => 4, 'count' => 4, 'start' => 401]);
            $this->seedRooms($property, $presidential, ['floor' => 5, 'count' => 2, 'start' => 501]);

            // 7. Rate plans (one BAR per room type) — idempotent
            foreach ([$deluxe, $premium, $suite, $presidential] as $rt) {
                RatePlan::updateOrCreate(
                    ['property_id' => $property->id, 'code' => 'BAR-'.$rt->code],
                    [
                        'room_type_id' => $rt->id,
                        'name'         => 'Best Available Rate ('.$rt->name.')',
                        'meal_plan'    => 'CP',
                        'pricing_mode' => 'fixed',
                        'base_rate'    => $rt->base_rate,
                        'is_active'    => true,
                    ]
                );
            }

            // 8. Users — idempotent on email; ALWAYS re-sync roles so RBAC is correct
            $admin = User::updateOrCreate(
                ['email' => 'admin@miraj-demo.test'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Piyush Owner',
                    'password' => Hash::make('password'),
                    'default_property_id' => $property->id,
                    'is_active' => true,
                ]
            );
            $admin->syncRoles(['Owner / Director']);

            $fom = User::updateOrCreate(
                ['email' => 'fom@miraj-demo.test'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'FO Manager',
                    'password' => Hash::make('password'),
                    'default_property_id' => $property->id,
                    'is_active' => true,
                ]
            );
            $fom->syncRoles(['Front Office Manager']);

            $cashier = User::updateOrCreate(
                ['email' => 'cashier@miraj-demo.test'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Cashier 1',
                    'password' => Hash::make('password'),
                    'default_property_id' => $property->id,
                    'is_active' => true,
                ]
            );
            $cashier->syncRoles(['Cashier']);

            // Attach all users to the demo property so PropertyController can list it
            foreach ([$admin, $fom, $cashier] as $u) {
                $u->properties()->syncWithoutDetaching([$property->id]);
            }

            // Bust the spatie permission cache so newly synced roles are visible immediately
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            $this->command->info('');
            $this->command->info('===== Demo seeded (idempotent) =====');
            $this->command->info("Tenant slug: miraj-demo");
            $this->command->info("Login URL:   http://localhost:8000/");
            $this->command->info("Admin:       admin@miraj-demo.test / password   (Owner / Director — full access)");
            $this->command->info("FO Manager:  fom@miraj-demo.test   / password   (Front Office Manager — limited)");
            $this->command->info("Cashier:     cashier@miraj-demo.test / password (Cashier — front office only)");
            $this->command->info('');
        });
    }

    private function seedRooms(Property $property, RoomType $type, array $config): void
    {
        for ($i = 0; $i < $config['count']; $i++) {
            Room::updateOrCreate(
                [
                    'property_id' => $property->id,
                    'number'      => (string) ($config['start'] + $i),
                ],
                [
                    'room_type_id' => $type->id,
                    'floor' => $config['floor'],
                    'wing' => 'Main',
                    'view' => 'Lake',
                    'status' => 'vacant_clean',
                    'fo_status' => 'vacant',
                    'is_smoking' => false,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedRolesAndPermissions(): void
    {
        $modules = [
            'reservations', 'frontoffice', 'pos', 'housekeeping', 'banquet',
            'amenities', 'store', 'crm', 'staff', 'accounts', 'channel',
            'revenue', 'reports', 'setup',
        ];
        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'export'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        $rolesMatrix = [
            'Owner / Director' => '*',
            'General Manager' => '*',
            'Front Office Manager' => ['reservations.*', 'frontoffice.*', 'housekeeping.view', 'reports.view'],
            'Reservation Agent' => ['reservations.view', 'reservations.create', 'reservations.edit'],
            'Cashier' => ['frontoffice.view', 'frontoffice.edit', 'reservations.view'],
            'Housekeeping Manager' => ['housekeeping.*', 'reservations.view'],
            'F&B Manager' => ['pos.*', 'reports.view'],
            'Banquet Manager' => ['banquet.*'],
            'Store Manager' => ['store.*'],
            'Accounts Manager' => ['accounts.*', 'reports.*'],
            'Revenue Manager' => ['revenue.*', 'channel.view', 'reports.view'],
            'CRM / Marketing' => ['crm.*'],
            'HR / Staff Admin' => ['staff.*'],
            'IT Admin' => ['setup.*', 'channel.*'],
        ];

        foreach ($rolesMatrix as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($perms === '*') {
                $role->syncPermissions(Permission::all());
                continue;
            }

            $perms = collect($perms)->flatMap(function ($p) use ($modules, $actions) {
                if (str_ends_with($p, '.*')) {
                    $module = substr($p, 0, -2);
                    return collect($actions)->map(fn($a) => "{$module}.{$a}");
                }
                return [$p];
            })->unique()->all();

            $role->syncPermissions($perms);
        }
    }
}
