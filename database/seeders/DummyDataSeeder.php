<?php

namespace Database\Seeders;

use App\Models\Amenities\Amenity;
use App\Models\Amenities\AmenityOrder;
use App\Models\Banquet\BanquetBooking;
use App\Models\Banquet\BanquetHall;
use App\Models\ChannelMapping;
use App\Models\ChannelSyncLog;
use App\Models\Company;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Guest;
use App\Models\KDS\KdsStation;
use App\Models\KDS\KdsTicket;
use App\Models\POS\MenuCategory;
use App\Models\POS\MenuItem;
use App\Models\POS\Order as PosOrder;
use App\Models\POS\Outlet;
use App\Models\POS\PosTable;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewSource;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Store\PurchaseOrder;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreItem;
use App\Models\Store\StoreVendor;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Populates a Hotelogix-style demo dataset on top of DemoSeeder.
 * Idempotent: if data already exists for the demo property, it skips.
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);
        $context->bypass(function () use ($context) {
            $tenant = Tenant::where('slug', 'miraj-demo')->first();
            if (!$tenant) { $this->command->warn('Demo tenant missing — run DemoSeeder first.'); return; }
            $property = $tenant->properties()->first();
            if (!$property) { $this->command->warn('Demo property missing.'); return; }
            $context->set($tenant, $property);

            // Skip if we've already loaded dummy data
            if (Reservation::where('property_id', $property->id)->count() >= 50) {
                $this->command->info('Dummy data already present. Skipping.');
                return;
            }

            $this->command->info('Seeding companies…');
            $companies = $this->seedCompanies($tenant);

            $this->command->info('Seeding guests…');
            $guests = $this->seedGuests($tenant);

            $this->command->info('Seeding reservations + folios…');
            $this->seedReservations($tenant, $property, $guests, $companies);

            $this->command->info('Seeding POS outlets, menu, KDS…');
            $this->seedPos($tenant, $property);

            $this->command->info('Seeding amenities…');
            $this->seedAmenities($tenant, $property);

            $this->command->info('Seeding banquet halls + bookings…');
            $this->seedBanquet($tenant, $property, $companies);

            $this->command->info('Seeding store / inventory…');
            $this->seedStore($tenant, $property);

            $this->command->info('Seeding reviews…');
            $this->seedReviews($tenant, $property);

            $this->command->info('Seeding channel mappings + sync log…');
            $this->seedChannel($tenant, $property);

            $this->command->info('Seeding accounts (chart + voucher types)…');
            $this->seedAccounts($tenant, $property);

            $this->command->info('Seeding revenue management data…');
            $this->seedRevenue($tenant, $property);

            $this->command->info('Seeding integration logs…');
            $this->seedIntegrationLogs($tenant, $property);

            $this->command->info('Seeding promotions / coupons…');
            $this->seedPromotions($tenant, $property);

            $this->command->info('Issuing demo SaaS license…');
            $this->issueDemoLicense($tenant);

            $this->command->info('Done.');
        });
    }

    /**
     * Issue a 365-day Growth-plan active license for the demo tenant.
     * Idempotent — runs only when no license exists yet.
     */
    private function issueDemoLicense(Tenant $tenant): void
    {
        if ($tenant->license()->exists()) {
            return;
        }
        $plan = SubscriptionPlan::where('code', 'growth')->first()
             ?? SubscriptionPlan::where('is_active', true)->first();

        if (!$plan) {
            $this->command->warn('No subscription plan found — run LicensingSeeder first.');
            return;
        }

        $superAdmin = User::where('is_super_admin', true)->first();
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
            'notes'                => 'Auto-issued by DummyDataSeeder for demo tenant.',
        ]);
    }

    private function seedCompanies(Tenant $tenant): array
    {
        $list = [
            ['Infosys Ltd', 'INFOSYS', '29AAACI4741P1Z2', 'Bangalore'],
            ['Tata Consultancy Services', 'TCS', '27AAACT2727Q1Z5', 'Mumbai'],
            ['Wipro Ltd', 'WIPRO', '29AAACW0518R1Z3', 'Bangalore'],
            ['Reliance Industries', 'RIL', '27AAACR5055K1Z2', 'Mumbai'],
            ['HDFC Bank', 'HDFC', '27AAACH2702H1Z9', 'Mumbai'],
            ['Adani Group', 'ADANI', '24AAACA1234M1Z8', 'Ahmedabad'],
            ['Make My Trip', 'MMT', '07AABCM7783A1ZS', 'Gurgaon'],
            ['Goibibo', 'GOIB', '07AABCG7867R1Z3', 'Gurgaon'],
        ];
        $companies = [];
        foreach ($list as [$name, $code, $gst, $city]) {
            $companies[] = Company::create([
                'tenant_id' => $tenant->id, 'code' => $code, 'name' => $name, 'legal_name' => $name,
                'gst_number' => $gst, 'billing_address' => "Corporate office, $city",
                'city' => $city, 'state' => '', 'country' => 'IN',
                'contact_person' => 'Travel Desk', 'contact_email' => 'travel@'.strtolower($code).'.com',
                'contact_phone' => '+91 80 ' . rand(1111111, 9999999),
                'is_credit_account' => true, 'credit_limit' => 500000, 'credit_days' => 30,
                'has_corporate_rate' => true, 'corporate_discount_percent' => 15, 'is_active' => true,
            ]);
        }
        return $companies;
    }

    private function seedGuests(Tenant $tenant): array
    {
        $first = ['Aarav','Aditya','Arjun','Rohan','Vikram','Rahul','Karan','Siddharth','Manish','Rajesh','Saurabh','Mohit','Nikhil','Akshay','Tarun','Ananya','Aishwarya','Diya','Isha','Kavya','Meera','Neha','Pooja','Riya','Sneha','Tanya','Vidya','Anjali','Priya','Anita','Michael','Sarah','David','Emily','James','Robert','Emma','Olivia','Liam','Noah','Wei','Yuki','Hiroshi','Chen','Mei','Anders','Sofia','Carlos','Lucas','Maria'];
        $last  = ['Sharma','Patel','Kumar','Singh','Gupta','Verma','Mehta','Shah','Joshi','Reddy','Iyer','Naidu','Pillai','Bose','Banerjee','Chatterjee','Sen','Khanna','Kapoor','Malhotra','Smith','Johnson','Williams','Brown','Jones','Tanaka','Suzuki','Wang','Liu','Andersen','Garcia','Lopez','Martinez'];
        $segments = ['leisure','corporate','ota','group','vip','walk_in'];
        $countries = ['IN','IN','IN','IN','IN','US','GB','SG','AE','JP','DE'];

        $guests = [];
        for ($i = 0; $i < 60; $i++) {
            $f = $first[array_rand($first)]; $l = $last[array_rand($last)];
            $guests[] = Guest::create([
                'tenant_id' => $tenant->id,
                'salutation' => rand(0,1) ? 'Mr' : 'Ms',
                'first_name' => $f, 'last_name' => $l,
                'email' => strtolower($f.'.'.$l.'@'.['gmail.com','yahoo.in','outlook.com'][rand(0,2)]),
                'phone' => '+91 ' . rand(70,99) . str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'dob' => Carbon::today()->subYears(rand(22,65))->subDays(rand(0,365)),
                'gender' => rand(0,1) ? 'M' : 'F',
                'nationality' => $countries[array_rand($countries)],
                'id_type' => ['aadhaar','passport','voter','driving_license'][rand(0,3)],
                'id_number' => strtoupper(substr(md5(uniqid()), 0, 12)),
                'address' => rand(1,500).' '.['Park Avenue','MG Road','Brigade Rd','Hill View','Lake Side'][rand(0,4)],
                'city' => ['Bangalore','Mumbai','Delhi','Chennai','Hyderabad','Pune','Kolkata','Jaipur'][rand(0,7)],
                'country' => 'IN', 'segment' => $segments[array_rand($segments)],
                'loyalty_tier' => rand(0,5) === 0 ? ['Silver','Gold','Platinum'][rand(0,2)] : null,
                'loyalty_number' => rand(0,5) === 0 ? 'ML'.rand(10000,99999) : null,
                'total_visits' => rand(0,8), 'lifetime_spend' => rand(0,200000),
                'preferences' => rand(0,2) === 0 ? ['note' => collect(['High floor','Lake view','Late check-in','Non-smoking','Twin beds','Vegetarian','Allergic to nuts'])->random()] : null,
            ]);
        }
        return $guests;
    }

    private function seedReservations(Tenant $tenant, Property $property, array $guests, array $companies): void
    {
        $rooms = Room::where('property_id', $property->id)->get();
        $roomTypes = RoomType::where('property_id', $property->id)->get()->keyBy('id');
        // Reservation.source_type is an enum — only these are valid
        $sources = ['walk_in','direct','ota','corporate','phone','email','website','travel_agent','group'];
        $segments = ['Leisure','Corporate','OTA','Group','Conference','Wedding','MICE','FIT'];
        $today = Carbon::today();

        $count = 0;
        // Past 60 days through 30 days future
        for ($i = -60; $i <= 30; $i++) {
            $bookingsForDay = rand(0, 3);
            for ($j = 0; $j < $bookingsForDay; $j++) {
                $arrival = $today->copy()->addDays($i);
                $nights = rand(1, 5);
                $departure = $arrival->copy()->addDays($nights);
                $guest = $guests[array_rand($guests)];
                $rt = $roomTypes->random();
                $rate = (float) $rt->base_rate;
                $taxPct = $rate <= 7500 ? 12 : 18;
                $isCorp = rand(0, 4) === 0;
                $company = $isCorp ? $companies[array_rand($companies)] : null;
                $discount = $isCorp ? round($rate * 0.15, 2) : 0;
                $effectiveRate = $rate - $discount;
                $roomRevenue = $effectiveRate * $nights;
                $tax = round($roomRevenue * ($taxPct / 100), 2);
                $total = $roomRevenue + $tax;

                // Determine status based on arrival
                if ($departure->lt($today)) {
                    $status = 'checked_out';
                } elseif ($arrival->lte($today) && $departure->gt($today)) {
                    $status = rand(0, 9) === 0 ? 'no_show' : 'checked_in';
                } else {
                    $status = rand(0, 6) === 0 ? 'tentative' : 'confirmed';
                }

                $resNum = 'RES-'.$arrival->format('ymd').'-'.str_pad((string) (++$count + 100), 4, '0', STR_PAD_LEFT);

                $r = Reservation::create([
                    'tenant_id' => $tenant->id, 'property_id' => $property->id,
                    'reservation_number' => $resNum,
                    'confirmation_number' => 'CONF-'.strtoupper(substr(md5(uniqid()),0,8)),
                    'guest_id' => $guest->id, 'company_id' => $company?->id,
                    'guest_name' => trim($guest->first_name.' '.$guest->last_name),
                    'guest_phone' => $guest->phone, 'guest_email' => $guest->email,
                    'source_type' => $isCorp ? 'corporate' : ($sources[array_rand($sources)]),
                    'source_name' => $company?->name ?? collect(['Booking.com','Walk-in','Direct','MakeMyTrip','Expedia','Goibibo','Phone'])->random(),
                    'market_segment' => $segments[array_rand($segments)],
                    'arrival_date' => $arrival, 'departure_date' => $departure, 'nights' => $nights,
                    'arrival_time' => sprintf('%02d:00:00', rand(12,22)),
                    'departure_time' => '11:00:00',
                    'rooms_count' => 1, 'adults' => rand(1,3), 'children' => rand(0,2),
                    'status' => $status,
                    'status_changed_at' => $arrival,
                    'room_revenue' => $roomRevenue, 'total_tax' => $tax,
                    'total_discount' => $discount * $nights,
                    'total_amount' => $total,
                    'paid_amount' => 0, 'balance_amount' => $total,
                    'currency' => 'INR',
                    'is_vip' => rand(0, 19) === 0,
                    'special_requests' => rand(0, 3) === 0 ? collect(['Late check-in','High floor','Twin beds','Extra towels','Allergic to nuts','Honeymoon decoration'])->random() : null,
                ]);

                // Pick a room if checked-in/checked-out
                $assignedRoom = null;
                if (in_array($status, ['checked_in','checked_out'])) {
                    $assignedRoom = $rooms->where('room_type_id', $rt->id)->random();
                }

                $rRoom = ReservationRoom::create([
                    'tenant_id' => $tenant->id, 'property_id' => $property->id,
                    'reservation_id' => $r->id,
                    'room_type_id' => $rt->id, 'room_id' => $assignedRoom?->id,
                    'arrival_date' => $arrival, 'departure_date' => $departure, 'nights' => $nights,
                    'adults' => $r->adults, 'children' => $r->children,
                    'guest_name' => $r->guest_name, 'guest_id' => $guest->id,
                    'average_rate' => $effectiveRate, 'total_rate' => $roomRevenue,
                    'total_tax' => $tax, 'total_amount' => $total,
                    'status' => match($status) {
                        'checked_in' => 'checked_in', 'checked_out' => 'checked_out',
                        'cancelled' => 'cancelled', 'no_show' => 'no_show',
                        default => 'booked',
                    },
                    'checked_in_at' => $status === 'checked_in' || $status === 'checked_out' ? $arrival->copy()->setTime(15, 0) : null,
                    'checked_out_at' => $status === 'checked_out' ? $departure->copy()->setTime(11, 0) : null,
                ]);

                if ($assignedRoom && $status === 'checked_in') {
                    $assignedRoom->update(['status'=>'occupied_clean','fo_status'=>'occupied']);
                }

                // Folio + charges + payment for checked-in / checked-out
                if (in_array($status, ['checked_in','checked_out'])) {
                    $folio = Folio::create([
                        'tenant_id' => $tenant->id, 'property_id' => $property->id,
                        'reservation_id' => $r->id, 'reservation_room_id' => $rRoom->id,
                        'folio_number' => 'F-'.$resNum, 'type' => 'guest',
                        'guest_id' => $guest->id, 'company_id' => $company?->id,
                        'billing_name' => $r->guest_name, 'billing_gst' => $company?->gst_number,
                        'total_charges' => 0, 'total_taxes' => 0, 'total_payments' => 0, 'balance' => 0,
                        'currency' => 'INR',
                        'status' => $status === 'checked_out' ? 'settled' : 'open',
                        'closed_at' => $status === 'checked_out' ? $departure->copy()->setTime(11, 0) : null,
                    ]);

                    // Post nightly room charges
                    $totalCharges = 0; $totalTaxes = 0;
                    for ($n = 0; $n < $nights; $n++) {
                        $chargeDate = $arrival->copy()->addDays($n);
                        FolioCharge::create([
                            'tenant_id' => $tenant->id, 'property_id' => $property->id,
                            'folio_id' => $folio->id,
                            'charge_date' => $chargeDate, 'business_date' => $chargeDate,
                            'category' => 'room',
                            'description' => "Room charge - {$rt->name} #".($assignedRoom?->number ?? '-'),
                            'reference' => 'NA-'.$chargeDate->format('ymd'),
                            'quantity' => 1, 'rate' => $effectiveRate,
                            'amount' => $effectiveRate, 'tax_amount' => round($effectiveRate * $taxPct/100, 2),
                            'net_amount' => $effectiveRate + round($effectiveRate * $taxPct/100, 2),
                            'tax_breakdown' => ['rate' => $taxPct, 'amount' => round($effectiveRate * $taxPct/100, 2)],
                            'is_voided' => false, 'posted_by' => 1,
                            'created_at' => $chargeDate->copy()->setTime(2, 0), 'updated_at' => $chargeDate->copy()->setTime(2, 0),
                        ]);
                        $totalCharges += $effectiveRate;
                        $totalTaxes += round($effectiveRate * $taxPct/100, 2);
                    }

                    // Random extras (food / laundry / spa)
                    $extras = rand(0, 3);
                    for ($k = 0; $k < $extras; $k++) {
                        $cat = collect(['food','beverage','laundry','mini_bar','spa','telephone'])->random();
                        $extraAmt = rand(200, 3500);
                        $extraTax = round($extraAmt * 0.18, 2);
                        $chargeDate = $arrival->copy()->addDays(rand(0, max(0, $nights-1)));
                        FolioCharge::create([
                            'tenant_id' => $tenant->id, 'property_id' => $property->id,
                            'folio_id' => $folio->id,
                            'charge_date' => $chargeDate, 'business_date' => $chargeDate,
                            'category' => $cat,
                            'description' => match($cat) {
                                'food' => collect(['Lunch at Lake View Restaurant','Dinner buffet','Room service breakfast','Veg thali'])->random(),
                                'beverage' => collect(['Mocktails at Sunrise Bar','Wine - Sula Brut','Single malt whisky','Coffee'])->random(),
                                'laundry' => collect(['Express wash','Dry cleaning','Pressing service'])->random(),
                                'mini_bar' => collect(['Mini bar - soft drinks','Mini bar - chocolates','Mini bar - snacks'])->random(),
                                'spa' => collect(['Ayurvedic massage','Couple spa','Body scrub'])->random(),
                                default => 'Phone bill',
                            },
                            'quantity' => 1, 'rate' => $extraAmt,
                            'amount' => $extraAmt, 'tax_amount' => $extraTax, 'net_amount' => $extraAmt + $extraTax,
                            'tax_breakdown' => ['rate' => 18, 'amount' => $extraTax],
                            'is_voided' => false, 'posted_by' => 1,
                            'created_at' => $chargeDate->copy()->setTime(rand(10,22), rand(0,59)),
                            'updated_at' => $chargeDate->copy()->setTime(rand(10,22), rand(0,59)),
                        ]);
                        $totalCharges += $extraAmt;
                        $totalTaxes += $extraTax;
                    }

                    $folioBalance = $totalCharges + $totalTaxes;
                    $payments = 0;

                    // Payments
                    if ($status === 'checked_out') {
                        $modes = ['cash','card','upi'];
                        $payMode = $modes[array_rand($modes)];
                        Payment::create([
                            'tenant_id' => $tenant->id, 'property_id' => $property->id,
                            'folio_id' => $folio->id, 'reservation_id' => $r->id,
                            'receipt_number' => 'RC-'.$departure->format('ymd').rand(1000,9999),
                            'payment_date' => $departure, 'business_date' => $departure,
                            'amount' => $folioBalance, 'currency' => 'INR',
                            'mode' => $payMode, 'received_by' => 1, 'status' => 'completed',
                        ]);
                        $payments = $folioBalance;
                    } elseif ($status === 'checked_in' && rand(0,1)) {
                        // Sometimes advance paid
                        $advance = round($folioBalance * (rand(20,80)/100), 2);
                        Payment::create([
                            'tenant_id' => $tenant->id, 'property_id' => $property->id,
                            'folio_id' => $folio->id, 'reservation_id' => $r->id,
                            'receipt_number' => 'RC-'.$arrival->format('ymd').rand(1000,9999),
                            'payment_date' => $arrival, 'business_date' => $arrival,
                            'amount' => $advance, 'currency' => 'INR',
                            'mode' => 'card', 'received_by' => 1, 'status' => 'completed',
                        ]);
                        $payments = $advance;
                    }

                    $folio->update([
                        'total_charges' => $totalCharges, 'total_taxes' => $totalTaxes,
                        'total_payments' => $payments, 'balance' => $folioBalance - $payments,
                    ]);
                    $r->update([
                        'total_amount' => $folioBalance,
                        'paid_amount' => $payments,
                        'balance_amount' => $folioBalance - $payments,
                    ]);
                }
            }
        }
        $this->command->info("  -> {$count} reservations created");
    }

    private function seedPos(Tenant $tenant, Property $property): void
    {
        $outlets = [
            ['LVR', 'Lake View Restaurant', 'restaurant', '07:00', '23:00', 10],
            ['SRB', 'Sunrise Bar', 'bar', '17:00', '01:00', 10],
            ['IRD', 'In-Room Dining', 'room_service', '00:00', '23:59', 5],
            ['POO', 'Poolside Café', 'pool', '10:00', '20:00', 10],
        ];
        $createdOutlets = [];
        foreach ($outlets as [$code, $name, $type, $open, $close, $sc]) {
            $createdOutlets[] = Outlet::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'code'=>$code,'name'=>$name,'type'=>$type,
                'open_time'=>$open,'close_time'=>$close,
                'service_charge_percent'=>$sc,'is_active'=>true,
            ]);
        }

        // Tables for the main restaurant
        $main = $createdOutlets[0];
        foreach (['Indoor','Lake-side','Private dining'] as $section) {
            for ($i = 1; $i <= 6; $i++) {
                PosTable::create([
                    'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                    'outlet_id'=>$main->id,
                    'name'=>$section[0].$i, 'section'=>$section,
                    'capacity'=>rand(2,6), 'status'=>'available','is_active'=>true,
                ]);
            }
        }

        // Menu categories + items
        $menuStructure = [
            'Lake View Restaurant' => [
                ['Starters', false, [
                    ['Paneer Tikka', 350, 'veg'], ['Tandoori Chicken', 450, 'non_veg'],
                    ['Hara Bhara Kebab', 320, 'veg'], ['Fish Amritsari', 480, 'non_veg'],
                    ['Veg Manchurian', 280, 'veg'], ['Chicken Lollipop', 400, 'non_veg'],
                ]],
                ['Mains', false, [
                    ['Butter Chicken', 550, 'non_veg'], ['Dal Makhani', 320, 'veg'],
                    ['Paneer Butter Masala', 380, 'veg'], ['Mutton Rogan Josh', 650, 'non_veg'],
                    ['Veg Biryani', 420, 'veg'], ['Hyderabadi Chicken Biryani', 520, 'non_veg'],
                    ['Palak Paneer', 360, 'veg'], ['Fish Curry', 580, 'non_veg'],
                ]],
                ['Breads & Rice', false, [
                    ['Butter Naan', 60, 'veg'], ['Garlic Naan', 80, 'veg'],
                    ['Tandoori Roti', 40, 'veg'], ['Jeera Rice', 220, 'veg'],
                ]],
                ['Desserts', false, [
                    ['Gulab Jamun', 180, 'veg'], ['Kulfi', 200, 'veg'],
                    ['Gajar Halwa', 220, 'veg'], ['Tiramisu', 280, 'veg'],
                ]],
            ],
            'Sunrise Bar' => [
                ['Spirits', true, [
                    ['Glenfiddich 12 Yr (60ml)', 850, null], ['Black Label (60ml)', 600, null],
                    ['Tanqueray Gin', 550, null], ['Bacardi White Rum', 400, null],
                ]],
                ['Cocktails', true, [
                    ['Old Fashioned', 650, null], ['Mojito', 450, null],
                    ['Negroni', 600, null], ['Margarita', 550, null], ['Pina Colada', 480, null],
                ]],
                ['Mocktails', false, [
                    ['Virgin Mojito', 250, null], ['Fresh Lime Soda', 150, null],
                    ['Watermelon Cooler', 220, null],
                ]],
                ['Beer', true, [
                    ['Kingfisher Premium', 280, null], ['Bira 91', 320, null],
                    ['Heineken', 380, null],
                ]],
            ],
        ];
        foreach ($createdOutlets as $outlet) {
            $structure = $menuStructure[$outlet->name] ?? null;
            if (!$structure) continue;
            $catOrder = 0;
            foreach ($structure as [$catName, $isLiquor, $items]) {
                $cat = MenuCategory::create([
                    'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                    'outlet_id'=>$outlet->id, 'name'=>$catName,
                    'display_order'=>++$catOrder, 'is_liquor'=>$isLiquor, 'is_active'=>true,
                ]);
                foreach ($items as [$name, $price, $foodType]) {
                    // food_type enum: veg|non_veg|egg|jain|beverage|liquor
                    $resolvedType = $foodType ?: ($isLiquor ? 'liquor' : 'beverage');
                    MenuItem::create([
                        'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                        'category_id'=>$cat->id, 'code'=>strtoupper(substr(preg_replace('/\s+/','',$name),0,8)).rand(10,99),
                        'name'=>$name, 'price'=>$price, 'cost'=>round($price * 0.4, 2),
                        'tax_percent'=>$isLiquor ? 28 : ($price > 1000 ? 18 : 5),
                        'food_type'=>$resolvedType, 'is_taxable'=>true, 'available'=>true, 'is_active'=>true,
                    ]);
                }
            }
        }

        // KDS stations
        $stations = [];
        foreach ([
            ['HOT','Hot Kitchen','hot_kitchen', 12],
            ['COLD','Cold Kitchen','cold_kitchen', 8],
            ['BAR','Bar','bar', 5],
            ['TANDOOR','Tandoor','tandoor', 10],
            ['EXPO','Expo / Pass','expo', 3],
        ] as [$code,$name,$type,$prep]) {
            $stations[] = KdsStation::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'outlet_id'=>$main->id,
                'code'=>$code,'name'=>$name,'type'=>$type,
                'default_prep_minutes'=>$prep, 'is_active'=>true,
            ]);
        }

        // ---- Sample POS orders (mix of in-flight + settled) ----
        $menuItemsByOutlet = MenuItem::where('property_id', $property->id)->with('category')->get()->groupBy(fn ($mi) => $mi->category?->outlet_id);
        $checkedInReservations = Reservation::where('property_id', $property->id)->where('status', 'checked_in')->limit(20)->get();
        $tablesMain = PosTable::where('outlet_id', $main->id)->get();

        $orderCount = 0;
        foreach ($createdOutlets as $outlet) {
            $items = $menuItemsByOutlet->get($outlet->id, collect());
            if ($items->isEmpty()) continue;

            // Past 7 days × ~6 orders/day per outlet
            for ($daysAgo = 0; $daysAgo < 7; $daysAgo++) {
                $orderDate = Carbon::today()->subDays($daysAgo);
                $ordersToday = rand(3, 8);
                for ($n = 0; $n < $ordersToday; $n++) {
                    $orderType = $outlet->type === 'room_service' ? 'room_service' : 'dine_in';
                    $covers = rand(1, 5);
                    $itemsForOrder = $items->random(min(rand(2, 6), $items->count()));
                    $subtotal = 0; $taxTotal = 0;
                    $orderItemsData = [];
                    foreach ($itemsForOrder as $mi) {
                        $qty = rand(1, 3);
                        $unit = (float) $mi->price;
                        $line = $qty * $unit;
                        $tax = round($line * (($mi->tax_percent ?? 5)/100), 2);
                        $subtotal += $line;
                        $taxTotal += $tax;
                        $orderItemsData[] = [
                            'menu_item_id' => $mi->id, 'item_name' => $mi->name,
                            'quantity' => $qty, 'unit_price' => $unit,
                            'amount' => $line, 'tax_percent' => $mi->tax_percent ?? 5,
                            'tax_amount' => $tax, 'total' => $line + $tax,
                            'status' => $daysAgo > 0 ? 'served' : collect(['sent','preparing','ready','served'])->random(),
                        ];
                    }
                    $sc = round($subtotal * (($outlet->service_charge_percent ?? 10) / 100), 2);
                    $total = $subtotal + $taxTotal + $sc;
                    $isOpen = $daysAgo === 0 && rand(0, 2) > 0;
                    $status = $isOpen
                        ? collect(['open','sent_to_kitchen','preparing','ready','served'])->random()
                        : collect(['settled','billed'])->random();

                    $openedAt = $orderDate->copy()->setTime(rand(11, 22), rand(0, 59));
                    $reservation = $orderType === 'room_service' && $checkedInReservations->isNotEmpty() ? $checkedInReservations->random() : null;

                    $order = PosOrder::create([
                        'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                        'outlet_id'=>$outlet->id,
                        'order_number'=>'ORD-'.$openedAt->format('ymd').'-'.str_pad((string) (++$orderCount), 4, '0', STR_PAD_LEFT),
                        'table_id'=>$orderType === 'dine_in' && $tablesMain->isNotEmpty() ? $tablesMain->random()->id : null,
                        'order_type'=>$orderType,
                        'reservation_id'=>$reservation?->id,
                        'room_number'=>$reservation?->rooms?->first()?->room?->number,
                        'guest_name'=>$reservation?->guest_name ?: collect(['Walk-in','Mr Sharma','Ms Patel','Foreign guest','Local family'])->random(),
                        'covers'=>$covers,'server_id'=>1,
                        'status'=>$status,
                        'opened_at'=>$openedAt,
                        'kot_printed_at'=>$openedAt->copy()->addMinutes(2),
                        'served_at'=>in_array($status, ['served','billed','settled']) ? $openedAt->copy()->addMinutes(rand(15, 45)) : null,
                        'billed_at'=>in_array($status, ['billed','settled']) ? $openedAt->copy()->addMinutes(rand(50, 90)) : null,
                        'settled_at'=>$status === 'settled' ? $openedAt->copy()->addMinutes(rand(60, 120)) : null,
                        'subtotal'=>$subtotal,'service_charge'=>$sc,'tax_amount'=>$taxTotal,
                        'discount_amount'=>0,'round_off'=>0,'total_amount'=>$total,
                        'created_by'=>1,
                        'created_at'=>$openedAt,'updated_at'=>$openedAt,
                    ]);

                    foreach ($orderItemsData as $od) {
                        \DB::table('pos_order_items')->insert(array_merge($od, [
                            'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                            'order_id'=>$order->id,
                            'created_at'=>$openedAt,'updated_at'=>$openedAt,
                        ]));
                    }

                    // Create one KDS ticket per order routed to a station (Hot kitchen by default)
                    if (in_array($status, ['open','sent_to_kitchen','preparing','ready'])) {
                        \DB::table('kds_tickets')->insert([
                            'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                            'order_id'=>$order->id,'station_id'=>$stations[array_rand($stations)]->id,
                            'ticket_number'=>'KOT-'.$order->id,
                            'status'=>match($status){'open'=>'queued','sent_to_kitchen'=>'queued','preparing'=>'started','ready'=>'ready',default=>'queued'},
                            'queued_at'=>$openedAt->copy()->addMinutes(2),
                            'started_at'=>in_array($status, ['preparing','ready']) ? $openedAt->copy()->addMinutes(5) : null,
                            'ready_at'=>$status === 'ready' ? $openedAt->copy()->addMinutes(20) : null,
                            'priority'=>'normal','is_recall'=>false,'is_overdue'=>false,
                            'target_prep_seconds'=>900,
                            'created_at'=>$openedAt,'updated_at'=>$openedAt,
                        ]);
                    }
                }
            }
        }
        $this->command->info("  -> $orderCount POS orders created");
    }

    private function seedAmenities(Tenant $tenant, Property $property): void
    {
        // Amenity.category enum: transport, meal, spa, tour, experience, merchandise, utility, other
        // Amenity.pricing_type enum: per_stay, per_night, per_person, per_person_per_night, flat
        $items = [
            ['SPA-AYU','Ayurvedic Body Massage (60min)','spa','flat',2500,18],
            ['SPA-COUP','Couple Spa Therapy (90min)','spa','flat',5500,18],
            ['SPA-FACI','Gold Facial','spa','flat',1800,18],
            ['POOL','Pool Access (non-guest)','utility','flat',500,18],
            ['GYM','Gym Day Pass (non-guest)','utility','flat',300,18],
            ['LAUN-EXP','Express Laundry (per item)','utility','flat',150,18],
            ['LAUN-DRY','Dry Cleaning (per item)','utility','flat',250,18],
            ['LAUN-PRESS','Pressing Service','utility','flat',80,18],
            ['TRAVEL-ARP','Airport Transfer (one way)','transport','flat',1500,18],
            ['TRAVEL-CITY','Half-day City Tour','tour','flat',2500,18],
            ['TRAVEL-FULL','Full-day Sightseeing','tour','flat',4500,18],
            ['PARK-DAY','Valet Parking (per day)','transport','per_night',300,18],
            ['EXTRA-BREAK','Add Breakfast','meal','per_person_per_night',650,5],
            ['EXTRA-DECOR','Honeymoon Decoration','experience','flat',2500,18],
            ['EXTRA-CAKE','Birthday Cake (1kg)','merchandise','flat',1200,18],
        ];
        foreach ($items as [$code,$name,$cat,$pricing,$price,$tax]) {
            Amenity::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'code'=>$code,'name'=>$name,'category'=>$cat,
                'pricing_type'=>$pricing,'price'=>$price,'tax_percent'=>$tax,
                'available_at_booking'=>true,'available_at_checkin'=>true,'available_in_stay'=>true,
                'is_active'=>true,
            ]);
        }

        // A few amenity orders
        $amenities = Amenity::where('property_id', $property->id)->get();
        $reservations = Reservation::where('property_id', $property->id)->whereIn('status', ['checked_in','checked_out'])->limit(20)->get();
        foreach ($reservations->take(15) as $r) {
            $a = $amenities->random();
            AmenityOrder::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'amenity_id'=>$a->id, 'reservation_id'=>$r->id,
                'order_number'=>'AM-'.now()->format('ymd').rand(1000,9999),
                'service_date'=>$r->arrival_date->copy()->addDays(rand(0,max(0,$r->nights-1))),
                'service_time'=>sprintf('%02d:00:00', rand(10,20)),
                'quantity'=>1, 'unit_price'=>$a->price,
                'tax_amount'=>round($a->price * ($a->tax_percent ?? 0)/100, 2),
                'total_amount'=>$a->price + round($a->price * ($a->tax_percent ?? 0)/100, 2),
                'status'=>collect(['pending','confirmed','fulfilled','cancelled'])->random(),
            ]);
        }
    }

    private function seedBanquet(Tenant $tenant, Property $property, array $companies): void
    {
        $halls = [
            ['MARBLE','Marble Hall', 4500, 800, 600, 350, 500, 80000, 150000],
            ['EMERALD','Emerald Pavilion', 2500, 350, 250, 150, 200, 40000, 75000],
            ['PEARL','Pearl Boardroom', 800, 60, 40, 25, 30, 15000, 25000],
        ];
        $createdHalls = [];
        foreach ($halls as [$code,$name,$area,$theatre,$classroom,$cluster,$banquet,$half,$full]) {
            $createdHalls[] = BanquetHall::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'code'=>$code,'name'=>$name,'description'=>"Premier $name with full A/V setup",
                'area_sqft'=>$area,
                'theatre_capacity'=>$theatre,'classroom_capacity'=>$classroom,
                'cluster_capacity'=>$cluster,'banquet_capacity'=>$banquet,
                'hourly_rate'=>round($half/4, 0),'half_day_rate'=>$half,'full_day_rate'=>$full,
                'amenities'=>['Projector','Stage','Dance floor','LED screens','Wireless mics','Wedding mandap'],
                'is_active'=>true,
            ]);
        }

        // Bookings
        $events = [
            ['Sharma-Singh Wedding','wedding', 'wedding'],
            ['Infosys Q4 Townhall','corporate', 'conference'],
            ['Patel Reception','reception', 'reception'],
            ['TCS Leadership Offsite','corporate', 'conference'],
            ['HDFC Birthday Bash','birthday', 'birthday'],
            ['Mehta Anniversary Dinner','anniversary', 'anniversary'],
            ['Wipro Product Launch','product_launch', 'product_launch'],
            ['Reliance Sales Training','training', 'training'],
        ];
        $today = Carbon::today();
        foreach ($events as $idx => [$name, $type, $eventType]) {
            $hall = $createdHalls[array_rand($createdHalls)];
            $eventDate = $today->copy()->addDays(rand(-15, 45));
            $pax = rand(50, $hall->banquet_capacity);
            $hallRent = $hall->full_day_rate;
            $foodPerPax = rand(800, 2500);
            $food = $pax * $foodPerPax;
            $bev = $pax * 250;
            $decor = rand(15000, 50000);
            $av = rand(5000, 15000);
            $sub = $hallRent + $food + $bev + $decor + $av;
            $tax = round($sub * 0.18, 2);
            $total = $sub + $tax;

            BanquetBooking::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'hall_id'=>$hall->id,
                'booking_number'=>'BQ-'.$eventDate->format('ymd').'-'.str_pad($idx+1, 3, '0', STR_PAD_LEFT),
                'company_id'=>$type === 'corporate' ? $companies[array_rand($companies)]->id : null,
                'event_name'=>$name,'event_type'=>$eventType,
                'event_date'=>$eventDate,
                'event_start_time'=>'19:00:00','event_end_time'=>'23:30:00',
                'expected_pax'=>$pax,
                'hall_rent'=>$hallRent,'food_amount'=>$food,'beverage_amount'=>$bev,
                'decor_amount'=>$decor,'av_amount'=>$av,'other_amount'=>0,
                'subtotal'=>$sub,'tax_amount'=>$tax,'total_amount'=>$total,
                'advance_received'=>$eventDate->lt($today) ? $total : round($total*0.5, 2),
                'status'=>$eventDate->lt($today) ? 'completed' : ($eventDate->lt($today->copy()->addDays(7)) ? 'confirmed' : collect(['enquiry','tentative','confirmed'])->random()),
                'menu_details'=>'8-course buffet with live counters',
                'setup_notes'=>'Theatre style with center aisle',
            ]);
        }
    }

    private function seedStore(Tenant $tenant, Property $property): void
    {
        // store_categories.type enum: raw_material | beverage | liquor | housekeeping | engineering | stationery | other
        $cats = [];
        $catMap = [
            'Vegetables' => 'raw_material', 'Fruits' => 'raw_material',
            'Dairy' => 'raw_material', 'Meat & Poultry' => 'raw_material',
            'Seafood' => 'raw_material', 'Groceries' => 'raw_material',
            'Beverages' => 'beverage',
            'Cleaning supplies' => 'housekeeping',
            'Linen' => 'housekeeping', 'Toiletries' => 'housekeeping',
        ];
        foreach (array_keys($catMap) as $name) {
            $cats[$name] = StoreCategory::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'name'=>$name,'type'=>$catMap[$name],
                'is_active'=>true,
            ]);
        }

        // Vendors
        $vendors = [];
        foreach ([
            ['VEG-01','Mahesh Vegetables','Mahesh Kumar','+91 98300 12345','30'],
            ['DAIRY-01','Amul Dairy Distributor','Amul Sales','+91 98200 23456','15'],
            ['MEAT-01','Modern Meat Suppliers','Rakesh Singh','+91 98765 34567','7'],
            ['GRO-01','Reliance Wholesale','Account Mgr','+91 22 6555 5555','30'],
            ['LINEN-01','Welspun Linens','B2B Sales','+91 22 6644 4444','45'],
            ['CLEAN-01','HUL Hospitality','Channel Sales','+91 22 6633 3333','30'],
        ] as [$code,$name,$contact,$phone,$terms]) {
            $vendors[] = StoreVendor::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'code'=>$code,'name'=>$name,'contact_person'=>$contact,
                'phone'=>$phone,'email'=>strtolower(str_replace(' ','',$contact)).'@vendor.test',
                'gst_number'=>strtoupper(substr(md5($code),0,15)),
                'address'=>'Vendor warehouse','payment_terms_days'=>(int) $terms,
                'credit_limit'=>500000,'is_active'=>true,
            ]);
        }

        // Items
        $items = [
            ['VEG-TOMATO','Tomato','Vegetables','kg', 50, 30, 200, 30],
            ['VEG-ONION','Onion','Vegetables','kg', 80, 40, 300, 28],
            ['VEG-POTATO','Potato','Vegetables','kg', 100, 50, 400, 25],
            ['DAIRY-MILK','Toned Milk','Dairy','litre', 60, 80, 200, 50],
            ['DAIRY-BUTTER','Butter (Amul)','Dairy','kg', 8, 10, 30, 480],
            ['DAIRY-PANEER','Paneer','Dairy','kg', 5, 8, 20, 320],
            ['MEAT-CHICKEN','Chicken','Meat & Poultry','kg', 25, 15, 80, 250],
            ['MEAT-MUTTON','Mutton','Meat & Poultry','kg', 12, 8, 30, 750],
            ['SEA-FISH','Pomfret','Seafood','kg', 15, 10, 40, 580],
            ['GRO-RICE','Basmati Rice','Groceries','kg', 200, 50, 500, 120],
            ['GRO-OIL','Sunflower Oil','Groceries','litre', 50, 30, 100, 145],
            ['BEV-COKE','Coca-Cola 300ml','Beverages','case', 8, 10, 30, 240],
            ['BEV-WATER','Mineral Water 1L','Beverages','case', 30, 25, 80, 240],
            ['CLEAN-DET','Surf Excel 5kg','Cleaning supplies','pack', 4, 5, 15, 850],
            ['CLEAN-PHEN','Phenyl 5L','Cleaning supplies','can', 6, 4, 20, 380],
            ['LINEN-TOWEL','Bath Towel','Linen','pcs', 80, 50, 200, 350],
            ['LINEN-SHEET','Bed Sheet King','Linen','pcs', 60, 40, 150, 650],
            ['TOIL-SHAMP','Shampoo Bottle 30ml','Toiletries','pcs', 200, 100, 500, 25],
            ['TOIL-SOAP','Soap Bar','Toiletries','pcs', 300, 150, 800, 18],
        ];
        foreach ($items as [$code,$name,$catName,$unit,$stock,$reorder,$max,$rate]) {
            StoreItem::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'category_id'=>$cats[$catName]->id,
                'code'=>$code,'name'=>$name,'unit'=>$unit,
                'current_stock'=>$stock,'reorder_level'=>$reorder,'max_stock'=>$max,
                'last_purchase_price'=>$rate,'average_cost'=>$rate * 0.95,
                'is_active'=>true,
            ]);
        }

        // 5 sample purchase orders
        $statuses = ['draft','sent','partial','received'];
        for ($i = 1; $i <= 6; $i++) {
            $vendor = $vendors[array_rand($vendors)];
            PurchaseOrder::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'vendor_id'=>$vendor->id,
                'po_number'=>'PO-'.now()->format('ym').'-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'po_date'=>Carbon::today()->subDays(rand(0,20)),
                'expected_delivery_date'=>Carbon::today()->addDays(rand(1,7)),
                'subtotal'=>($amt = rand(15000, 80000)),
                'tax_amount'=>round($amt * 0.18, 2),
                'total_amount'=>$amt + round($amt * 0.18, 2),
                'status'=>$statuses[array_rand($statuses)],
                'created_by'=>1,
            ]);
        }
    }

    private function seedReviews(Tenant $tenant, Property $property): void
    {
        $sources = [];
        foreach ([
            ['google','Google Business'],
            ['booking_com','Booking.com'],
            ['tripadvisor','TripAdvisor'],
            ['mmt','MakeMyTrip'],
            ['agoda','Agoda'],
        ] as [$key,$label]) {
            $sources[$key] = ReviewSource::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'source'=>$key,'external_property_id'=>'EXT-'.strtoupper($key),
                'auto_fetch'=>true,'is_active'=>true,
            ]);
        }
        $reviewBodies = [
            'positive' => [
                'Stunning lake view from our room. Staff were incredibly attentive — special thanks to the front desk team.',
                'Loved the spa! Peaceful, professional therapists. Will definitely return.',
                'The food at Lake View Restaurant is exceptional. Try the butter chicken.',
                'Best heritage hotel experience in Udaipur. Felt like royalty.',
                'Perfect honeymoon stay. Beautiful decoration in the room on arrival.',
                'Excellent property, clean rooms, polite staff, great location.',
            ],
            'neutral' => [
                'Decent stay. Some maintenance issues with the AC but staff fixed it quickly.',
                'Good location but breakfast options were limited.',
                'Comfortable rooms, average food. Will probably try elsewhere next time.',
            ],
            'negative' => [
                'Check-in was slow. Had to wait 40 minutes despite confirmed booking.',
                'Room was not cleaned properly on the second day. Mini bar items missing.',
                'Wifi kept dropping. Not great for a business stay.',
            ],
        ];
        foreach (range(1, 24) as $i) {
            $sentiment = rand(0,9) < 7 ? 'positive' : (rand(0,1) ? 'neutral' : 'negative');
            $rating = match($sentiment) {
                'positive' => rand(8,10) / 2,
                'neutral'  => rand(6,7) / 2 + 0.5,
                'negative' => rand(2,5) / 2,
            };
            $sourceKey = collect(['google','booking_com','tripadvisor','mmt','agoda'])->random();
            $body = collect($reviewBodies[$sentiment])->random();
            $reviewDate = Carbon::today()->subDays(rand(0, 90));
            Review::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'source_id'=>$sources[$sourceKey]->id,'source'=>$sourceKey,
                'external_review_id'=>'EXT-'.uniqid(),
                'reviewer_name'=>collect(['Anjali Kapoor','Michael Chen','Priya Sharma','James Wilson','Aditi Patel','Wei Zhang','Rohan Kumar','Sarah Johnson','Hiroshi Tanaka','Maria Garcia'])->random(),
                'reviewer_country'=>collect(['IN','US','GB','SG','DE','JP','AE','AU'])->random(),
                'stay_date'=>$reviewDate->copy()->subDays(rand(1,5)),
                'review_date'=>$reviewDate,
                'rating'=>$rating,'original_rating'=>$rating*2,'original_rating_max'=>10,
                'title'=>collect(['Wonderful stay','Great service','Highly recommend','Lovely property','Some issues','Decent stay'])->random(),
                'body'=>$body,
                'aspect_ratings'=>['cleanliness'=>rand(7,10),'service'=>rand(7,10),'location'=>rand(8,10),'value'=>rand(6,9)],
                'sentiment'=>$sentiment,'language'=>'en',
                'response_text'=>$sentiment !== 'positive' ? 'Thank you for your feedback. We are truly sorry for the inconvenience and have shared this with our team.' : null,
                'responded_at'=>$sentiment !== 'positive' ? $reviewDate->copy()->addDays(2) : null,
                'is_visible'=>true,
            ]);
        }
    }

    private function seedChannel(Tenant $tenant, Property $property): void
    {
        $channels = ['booking_com','expedia','agoda','goibibo','makemytrip'];
        $roomTypes = RoomType::where('property_id', $property->id)->get();
        $ratePlans = RatePlan::where('property_id', $property->id)->get();

        foreach ($channels as $ch) {
            foreach ($roomTypes as $rt) {
                ChannelMapping::create([
                    'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                    'channel'=>$ch, 'external_hotel_id'=>'EXT-'.strtoupper($ch).'-MLPU',
                    'external_property_code'=>'MLPU-'.strtoupper(substr($ch,0,3)),
                    'room_type_id'=>$rt->id,
                    'external_room_type_id'=>strtoupper($ch).'-'.$rt->code.'-'.rand(1000,9999),
                    'push_inventory'=>true,'push_rates'=>true,'pull_bookings'=>true,
                    'last_sync_at'=>now()->subMinutes(rand(2, 120)),
                    'last_sync_status'=>rand(0,9) === 0 ? 'failed' : 'success',
                    'is_active'=>true,
                ]);
            }
        }

        // Sync log entries (direction: push|pull, status: queued|in_progress|success|partial|failed|retry)
        for ($i = 0; $i < 30; $i++) {
            $ch = $channels[array_rand($channels)];
            $direction = rand(0,1) ? 'push' : 'pull';
            $op = $direction === 'push'
                ? collect(['inventory_push','rate_push','restriction_push'])->random()
                : collect(['booking_pull','booking_modify','booking_cancel'])->random();
            $status = rand(0,9) === 0 ? 'failed' : 'success';
            $startedAt = now()->subMinutes(rand(5, 4000));
            ChannelSyncLog::create([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'channel'=>$ch,'direction'=>$direction,'operation'=>$op,
                'date_from'=>Carbon::today(),'date_to'=>Carbon::today()->addDays(60),
                'status'=>$status,
                'records_processed'=>rand(10, 200),
                'records_failed'=>$status === 'failed' ? rand(1, 10) : 0,
                'started_at'=>$startedAt,'completed_at'=>$startedAt->copy()->addSeconds(rand(2,30)),
                'duration_ms'=>rand(500, 8000),
                'created_at'=>$startedAt,'updated_at'=>$startedAt,
            ]);
        }
    }

    private function seedAccounts(Tenant $tenant, Property $property): void
    {
        // Voucher types
        $vts = [];
        foreach ([
            ['CR','Cash receipt','receipt','CR'],
            ['BR','Bank receipt','receipt','BR'],
            ['CP','Cash payment','payment','CP'],
            ['BP','Bank payment','payment','BP'],
            ['JV','Journal voucher','journal','JV'],
            ['SV','Sales voucher','sales','SV'],
            ['PV','Purchase voucher','purchase','PV'],
        ] as [$code,$name,$type,$prefix]) {
            $vts[$code] = DB::table('accounts_voucher_types')->insertGetId([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'code'=>$code,'name'=>$name,'type'=>$type,'prefix'=>$prefix,
                'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        // Chart of accounts (basic)
        $accounts = [
            ['1100','Cash on hand','asset','cash'],
            ['1110','Bank — Current account','asset','bank'],
            ['1200','Accounts receivable — Guest','asset','current_asset'],
            ['1210','Accounts receivable — Corporate','asset','current_asset'],
            ['1500','Furniture & fittings','asset','fixed_asset'],
            ['1510','Building','asset','fixed_asset'],
            ['2100','Accounts payable — Vendors','liability','current_liability'],
            ['2200','GST output payable','liability','tax_payable'],
            ['2210','TDS payable','liability','tax_payable'],
            ['3000','Owners equity','equity','capital'],
            ['4100','Room revenue','income','sales_revenue'],
            ['4200','F&B revenue','income','sales_revenue'],
            ['4300','Banquet revenue','income','sales_revenue'],
            ['4400','Spa & other revenue','income','service_revenue'],
            ['5100','Cost of food sold','expense','cost_of_sales'],
            ['5200','Cost of beverage sold','expense','cost_of_sales'],
            ['6100','Salaries','expense','operating_expense'],
            ['6200','Utilities','expense','operating_expense'],
            ['6300','Repairs & maintenance','expense','operating_expense'],
            ['6400','Marketing','expense','admin_expense'],
        ];
        foreach ($accounts as [$code,$name,$type,$subtype]) {
            DB::table('accounts_chart')->insert([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'code'=>$code,'name'=>$name,'type'=>$type,'subtype'=>$subtype,
                'is_active'=>true,'is_system'=>false,
                'opening_balance'=>0,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        // A few sample vouchers
        for ($i = 1; $i <= 12; $i++) {
            $type = collect(['CR','BR','SV','PV'])->random();
            $amt = rand(5000, 80000);
            $date = Carbon::today()->subDays(rand(0,30));
            DB::table('accounts_vouchers')->insert([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'voucher_type_id'=>$vts[$type],
                'voucher_number'=>$type.'-'.$date->format('ymd').'-'.str_pad($i,3,'0',STR_PAD_LEFT),
                'voucher_date'=>$date,'business_date'=>$date,
                'reference_type'=>'manual','reference_id'=>null,
                'narration'=>collect(['Cash sales','Bank deposit','Vendor payment','Corporate AR receipt','Salary payment','Utility bill'])->random(),
                'total_debit'=>$amt,'total_credit'=>$amt,
                'status'=>'posted','posted_by'=>1,'posted_at'=>$date,
                'created_at'=>$date,'updated_at'=>$date,
            ]);
        }
    }

    private function seedRevenue($tenant, $property): void
    {
        // Competitors
        $compNames = [
            ['Trident Udaipur', 1.2, true],
            ['The Oberoi Udaivilas', 3.5, true],
            ['Taj Lake Palace', 0.8, true],
            ['Radisson Blu Udaipur', 4.5, false],
            ['Lemon Tree Udaipur', 2.8, false],
        ];
        $compIds = [];
        foreach ($compNames as [$name, $dist, $primary]) {
            $compIds[] = DB::table('revenue_competitors')->insertGetId([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'name'=>$name,'distance_km'=>$dist,
                'booking_com_url'=>'https://www.booking.com/hotel/in/'.strtolower(str_replace(' ','-',$name)).'.html',
                'mmt_url'=>'https://www.makemytrip.com/hotels/'.strtolower(str_replace(' ','-',$name)),
                'tripadvisor_url'=>null,
                'is_primary_compset'=>$primary,'is_active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        // Rate-shop history (last 7 days × stay dates next 30 days × 2-3 competitors)
        $rooms = ['Deluxe','Premium Suite','Heritage Suite'];
        for ($daysAgo = 0; $daysAgo < 7; $daysAgo++) {
            $shopDate = Carbon::today()->subDays($daysAgo);
            for ($stayOffset = 0; $stayOffset < 14; $stayOffset += 2) {
                $stayDate = Carbon::today()->addDays($stayOffset);
                foreach (array_rand($compIds, 3) as $idx) {
                    $compId = $compIds[$idx];
                    $room = $rooms[array_rand($rooms)];
                    $rate = rand(5500, 28000);
                    DB::table('revenue_rate_shop')->insert([
                        'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                        'competitor_id'=>$compId,
                        'shop_date'=>$shopDate,'stay_date'=>$stayDate,
                        'source'=>collect(['booking.com','mmt','agoda','goibibo'])->random(),
                        'room_type_label'=>$room,
                        'rate'=>$rate,'currency'=>'INR',
                        'available'=>rand(0,9) !== 0,
                        'created_at'=>$shopDate, 'updated_at'=>$shopDate,
                    ]);
                }
            }
        }

        // Pricing rules — rule_type enum: occupancy_based|days_to_arrival|day_of_week|season|event|compset_position|min_max_floor
        $rules = [
            ['Weekend uplift',                 'day_of_week',     'increase_percent', 15,  true],
            ['Last-minute discount (≤3 days)', 'days_to_arrival', 'decrease_percent', 10,  true],
            ['Peak season uplift (Oct-Mar)',   'season',          'increase_percent', 20,  true],
            ['High-occupancy uplift (>80%)',   'occupancy_based', 'increase_percent', 25,  true],
            ['Low-occupancy promotional (<40%)','occupancy_based','decrease_percent', 12,  false],
            ['Compset top-of-page',            'compset_position','increase_percent', 8,   true],
        ];
        foreach ($rules as $i => [$name,$type,$action,$value,$active]) {
            DB::table('revenue_pricing_rules')->insert([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'name'=>$name,'rule_type'=>$type,
                'priority'=>$i+1,'conditions'=>json_encode([]),
                'action'=>$action,'value'=>$value,
                'valid_from'=>Carbon::today()->subDays(30),'valid_to'=>Carbon::today()->addDays(365),
                'is_active'=>$active,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        // Forecasts (next 14 days)
        for ($i = 0; $i < 14; $i++) {
            $stay = Carbon::today()->addDays($i);
            $occ = rand(45, 92);
            $arr = rand(7500, 14500);
            DB::table('revenue_forecasts')->insert([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'forecast_date'=>Carbon::today(),
                'stay_date'=>$stay,
                'forecast_occupancy_pct'=>$occ,
                'forecast_arr'=>$arr,
                'forecast_revpar'=>round($arr * $occ / 100, 0),
                'booking_pace'=>json_encode(['vs_last_year_pct'=>rand(-15, 25), 'on_books'=>rand(8, 22), 'pace_index'=>round(rand(80, 130)/100, 2)]),
                'model_version'=>'v1.2-baseline',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    private function seedPromotions($tenant, $property): void
    {
        $promos = [
            ['SUMMER2026','Summer special',       'percentage', 15, 'all',       today()->subDays(15), today()->addDays(60), 2, 0,    null, true,  true,  false, true,  true,  true,  'Get 15% off on all stays this summer'],
            ['EARLYBIRD30','Early bird (30+ days)','percentage', 20, 'all',       today(),               today()->addDays(180), 1, 0,    null, true,  true,  true,  false, true,  true,  '20% off when you book at least 30 days in advance'],
            ['CORP-INFOSYS','Infosys corporate',  'percentage', 25, 'corporate', today(),               today()->addDays(365), 1, 0,    null, true,  false, false, true,  false, true,  'Negotiated rate for Infosys travel desk'],
            ['HONEYMOON','Honeymoon package',     'complimentary_addon', 1, 'room_types', today(),     today()->addDays(120), 2, 8000, 100, true,  true,  false, true,  true,  true,  'Includes room decoration + champagne + late checkout'],
            ['STAY3GET1','Stay 3 nights, 1 free', 'free_night',  1, 'all',       today(),               today()->addDays(90), 3, 0,    50,   true,  true,  false, false, true,  true,  '4th night complimentary on minimum 3-night stays'],
            ['FLAT1500','Flat ₹1500 off',         'flat_amount', 1500, 'all',     today(),              today()->addDays(45), 1, 7000, 200,  true,  true,  false, true,  false, true,  'Flat ₹1500 off on bookings above ₹7000'],
            ['MMT-SALE','MakeMyTrip Sale',        'percentage',  18, 'all',      today(),               today()->addDays(20), 1, 0,    null, false, true,  false, true,  true,  true,  'OTA promo via MakeMyTrip'],
            ['EXPIRED','Expired test promo',      'percentage',  10, 'all',      today()->subDays(60), today()->subDays(30), 1, 0,   null, true,  false, false, false, true,  true,  'Already expired — for filter testing'],
        ];

        $valueIdx = 0;
        foreach ($promos as $row) {
            [$code, $name, $type, $value, $applies, $from, $to, $minNights, $minAmt, $maxUses, $direct, $ota, $corp, $stack, $active, $public, $desc] = array_pad($row, 18, null);
            DB::table('promotions')->insert([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'code'=>$code,'name'=>$name,'description'=>$desc,
                'type'=>$type,'value'=>$value,'applies_to'=>$applies,
                'valid_from'=>$from,'valid_to'=>$to,
                'min_nights'=>$minNights,'min_amount'=>$minAmt,
                'max_uses'=>$maxUses,'used_count'=>$maxUses ? rand(0, max(0, intval($maxUses/2))) : rand(0, 30),
                'max_per_guest'=>$type === 'free_night' ? 1 : null,
                'advance_days'=>$code === 'EARLYBIRD30' ? 30 : 0,
                'available_direct'=>$direct,'available_ota'=>$ota,'available_corporate'=>$corp,
                'is_stackable'=>$stack,'is_active'=>$active,'is_public'=>$public,
                'created_by'=>1,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    private function seedIntegrationLogs($tenant, $property): void
    {
        $integrations = [
            ['razorpay','create_order','reservation','txn_'.uniqid()],
            ['axisrooms','push_inventory','rate_calendar',null],
            ['axisrooms','push_rates','rate_calendar',null],
            ['hyperverge','ocr_aadhaar','guest',null],
            ['aisensy','send_whatsapp','reservation','msg_'.uniqid()],
            ['onity','program_key','reservation_room',null],
            ['msg91','send_otp','user',null],
            ['tally','export_voucher','accounts_voucher',null],
        ];
        for ($i = 0; $i < 30; $i++) {
            [$int, $op, $refType, $extId] = $integrations[array_rand($integrations)];
            $success = rand(0, 19) !== 0;
            $calledAt = now()->subMinutes(rand(5, 5000));
            DB::table('integration_logs')->insert([
                'tenant_id'=>$tenant->id,'property_id'=>$property->id,
                'integration'=>$int,'operation'=>$op,
                'reference_type'=>$refType,'reference_id'=>rand(1, 100),
                'request_payload'=>json_encode(['op'=>$op]),
                'response_payload'=>json_encode(['ok'=>$success]),
                'status_code'=>$success ? 200 : collect([400,401,500,503])->random(),
                'success'=>$success,
                'error_message'=>$success ? null : 'Upstream timeout',
                'duration_ms'=>rand(120, 4500),
                'called_at'=>$calledAt,
                'created_at'=>$calledAt,'updated_at'=>$calledAt,
            ]);
        }
    }
}
