<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LicensingSeeder::class,     // Subscription plans + super admin — run first
            ModuleSeeder::class,        // Catalog of modules — must run before tenants
            IndiaTaxRulesSeeder::class, // GST rule matrix (room/F&B/banquet/services/liquor/tobacco)
            DemoSeeder::class,          // Demo tenant + property + rooms + auth users
            DummyDataSeeder::class,     // Comprehensive dummy data + auto-issue active license for demo tenant
        ]);
    }
}
