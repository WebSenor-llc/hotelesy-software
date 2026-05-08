<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * One-shot demo bootstrap. Runs (in order):
 *   1. PosMenuMegaSeeder    — 200+ menu items across all categories
 *   2. DummyDataSeeder      — guests, reservations, folios, charges, payments
 *   3. DemoDayLiveSeeder    — promotes some to checked_in / checked_out,
 *                              adds foreign nationals for Form C,
 *                              generates POS orders for last 7 days
 *
 * Usage:
 *   php artisan db:seed --class=DemoBootstrapSeeder
 *
 * Safe to re-run; each underlying seeder is idempotent or skips when full.
 */
class DemoBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('▶ Running PosMenuMegaSeeder (menu items)…');
        $this->call(PosMenuMegaSeeder::class);

        $this->command?->info('▶ Running DummyDataSeeder (reservations + folios + payments)…');
        $this->call(DummyDataSeeder::class);

        $this->command?->info('▶ Running DemoDayLiveSeeder (live state + POS orders)…');
        $this->call(DemoDayLiveSeeder::class);

        $this->command?->info('✓ Demo data ready.');
    }
}
