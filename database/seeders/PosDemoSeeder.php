<?php

namespace Database\Seeders;

use App\Models\KDS\KdsStation;
use App\Models\POS\MenuCategory;
use App\Models\POS\MenuItem;
use App\Models\POS\Outlet;
use App\Models\POS\PosTable;
use App\Models\Property;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds POS demo data for the Miraj Lake Palace property.
 * Run after DemoSeeder.
 */
class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);

        $context->bypass(function () use ($context) {
            $property = Property::where('code', 'MLPU')->first();
            if (! $property) {
                $this->command?->warn('No demo property found; skipping POS seeding.');
                return;
            }

            $context->set($property->tenant, $property);

            // Restaurant outlet
            $restaurant = Outlet::create([
                'property_id' => $property->id,
                'code' => 'JHAROKHA',
                'name' => 'Jharokha Multi-Cuisine Restaurant',
                'type' => 'restaurant',
                'open_time' => '07:00',
                'close_time' => '23:00',
                'service_charge_percent' => 10,
                'is_active' => true,
            ]);

            // Bar
            $bar = Outlet::create([
                'property_id' => $property->id,
                'code' => 'BAR',
                'name' => 'Lake View Bar',
                'type' => 'bar',
                'open_time' => '11:00',
                'close_time' => '01:00',
                'service_charge_percent' => 10,
                'is_active' => true,
            ]);

            // Tables
            for ($i = 1; $i <= 12; $i++) {
                PosTable::create([
                    'property_id' => $property->id,
                    'outlet_id' => $restaurant->id,
                    'name' => "T{$i}",
                    'section' => $i <= 6 ? 'Indoor' : 'Lakeside',
                    'capacity' => 4,
                    'status' => 'available',
                    'is_active' => true,
                ]);
            }

            // Menu Categories
            $starters = MenuCategory::create([
                'property_id' => $property->id,
                'outlet_id' => $restaurant->id,
                'name' => 'Starters',
                'is_liquor' => false,
                'display_order' => 10,
                'is_active' => true,
            ]);
            $tandoor = MenuCategory::create([
                'property_id' => $property->id,
                'outlet_id' => $restaurant->id,
                'name' => 'Tandoor',
                'is_liquor' => false,
                'display_order' => 20,
                'is_active' => true,
            ]);
            $mains = MenuCategory::create([
                'property_id' => $property->id,
                'outlet_id' => $restaurant->id,
                'name' => 'Main Course',
                'is_liquor' => false,
                'display_order' => 30,
                'is_active' => true,
            ]);
            $beverages = MenuCategory::create([
                'property_id' => $property->id,
                'outlet_id' => $restaurant->id,
                'name' => 'Beverages',
                'is_liquor' => false,
                'display_order' => 40,
                'is_active' => true,
            ]);
            $liquor = MenuCategory::create([
                'property_id' => $property->id,
                'outlet_id' => $bar->id,
                'name' => 'Spirits',
                'is_liquor' => true,
                'display_order' => 50,
                'is_active' => true,
            ]);

            // Menu Items
            $items = [
                ['cat' => $starters, 'code' => 'PAN_TIK', 'name' => 'Paneer Tikka', 'price' => 425, 'tax' => 5, 'food_type' => 'veg'],
                ['cat' => $starters, 'code' => 'CHK_TIK', 'name' => 'Chicken Tikka', 'price' => 545, 'tax' => 5, 'food_type' => 'non_veg'],
                ['cat' => $tandoor, 'code' => 'TAN_ROT', 'name' => 'Tandoori Roti', 'price' => 65, 'tax' => 5, 'food_type' => 'veg'],
                ['cat' => $tandoor, 'code' => 'GAR_NAA', 'name' => 'Garlic Naan', 'price' => 95, 'tax' => 5, 'food_type' => 'veg'],
                ['cat' => $tandoor, 'code' => 'TAN_CHK', 'name' => 'Tandoori Chicken (Half)', 'price' => 525, 'tax' => 5, 'food_type' => 'non_veg'],
                ['cat' => $mains, 'code' => 'DAL_MAK', 'name' => 'Dal Makhani', 'price' => 395, 'tax' => 5, 'food_type' => 'veg'],
                ['cat' => $mains, 'code' => 'PAN_BUT', 'name' => 'Paneer Butter Masala', 'price' => 475, 'tax' => 5, 'food_type' => 'veg'],
                ['cat' => $mains, 'code' => 'BUT_CHK', 'name' => 'Butter Chicken', 'price' => 625, 'tax' => 5, 'food_type' => 'non_veg'],
                ['cat' => $mains, 'code' => 'LAA_GOSH', 'name' => 'Laal Maas (Rajasthani Lamb)', 'price' => 745, 'tax' => 5, 'food_type' => 'non_veg'],
                ['cat' => $beverages, 'code' => 'LASSI', 'name' => 'Sweet Lassi', 'price' => 165, 'tax' => 5, 'food_type' => 'beverage'],
                ['cat' => $beverages, 'code' => 'COKE', 'name' => 'Coca-Cola 300ml', 'price' => 145, 'tax' => 18, 'food_type' => 'beverage'],
                ['cat' => $beverages, 'code' => 'COFFEE', 'name' => 'Filter Coffee', 'price' => 195, 'tax' => 5, 'food_type' => 'beverage'],
                ['cat' => $liquor, 'code' => 'KGFCHL', 'name' => 'Kingfisher Chilled', 'price' => 425, 'tax' => 18, 'food_type' => 'liquor'],
                ['cat' => $liquor, 'code' => 'BLEND12', 'name' => 'Blenders Pride 60ml', 'price' => 795, 'tax' => 18, 'food_type' => 'liquor'],
            ];
            foreach ($items as $i) {
                MenuItem::create([
                    'property_id' => $property->id,
                    'category_id' => $i['cat']->id,
                    'code' => $i['code'],
                    'name' => $i['name'],
                    'price' => $i['price'],
                    'cost' => round($i['price'] * 0.4, 2),
                    'tax_percent' => $i['tax'],
                    'food_type' => $i['food_type'],
                    'is_combo' => false,
                    'is_taxable' => true,
                    'available' => true,
                    'is_active' => true,
                ]);
            }

            // KDS Stations
            $hotKitchen = KdsStation::create([
                'property_id' => $property->id,
                'outlet_id' => $restaurant->id,
                'code' => 'HOT',
                'name' => 'Hot Kitchen',
                'type' => 'hot_kitchen',
                'default_prep_minutes' => 18,
                'is_active' => true,
            ]);
            $tandoorStation = KdsStation::create([
                'property_id' => $property->id,
                'outlet_id' => $restaurant->id,
                'code' => 'TAND',
                'name' => 'Tandoor Station',
                'type' => 'tandoor',
                'default_prep_minutes' => 12,
                'is_active' => true,
            ]);
            $coldStation = KdsStation::create([
                'property_id' => $property->id,
                'outlet_id' => $restaurant->id,
                'code' => 'COLD',
                'name' => 'Cold / Beverage',
                'type' => 'cold_kitchen',
                'default_prep_minutes' => 5,
                'is_active' => true,
            ]);
            $barStation = KdsStation::create([
                'property_id' => $property->id,
                'outlet_id' => $bar->id,
                'code' => 'BAR',
                'name' => 'Bar',
                'type' => 'bar',
                'default_prep_minutes' => 8,
                'is_active' => true,
            ]);

            // Route categories to stations
            $tandoorStation->categories()->attach([$tandoor->id => ['priority' => 10]]);
            $hotKitchen->categories()->attach([
                $starters->id => ['priority' => 10],
                $mains->id => ['priority' => 10],
            ]);
            $coldStation->categories()->attach([$beverages->id => ['priority' => 10]]);
            $barStation->categories()->attach([$liquor->id => ['priority' => 10]]);

            // Enable POS + KDS for the demo tenant
            $modules = app(\App\Services\ModuleService::class);
            foreach (['pos', 'kds', 'channel_manager', 'reviews', 'revenue', 'amenities'] as $code) {
                try {
                    $modules->toggleForTenant($property->tenant, $code, true);
                } catch (\Throwable $e) {
                    $this->command?->warn("Could not enable module '{$code}': " . $e->getMessage());
                }
            }

            $this->command?->info('POS demo seeded: 2 outlets, 12 tables, 14 menu items, 4 KDS stations.');
        });
    }
}
