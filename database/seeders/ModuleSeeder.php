<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Catalog of modules. Idempotent — re-running upserts.
     */
    public function run(): void
    {
        $modules = [
            // Core (cannot be disabled)
            ['code' => 'pms', 'name' => 'Property Management System', 'category' => 'core', 'is_core' => true, 'sort_order' => 10, 'description' => 'Reservations, check-in/out, folio, room inventory'],
            ['code' => 'users', 'name' => 'User & Role Management', 'category' => 'core', 'is_core' => true, 'sort_order' => 20],
            ['code' => 'reports', 'name' => 'Standard Reports', 'category' => 'core', 'is_core' => true, 'sort_order' => 30],

            // Operations
            ['code' => 'pos', 'name' => 'Point of Sale (POS)', 'category' => 'operations', 'is_core' => false, 'sort_order' => 100, 'available_in_plans' => ['growth', 'enterprise'], 'addon_price_inr' => 2500, 'description' => 'Restaurant, bar, room service order management'],
            ['code' => 'kds', 'name' => 'Kitchen Display System (KDS)', 'category' => 'operations', 'is_core' => false, 'sort_order' => 110, 'depends_on' => ['pos'], 'available_in_plans' => ['growth', 'enterprise'], 'addon_price_inr' => 1500, 'description' => 'Kitchen ticket management with prep timing'],
            ['code' => 'banquet', 'name' => 'Banquet & Sales/Catering', 'category' => 'operations', 'is_core' => false, 'sort_order' => 120, 'available_in_plans' => ['growth', 'enterprise'], 'addon_price_inr' => 2000, 'description' => 'Hall booking, event packages, catering'],
            ['code' => 'housekeeping', 'name' => 'Housekeeping', 'category' => 'operations', 'is_core' => false, 'sort_order' => 130, 'addon_price_inr' => 1000, 'description' => 'Room status board, task assignment, lost & found'],
            ['code' => 'store', 'name' => 'Store / Materials Management', 'category' => 'operations', 'is_core' => false, 'sort_order' => 140, 'available_in_plans' => ['enterprise'], 'addon_price_inr' => 2500, 'description' => 'Inventory, vendors, purchase orders, GRN'],
            ['code' => 'maintenance', 'name' => 'Maintenance Management', 'category' => 'operations', 'is_core' => false, 'sort_order' => 150, 'addon_price_inr' => 1000, 'description' => 'Engineering tickets, preventive maintenance'],
            ['code' => 'spa', 'name' => 'Spa & Wellness', 'category' => 'operations', 'is_core' => false, 'sort_order' => 160, 'addon_price_inr' => 2000, 'description' => 'Spa appointments, therapist scheduling'],

            // Distribution
            ['code' => 'channel_manager', 'name' => 'Channel Manager Integration', 'category' => 'distribution', 'is_core' => false, 'sort_order' => 200, 'available_in_plans' => ['growth', 'enterprise'], 'addon_price_inr' => 3000, 'description' => 'OTA inventory + rate sync via AxisRooms / STAAH / SiteMinder'],
            ['code' => 'booking_engine', 'name' => 'Direct Booking Engine', 'category' => 'distribution', 'is_core' => false, 'sort_order' => 210, 'addon_price_inr' => 2500, 'description' => 'Public direct booking website'],
            ['code' => 'cms', 'name' => 'Hotel Website (CMS)', 'category' => 'distribution', 'is_core' => false, 'sort_order' => 220, 'available_in_plans' => ['enterprise'], 'addon_price_inr' => 5000, 'description' => 'Hosted multi-page hotel website with booking engine'],
            ['code' => 'crs', 'name' => 'Central Reservation System', 'category' => 'distribution', 'is_core' => false, 'sort_order' => 230, 'available_in_plans' => ['enterprise'], 'addon_price_inr' => 7500, 'description' => 'Multi-property central CRS for chains'],

            // Finance
            ['code' => 'accounts', 'name' => 'Financial Management', 'category' => 'finance', 'is_core' => false, 'sort_order' => 300, 'available_in_plans' => ['enterprise'], 'addon_price_inr' => 4000, 'description' => 'Chart of accounts, vouchers, GST returns, bank reconciliation'],
            ['code' => 'payroll', 'name' => 'Payroll & HR', 'category' => 'finance', 'is_core' => false, 'sort_order' => 310, 'addon_price_inr' => 3000, 'description' => 'Staff records, payroll, attendance'],
            ['code' => 'fb_costing', 'name' => 'F&B Cost Control', 'category' => 'finance', 'is_core' => false, 'sort_order' => 320, 'depends_on' => ['pos', 'store'], 'addon_price_inr' => 1500],

            // Marketing / CRM
            ['code' => 'reviews', 'name' => 'Review Management', 'category' => 'marketing', 'is_core' => false, 'sort_order' => 400, 'addon_price_inr' => 2000, 'description' => 'Aggregate Google / TripAdvisor / Booking reviews; respond from one inbox'],
            ['code' => 'revenue', 'name' => 'Revenue Management', 'category' => 'marketing', 'is_core' => false, 'sort_order' => 410, 'available_in_plans' => ['enterprise'], 'addon_price_inr' => 5000, 'description' => 'Rate shopper, dynamic pricing, occupancy forecast'],
            ['code' => 'crm', 'name' => 'Guest CRM & Loyalty', 'category' => 'marketing', 'is_core' => false, 'sort_order' => 420, 'addon_price_inr' => 2500],
            ['code' => 'amenities', 'name' => 'Amenities & Add-ons', 'category' => 'marketing', 'is_core' => false, 'sort_order' => 430, 'addon_price_inr' => 1000, 'description' => 'Sell extras: airport pickup, breakfast upgrade, spa packages'],
            ['code' => 'whatsapp', 'name' => 'WhatsApp Engagement', 'category' => 'marketing', 'is_core' => false, 'sort_order' => 440, 'addon_price_inr' => 1500, 'description' => 'AiSensy / Gallabox integration for guest communication'],
            ['code' => 'membership', 'name' => 'Membership / Club', 'category' => 'marketing', 'is_core' => false, 'sort_order' => 450, 'addon_price_inr' => 2000],

            // Integrations
            ['code' => 'door_locks', 'name' => 'Door Lock Integration', 'category' => 'integrations', 'is_core' => false, 'sort_order' => 500, 'addon_price_inr' => 1500, 'description' => 'Onity, Saflok, dormakaba'],
            ['code' => 'id_scanner', 'name' => 'ID Scanner / OCR', 'category' => 'integrations', 'is_core' => false, 'sort_order' => 510, 'addon_price_inr' => 1000, 'description' => 'Aadhaar, passport scanning'],
            ['code' => 'payment_gateway', 'name' => 'Online Payment Gateway', 'category' => 'integrations', 'is_core' => false, 'sort_order' => 520, 'addon_price_inr' => 0, 'description' => 'Razorpay, Stripe, PayU'],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['code' => $module['code']],
                array_merge($module, [
                    'depends_on' => $module['depends_on'] ?? null,
                    'available_in_plans' => $module['available_in_plans'] ?? null,
                    'is_active' => true,
                ])
            );
        }

        $this->command?->info('Seeded ' . count($modules) . ' modules.');
    }
}
