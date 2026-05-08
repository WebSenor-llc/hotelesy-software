<?php

namespace App\Livewire\Desktop;

use App\Models\Property;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.desktop-shell')]
class FirstRunSetup extends Component
{
    public int $step = 1; // 1: hotel, 2: owner, 3: room types

    // Step 1 — Hotel basics
    #[Validate('required|string|min:2|max:160')] public string $hotelName = '';
    #[Validate('required|string|max:80')]        public string $city = '';
    #[Validate('required|string|max:80')]        public string $state = 'Rajasthan';
    #[Validate('nullable|string|max:80')]        public string $stateCode = '08';
    #[Validate('nullable|string|max:20|regex:/^([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1})?$/')]
    public string $gstin = '';
    #[Validate('nullable|string|max:20')] public string $phone = '';
    #[Validate('nullable|email|max:160')] public string $hotelEmail = '';

    // Step 2 — Owner login
    #[Validate('required|string|min:2|max:120')]        public string $ownerName = '';
    #[Validate('required|email|max:160|unique:users,email')] public string $ownerEmail = '';
    #[Validate('required|string|min:6|max:100')]        public string $password = '';
    #[Validate('required|string|same:password')]        public string $passwordConfirmation = '';

    // Step 3 — Initial room types
    public array $roomTypes = [
        ['code' => 'STD',  'name' => 'Standard',          'rate' => 2500, 'occupancy' => 2, 'count' => 5],
        ['code' => 'DLX',  'name' => 'Deluxe',            'rate' => 3500, 'occupancy' => 2, 'count' => 5],
        ['code' => 'SUITE','name' => 'Suite',             'rate' => 6500, 'occupancy' => 3, 'count' => 2],
    ];

    public bool $busy = false;
    public ?string $error = null;

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validateOnly('hotelName');
            $this->validateOnly('city');
            $this->validateOnly('state');
            $this->validateOnly('gstin');
        } elseif ($this->step === 2) {
            $this->validateOnly('ownerName');
            $this->validateOnly('ownerEmail');
            $this->validateOnly('password');
            $this->validateOnly('passwordConfirmation');
        }
        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function finish(): void
    {
        $this->busy = true;
        $this->error = null;

        try {
            $this->validate();

            DB::transaction(function () {
                $ctx = app(TenantContext::class);
                $ctx->bypass(function () {
                    // Tenant — single-tenant for desktop, but we still use the
                    // tenant model so all the BelongsToTenant magic works.
                    $tenant = Tenant::firstOrCreate(
                        ['id' => (int) config('desktop.tenant_id', 1)],
                        [
                            'name'        => $this->hotelName,
                            'slug'        => Str::slug($this->hotelName) . '-' . Str::random(4),
                            'legal_name'  => $this->hotelName,
                            'owner_email' => $this->ownerEmail,
                            'country'     => 'IN',
                            'currency'    => 'INR',
                            'timezone'    => 'Asia/Kolkata',
                            'locale'      => 'en',
                            // tenants.plan is enum(trial,starter,growth,enterprise);
                            // desktop builds map to 'enterprise' (full features,
                            // single tenant). The "this is a desktop install"
                            // marker lives in settings.build below.
                            'plan'        => 'enterprise',
                            'status'      => 'active',
                            'property_limit' => 1,
                            'room_limit'  => 500,
                            'user_limit'  => 50,
                            'features'    => ['desktop' => true, 'pos' => true, 'banquet' => true],
                            'settings'    => ['build' => 'desktop'],
                        ]
                    );

                    // Property
                    $property = Property::firstOrCreate(
                        ['id' => (int) config('desktop.property_id', 1)],
                        [
                            'tenant_id'  => $tenant->id,
                            'code'       => 'MAIN',
                            'name'       => $this->hotelName,
                            'legal_name' => $this->hotelName,
                            'city'       => $this->city,
                            'state'      => $this->state,
                            'state_code' => $this->stateCode ?: null,
                            'country'    => 'IN',
                            'phone'      => $this->phone ?: null,
                            'email'      => $this->hotelEmail ?: null,
                            'gst_number' => $this->gstin ?: null,
                            'currency'   => 'INR',
                            'timezone'   => 'Asia/Kolkata',
                            'check_in_time' => '14:00',
                            'check_out_time'=> '12:00',
                            'status'     => 'active',
                            'invoice_prefix' => strtoupper(substr(Str::slug($this->hotelName), 0, 4)) ?: 'HTL',
                        ]
                    );

                    // Owner user
                    $user = User::firstOrCreate(
                        ['email' => $this->ownerEmail],
                        [
                            'tenant_id' => $tenant->id,
                            'name'      => $this->ownerName,
                            'password'  => Hash::make($this->password),
                            'default_property_id' => $property->id,
                            'is_active' => true,
                        ]
                    );
                    $user->properties()->syncWithoutDetaching([$property->id]);

                    // Ensure the Owner / Director role exists in the fresh
                    // desktop SQLite DB. The cloud seeds roles via DemoSeeder /
                    // RolesAndPermissionsSeeder, but the desktop installer
                    // skips those — we create the bare-minimum role here and
                    // assign it. The rbac middleware grants full access to
                    // anyone with this role, so no individual permissions
                    // need to be seeded for the owner to use the app.
                    \Spatie\Permission\Models\Role::firstOrCreate([
                        'name'       => 'Owner / Director',
                        'guard_name' => 'web',
                    ]);
                    $user->syncRoles(['Owner / Director']);

                    // Room types
                    // Note: BelongsToTenant auto-fill is suppressed inside
                    // TenantContext::bypass(), so we set tenant_id manually.
                    foreach ($this->roomTypes as $rt) {
                        if (empty($rt['code']) || empty($rt['name'])) continue;
                        RoomType::firstOrCreate(
                            ['property_id' => $property->id, 'code' => $rt['code']],
                            [
                                'tenant_id'        => $tenant->id,
                                'name'             => $rt['name'],
                                'description'      => $rt['name'] . ' room',
                                'base_occupancy'   => max(1, (int) $rt['occupancy']),
                                'max_occupancy'    => max(1, (int) $rt['occupancy']) + 1,
                                'max_adults'       => max(1, (int) $rt['occupancy']),
                                'max_children'    => 2,
                                'extra_bed_capacity' => 1,
                                'base_rate'        => (float) $rt['rate'],
                                'extra_adult_rate' => 1500,
                                'extra_bed_rate'   => 1000,
                                'bed_type'         => 'king',
                                'hsn_sac_code'     => '996311',
                                'is_active'        => true,
                            ]
                        );
                    }

                    // Auto-login the owner
                    auth()->login($user, true);
                    session()->regenerate();
                });
            });

            // Done — go to dashboard
            $this->redirect(route('dashboard'), navigate: false);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->busy = false;
        }
    }

    public function render()
    {
        return view('livewire.desktop.first-run-setup');
    }
}
