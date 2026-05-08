<?php

namespace Database\Seeders;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\POS\MenuItem;
use App\Models\POS\Order;
use App\Models\POS\Outlet;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo-Day Live Seeder
 *
 * Brings the property to a "live operating" state for a demo:
 *   - Promotes some confirmed reservations to checked_in (today's in-house)
 *   - Marks some past arrivals as checked_out (yesterday's history)
 *   - Adds a few foreign-national guests so Form C / FRRO has data
 *   - Generates POS orders across the last 7 days so the F&B dashboard
 *     shows real revenue, top items, and payment-mode breakdowns
 *
 * Idempotent-ish: it skips work if it sees plenty of activity already.
 *
 * Run AFTER PosMenuMegaSeeder + DummyDataSeeder so menu items and
 * reservations exist. See DemoBootstrapSeeder for the one-shot pipeline.
 *
 *   php artisan db:seed --class=DemoDayLiveSeeder
 */
class DemoDayLiveSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantContext::class)->bypass(function () {
            foreach (Property::where('status', '!=', 'archived')->get() as $property) {
                $this->command?->info("Demo-prepping {$property->name}…");
                $this->promoteToCheckedIn($property);
                $this->markPastAsCheckedOut($property);
                $this->seedForeignNationals($property);
                $this->seedPosOrders($property);
            }
        });
    }

    /** Pick some confirmed reservations whose arrival is today/past and check them in. */
    private function promoteToCheckedIn(Property $property): void
    {
        $today = Carbon::today();

        $candidates = Reservation::where('property_id', $property->id)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->where('arrival_date', '<=', $today->toDateString())
            ->where('departure_date', '>=', $today->toDateString())
            ->with(['rooms'])
            ->limit(8)
            ->get();

        $vacantRooms = Room::where('property_id', $property->id)
            ->where('is_active', true)
            ->whereIn('status', ['vacant_clean', 'inspected', 'vacant_dirty'])
            ->where('fo_status', 'vacant')
            ->get()
            ->keyBy(fn ($r) => $r->room_type_id . '_' . $r->id);

        $count = 0;
        foreach ($candidates as $r) {
            $rRoom = $r->rooms->first();
            if (!$rRoom) continue;

            // Find a vacant room of the right type
            $room = Room::where('property_id', $property->id)
                ->where('room_type_id', $rRoom->room_type_id)
                ->where('is_active', true)
                ->whereIn('status', ['vacant_clean', 'inspected', 'vacant_dirty'])
                ->where('fo_status', 'vacant')
                ->first();
            if (!$room) continue;

            DB::transaction(function () use ($r, $rRoom, $room, $property) {
                $r->update([
                    'status' => Reservation::STATUS_CHECKED_IN,
                    'status_changed_at' => now()->subHours(rand(1, 8)),
                ]);
                $rRoom->update([
                    'room_id' => $room->id,
                    'status' => 'checked_in',
                    'checked_in_at' => now()->subHours(rand(1, 8)),
                    'key_card_number' => 'KC' . rand(100, 999),
                ]);
                $room->update([
                    'status' => 'occupied_clean',
                    'fo_status' => 'occupied',
                ]);
                Folio::firstOrCreate(
                    [
                        'reservation_id' => $r->id,
                        'reservation_room_id' => $rRoom->id,
                    ],
                    [
                        'tenant_id' => $r->tenant_id,
                        'property_id' => $property->id,
                        'folio_number' => 'F-' . $r->reservation_number,
                        'type' => 'guest',
                        'guest_id' => $r->guest_id,
                        'billing_name' => $r->guest_name,
                        'total_charges' => $r->room_revenue ?? 0,
                        'total_taxes' => $r->total_tax ?? 0,
                        'total_payments' => 0,
                        'balance' => $r->total_amount ?? 0,
                        'currency' => 'INR',
                        'status' => 'open',
                    ]
                );
            });
            $count++;
            if ($count >= 8) break;
        }
        $this->command?->line("  → {$count} reservations checked in");
    }

    /** Mark a few past arrivals as checked_out so the dashboard shows history. */
    private function markPastAsCheckedOut(Property $property): void
    {
        $today = Carbon::today();

        $candidates = Reservation::where('property_id', $property->id)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
            ->where('departure_date', '<', $today->toDateString())
            ->limit(10)
            ->get();

        $count = 0;
        foreach ($candidates as $r) {
            $checkedOutAt = Carbon::parse($r->departure_date)->setTime(11, rand(0, 59));
            $r->update([
                'status' => Reservation::STATUS_CHECKED_OUT,
                'status_changed_at' => $checkedOutAt,
                'paid_amount' => $r->total_amount,
                'balance_amount' => 0,
            ]);
            $rRoom = $r->rooms()->first();
            if ($rRoom) {
                $rRoom->update(['status' => 'checked_out', 'checked_out_at' => $checkedOutAt]);
                if ($rRoom->room_id) {
                    Room::where('id', $rRoom->room_id)->update([
                        'status' => 'vacant_clean',
                        'fo_status' => 'vacant',
                    ]);
                }
            }
            $r->folios()->where('status', 'open')->update([
                'status' => 'settled',
                'total_payments' => DB::raw('total_charges + total_taxes'),
                'balance' => 0,
                'closed_at' => $checkedOutAt,
            ]);
            $count++;
        }
        $this->command?->line("  → {$count} past reservations marked checked out");
    }

    /** Add 3-5 foreign-national guests with passport+visa so Form C has data. */
    private function seedForeignNationals(Property $property): void
    {
        $foreignProfiles = [
            ['John', 'Smith', 'GB', 'A1234567', '2028-12-15', 'V/UK/2025/9821', '2025-12-15', 'United Kingdom', 'Bangkok'],
            ['Maria', 'Garcia', 'ES', 'PAB123456', '2030-03-20', 'V/ESP/2026/4412', '2026-04-30', 'Spain', 'Mumbai'],
            ['Liam', 'OConnor', 'IE', 'PA9876543', '2029-06-10', 'V/IRL/2026/0218', '2026-06-30', 'Ireland', 'Singapore'],
            ['Yuki', 'Tanaka', 'JP', 'TZ8765432', '2031-01-08', 'V/JPN/2026/7733', '2026-08-15', 'Japan', 'Kathmandu'],
            ['Anika', 'Müller', 'DE', 'C012345678', '2030-09-25', 'V/DEU/2026/5566', '2026-10-30', 'Germany', 'Colombo'],
        ];

        $reservations = Reservation::where('property_id', $property->id)
            ->whereIn('status', [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])
            ->whereDoesntHave('guest', fn ($q) => $q->where('is_foreign_national', true))
            ->limit(count($foreignProfiles))
            ->get();

        foreach ($reservations as $i => $r) {
            $p = $foreignProfiles[$i] ?? null;
            if (!$p) break;
            [$first, $last, $nat, $passport, $passExp, $visa, $visaExp, $arrFrom, $nextDest] = $p;

            $guest = Guest::firstOrCreate(
                [
                    'tenant_id' => $r->tenant_id,
                    'phone' => $r->guest_phone ?: '+' . rand(10, 90) . rand(1000000000, 9999999999),
                    'first_name' => $first,
                ],
                ['last_name' => $last, 'email' => strtolower("{$first}.{$last}@example.com")]
            );
            $guest->update([
                'is_foreign_national' => true,
                'nationality' => $nat,
                'id_type' => 'passport',
                'id_number' => $passport,
                'passport_number' => $passport,
                'passport_expiry' => $passExp,
                'visa_number' => $visa,
                'visa_expiry' => $visaExp,
                'arrival_in_india' => Carbon::parse($r->arrival_date)->subDays(rand(0, 3))->toDateString(),
                'next_destination' => $nextDest,
            ]);
            $r->update([
                'guest_id' => $guest->id,
                'guest_name' => "{$first} {$last}",
            ]);
        }
        $this->command?->line('  → ' . $reservations->count() . ' foreign-national guests seeded');
    }

    /** Generate POS orders distributed across the last 7 days so F&B has data. */
    private function seedPosOrders(Property $property): void
    {
        $outlet = Outlet::where('property_id', $property->id)->where('is_active', true)->first();
        if (!$outlet) {
            $this->command?->warn('  → No active outlet — skipping POS demo orders');
            return;
        }

        $items = MenuItem::where('property_id', $property->id)->where('available', true)->take(40)->get();
        if ($items->isEmpty()) {
            $this->command?->warn('  → No menu items — run PosMenuMegaSeeder first');
            return;
        }

        // If today already has 10+ settled orders, skip (idempotent-ish)
        $existingToday = Order::where('property_id', $property->id)
            ->whereDate('opened_at', today())
            ->count();
        if ($existingToday > 10) {
            $this->command?->line("  → POS already has {$existingToday} orders today, skipping seed");
            return;
        }

        $count = 0;
        $orderTypes = ['dine_in', 'dine_in', 'dine_in', 'takeaway', 'room_service'];
        $payModes = ['cash', 'cash', 'card', 'upi', 'upi'];
        $guestNames = ['Walk-in', 'Rajesh K', 'Priya S', 'Anil M', 'Sneha P', 'Vikram J', 'Meera D', 'Kunal R'];

        // 7 days of activity, more on recent days
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $date = today()->subDays($daysAgo);
            $ordersToday = $daysAgo === 0 ? 12 : ($daysAgo <= 2 ? 8 : 5);

            for ($i = 0; $i < $ordersToday; $i++) {
                $orderType = $orderTypes[array_rand($orderTypes)];
                $payMode = $payModes[array_rand($payModes)];
                $when = $date->copy()->setTime(rand(11, 22), rand(0, 59));

                // Pick 1-4 random items
                $selected = $items->random(rand(1, min(4, $items->count())));
                $subtotal = 0; $tax = 0;
                $orderItems = [];
                foreach ($selected as $mi) {
                    $qty = rand(1, 3);
                    $unit = (float) $mi->price;
                    $lineAmt = $qty * $unit;
                    $lineTax = round($lineAmt * (($mi->tax_percent ?? 5) / 100), 2);
                    $subtotal += $lineAmt;
                    $tax += $lineTax;
                    $orderItems[] = compact('mi', 'qty', 'unit', 'lineAmt', 'lineTax');
                }
                $sc = round($subtotal * 0.10, 2);  // 10% service charge
                $total = $subtotal + $tax + $sc;

                $orderId = DB::transaction(function () use ($property, $outlet, $orderItems, $subtotal, $tax, $sc, $total, $when, $orderType, $payMode, $guestNames) {
                    $order = Order::create([
                        'tenant_id' => $property->tenant_id,
                        'property_id' => $property->id,
                        'outlet_id' => $outlet->id,
                        'order_number' => 'ORD-' . $when->format('ymd') . '-' . str_pad((string) (Order::count() + 1), 4, '0', STR_PAD_LEFT),
                        'order_type' => $orderType,
                        'guest_name' => $orderType === 'room_service' ? 'Room ' . rand(101, 305) : $guestNames[array_rand($guestNames)],
                        'room_number' => $orderType === 'room_service' ? (string) rand(101, 305) : null,
                        'covers' => rand(1, 4),
                        'status' => Order::STATUS_SETTLED,
                        'opened_at' => $when,
                        'kot_printed_at' => $when->copy()->addMinutes(2),
                        'served_at' => $when->copy()->addMinutes(15),
                        'billed_at' => $when->copy()->addMinutes(30),
                        'settled_at' => $when->copy()->addMinutes(35),
                        'subtotal' => $subtotal,
                        'service_charge' => $sc,
                        'tax_amount' => $tax,
                        'discount_amount' => 0,
                        'round_off' => 0,
                        'total_amount' => $total,
                        'payment_timing' => 'on_bill',
                        'payment_mode' => $payMode,
                        'paid_at' => $when->copy()->addMinutes(35),
                        'created_at' => $when,
                        'updated_at' => $when->copy()->addMinutes(35),
                    ]);
                    foreach ($orderItems as $oi) {
                        DB::table('pos_order_items')->insert([
                            'tenant_id' => $property->tenant_id,
                            'property_id' => $property->id,
                            'order_id' => $order->id,
                            'menu_item_id' => $oi['mi']->id,
                            'item_name' => $oi['mi']->name,
                            'quantity' => $oi['qty'],
                            'unit_price' => $oi['unit'],
                            'amount' => $oi['lineAmt'],
                            'tax_percent' => $oi['mi']->tax_percent ?? 5,
                            'tax_amount' => $oi['lineTax'],
                            'total' => $oi['lineAmt'] + $oi['lineTax'],
                            'status' => 'sent',
                            'created_at' => $when,
                            'updated_at' => $when,
                        ]);
                    }
                    Payment::create([
                        'tenant_id' => $property->tenant_id,
                        'property_id' => $property->id,
                        'folio_id' => null,
                        'reservation_id' => null,
                        'receipt_number' => 'RCPT-' . $when->format('ymd') . '-' . str_pad((string) (Payment::count() + 1), 4, '0', STR_PAD_LEFT),
                        'payment_date' => $when->toDateString(),
                        'business_date' => $when->toDateString(),
                        'mode' => $payMode,
                        'amount' => $total,
                        'currency' => 'INR',
                        'status' => 'completed',
                        'received_by' => 1,
                        'notes' => 'Demo seed',
                        'payable_type' => Order::class,
                        'payable_id' => $order->id,
                        'created_at' => $when->copy()->addMinutes(35),
                        'updated_at' => $when->copy()->addMinutes(35),
                    ]);
                    return $order->id;
                });
                $count++;
            }
        }
        $this->command?->line("  → {$count} POS orders seeded across last 7 days");
    }
}
