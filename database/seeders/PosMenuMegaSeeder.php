<?php

namespace Database\Seeders;

use App\Models\POS\MenuCategory;
use App\Models\POS\MenuItem;
use App\Models\POS\Outlet;
use App\Models\Property;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds 200+ realistic menu items across categories for every property's
 * primary restaurant outlet. Idempotent — uses updateOrCreate keyed on
 * (property_id, code).
 *
 * Run:  php artisan db:seed --class=PosMenuMegaSeeder
 */
class PosMenuMegaSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantContext::class)->bypass(function () {
            $properties = Property::all();

            foreach ($properties as $property) {
                $outlet = Outlet::where('property_id', $property->id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first();
                if (! $outlet) {
                    $this->command?->warn("No active outlet for property #{$property->id} ({$property->name}); skipping.");
                    continue;
                }

                // Ensure all the standard categories exist for this outlet.
                // Pass tenant_id explicitly because BelongsToTenant auto-fill
                // is suppressed inside TenantContext::bypass().
                $categories = $this->ensureCategories($property->tenant_id, $property->id, $outlet->id);

                $this->seedItems($property->tenant_id, $property->id, $categories);
                $this->command?->info("Seeded menu items for {$property->name} → {$outlet->name}");
            }
        });
    }

    private function ensureCategories(int $tenantId, int $propertyId, int $outletId): array
    {
        $defaults = [
            'STARTERS'      => ['Starters & Appetizers', false, 1],
            'SOUPS'         => ['Soups',                 false, 2],
            'SALADS'        => ['Salads',                false, 3],
            'INDIAN_VEG'    => ['Indian Vegetarian',     false, 4],
            'INDIAN_NONVEG' => ['Indian Non-Vegetarian', false, 5],
            'BIRYANI'       => ['Biryani & Pulao',       false, 6],
            'BREAD'         => ['Indian Breads',         false, 7],
            'RICE'          => ['Rice & Dal',            false, 8],
            'CHINESE'       => ['Chinese',               false, 9],
            'CONTINENTAL'   => ['Continental',           false, 10],
            'PIZZA'         => ['Pizza & Pasta',         false, 11],
            'TANDOOR'       => ['Tandoor & Grills',      false, 12],
            'SOUTH_INDIAN'  => ['South Indian',          false, 13],
            'BREAKFAST'     => ['Breakfast',             false, 14],
            'DESSERTS'      => ['Desserts',              false, 15],
            'BEVERAGES'     => ['Soft Drinks & Juices',  false, 16],
            'HOT_DRINKS'    => ['Tea & Coffee',          false, 17],
            'BAR_BEER'      => ['Bar — Beer',            true,  18],
            'BAR_SPIRITS'   => ['Bar — Spirits',         true,  19],
            'BAR_WINE'      => ['Bar — Wines',           true,  20],
            'BAR_COCKTAIL'  => ['Bar — Cocktails',       true,  21],
        ];

        $byCode = [];
        foreach ($defaults as $code => [$name, $isLiquor, $order]) {
            $cat = MenuCategory::updateOrCreate(
                ['property_id' => $propertyId, 'outlet_id' => $outletId, 'code' => $code],
                [
                    'tenant_id'     => $tenantId,
                    'name'          => $name,
                    'is_liquor'     => $isLiquor,
                    'is_active'     => true,
                    'display_order' => $order,
                ]
            );
            $byCode[$code] = $cat;
        }
        return $byCode;
    }

    private function seedItems(int $tenantId, int $propertyId, array $cats): void
    {
        $items = $this->itemsCatalog();

        foreach ($items as $code => $row) {
            [$name, $catCode, $price, $cost, $taxPct, $foodType, $isCombo] = $row;
            if (! isset($cats[$catCode])) continue;

            $hsn = match (true) {
                in_array($catCode, ['BAR_BEER','BAR_SPIRITS','BAR_WINE','BAR_COCKTAIL']) => null,
                default => '996331',
            };

            MenuItem::updateOrCreate(
                ['property_id' => $propertyId, 'code' => $code],
                [
                    'tenant_id'    => $tenantId,
                    'category_id'  => $cats[$catCode]->id,
                    'name'         => $name,
                    'description'  => null,
                    'price'        => $price,
                    'cost'         => $cost,
                    'tax_percent'  => $taxPct,
                    'food_type'    => $foodType,
                    'hsn_sac_code' => $hsn,
                    'is_alcohol'   => in_array($catCode, ['BAR_BEER','BAR_SPIRITS','BAR_WINE','BAR_COCKTAIL']),
                    'is_combo'     => $isCombo,
                    'is_taxable'   => true,
                    'available'    => true,
                    'is_active'    => true,
                ]
            );
        }
    }

    /**
     * 230+ items across 21 categories. Format:
     * [code => [name, category_code, price, cost, tax_pct, food_type, is_combo]]
     */
    private function itemsCatalog(): array
    {
        return [
            // ---------- STARTERS ----------
            'PNR_TIK'   => ['Paneer Tikka', 'STARTERS', 320, 160, 5, 'veg', false],
            'PNR_KSH'   => ['Paneer Kashmiri', 'STARTERS', 350, 175, 5, 'veg', false],
            'HRA_KEB'   => ['Hara Bhara Kebab', 'STARTERS', 300, 150, 5, 'veg', false],
            'VEG_SPR'   => ['Veg Spring Rolls', 'STARTERS', 280, 130, 5, 'veg', false],
            'CRSPY_CRN' => ['Crispy Corn', 'STARTERS', 290, 130, 5, 'veg', false],
            'BABYCORN'  => ['Crispy Babycorn', 'STARTERS', 310, 140, 5, 'veg', false],
            'MUSH_MAN'  => ['Mushroom Manchurian', 'STARTERS', 320, 150, 5, 'veg', false],
            'CHL_PNR'   => ['Chilli Paneer', 'STARTERS', 350, 170, 5, 'veg', false],
            'TND_CHKN'  => ['Tandoori Chicken (half)', 'STARTERS', 480, 240, 5, 'non_veg', false],
            'TND_CHKN_F'=> ['Tandoori Chicken (full)', 'STARTERS', 850, 425, 5, 'non_veg', false],
            'CHKN_TIK'  => ['Chicken Tikka', 'STARTERS', 450, 220, 5, 'non_veg', false],
            'AFGHN_TIK' => ['Afghani Chicken Tikka', 'STARTERS', 470, 235, 5, 'non_veg', false],
            'MAL_TIK'   => ['Chicken Malai Tikka', 'STARTERS', 460, 225, 5, 'non_veg', false],
            'CHKN_LOL'  => ['Chicken Lollipop', 'STARTERS', 400, 200, 5, 'non_veg', false],
            'CHKN_65'   => ['Chicken 65', 'STARTERS', 420, 210, 5, 'non_veg', false],
            'CHILLI_CKN'=> ['Chilli Chicken', 'STARTERS', 430, 215, 5, 'non_veg', false],
            'FISH_AMT'  => ['Fish Amritsari', 'STARTERS', 480, 240, 5, 'non_veg', false],
            'FISH_FRY'  => ['Tawa Fish Fry', 'STARTERS', 520, 260, 5, 'non_veg', false],
            'PRWN_KOL'  => ['Prawns Koliwada', 'STARTERS', 580, 290, 5, 'non_veg', false],
            'KAB_GAL'   => ['Galouti Kebab', 'STARTERS', 460, 230, 5, 'non_veg', false],

            // ---------- SOUPS ----------
            'TOM_SOUP'  => ['Cream of Tomato Soup', 'SOUPS', 180, 70, 5, 'veg', false],
            'SWC_VEG'   => ['Sweet Corn Veg Soup', 'SOUPS', 190, 75, 5, 'veg', false],
            'HSR_VEG'   => ['Hot & Sour Veg Soup', 'SOUPS', 200, 80, 5, 'veg', false],
            'MAN_VEG'   => ['Manchow Veg Soup', 'SOUPS', 200, 80, 5, 'veg', false],
            'LEMON_COR' => ['Lemon Coriander Soup', 'SOUPS', 200, 75, 5, 'veg', false],
            'TALUM_SOUP'=> ['Talumein Soup', 'SOUPS', 220, 90, 5, 'veg', false],
            'SWC_CHKN'  => ['Sweet Corn Chicken Soup', 'SOUPS', 220, 95, 5, 'non_veg', false],
            'HSR_CHKN'  => ['Hot & Sour Chicken Soup', 'SOUPS', 230, 100, 5, 'non_veg', false],
            'MAN_CHKN'  => ['Manchow Chicken Soup', 'SOUPS', 230, 100, 5, 'non_veg', false],

            // ---------- SALADS ----------
            'GRN_SAL'   => ['Garden Green Salad', 'SALADS', 220, 80, 5, 'veg', false],
            'CES_SAL_V' => ['Caesar Salad (Veg)', 'SALADS', 320, 130, 5, 'veg', false],
            'CES_SAL_C' => ['Caesar Salad (Chicken)', 'SALADS', 380, 170, 5, 'non_veg', false],
            'GRK_SAL'   => ['Greek Salad', 'SALADS', 340, 140, 5, 'veg', false],
            'KCM_SAL'   => ['Kachumber Salad', 'SALADS', 180, 60, 5, 'veg', false],
            'FRT_SAL'   => ['Fresh Fruit Salad', 'SALADS', 220, 90, 5, 'veg', false],

            // ---------- INDIAN VEG ----------
            'PNR_BTM'   => ['Paneer Butter Masala', 'INDIAN_VEG', 380, 170, 5, 'veg', false],
            'KAD_PNR'   => ['Kadai Paneer', 'INDIAN_VEG', 380, 170, 5, 'veg', false],
            'PNR_LBD'   => ['Paneer Lababdar', 'INDIAN_VEG', 390, 175, 5, 'veg', false],
            'PNR_TKM'   => ['Paneer Tikka Masala', 'INDIAN_VEG', 390, 175, 5, 'veg', false],
            'PALAK_PNR' => ['Palak Paneer', 'INDIAN_VEG', 360, 160, 5, 'veg', false],
            'SHA_PNR'   => ['Shahi Paneer', 'INDIAN_VEG', 390, 180, 5, 'veg', false],
            'METHI_MAL' => ['Methi Malai Paneer', 'INDIAN_VEG', 380, 175, 5, 'veg', false],
            'DAL_MKH'   => ['Dal Makhani', 'INDIAN_VEG', 320, 130, 5, 'veg', false],
            'DAL_TDK'   => ['Dal Tadka', 'INDIAN_VEG', 280, 110, 5, 'veg', false],
            'MIX_VEG'   => ['Mix Vegetables', 'INDIAN_VEG', 320, 140, 5, 'veg', false],
            'JEERA_AL'  => ['Jeera Aloo', 'INDIAN_VEG', 280, 110, 5, 'veg', false],
            'BHN_MAS'   => ['Bhindi Masala', 'INDIAN_VEG', 300, 130, 5, 'veg', false],
            'BAIGAN_BR' => ['Baingan Bharta', 'INDIAN_VEG', 320, 140, 5, 'veg', false],
            'CHANA_MAS' => ['Chana Masala', 'INDIAN_VEG', 300, 120, 5, 'veg', false],
            'RAJMA'     => ['Rajma Masala', 'INDIAN_VEG', 300, 120, 5, 'veg', false],
            'KAJU_CRY'  => ['Kaju Curry', 'INDIAN_VEG', 420, 200, 5, 'veg', false],
            'MTR_PNR'   => ['Matar Paneer', 'INDIAN_VEG', 360, 160, 5, 'veg', false],
            'MAL_KOFT'  => ['Malai Kofta', 'INDIAN_VEG', 380, 170, 5, 'veg', false],
            'PND_MUSH'  => ['Pindi Mushroom', 'INDIAN_VEG', 360, 160, 5, 'veg', false],
            'AL_GBI'    => ['Aloo Gobi', 'INDIAN_VEG', 300, 130, 5, 'veg', false],

            // ---------- INDIAN NON-VEG ----------
            'BTR_CHKN'  => ['Butter Chicken', 'INDIAN_NONVEG', 480, 220, 5, 'non_veg', false],
            'CHKN_TKA_M'=> ['Chicken Tikka Masala', 'INDIAN_NONVEG', 470, 215, 5, 'non_veg', false],
            'KAD_CHKN'  => ['Kadai Chicken', 'INDIAN_NONVEG', 460, 210, 5, 'non_veg', false],
            'CHKN_KRI'  => ['Chicken Kali Mirch', 'INDIAN_NONVEG', 470, 215, 5, 'non_veg', false],
            'CHKN_KEEMA'=> ['Chicken Keema', 'INDIAN_NONVEG', 460, 210, 5, 'non_veg', false],
            'CHKN_MUGH' => ['Chicken Mughlai', 'INDIAN_NONVEG', 490, 230, 5, 'non_veg', false],
            'CHKN_SAG'  => ['Chicken Saag', 'INDIAN_NONVEG', 470, 215, 5, 'non_veg', false],
            'CHKN_BTR'  => ['Chicken Bhuna', 'INDIAN_NONVEG', 460, 210, 5, 'non_veg', false],
            'MUTON_RGN' => ['Mutton Rogan Josh', 'INDIAN_NONVEG', 650, 320, 5, 'non_veg', false],
            'MUTON_KORM'=> ['Mutton Korma', 'INDIAN_NONVEG', 660, 330, 5, 'non_veg', false],
            'MUTON_SAG' => ['Mutton Saagwala', 'INDIAN_NONVEG', 660, 330, 5, 'non_veg', false],
            'MUTON_KEMA'=> ['Mutton Keema Matar', 'INDIAN_NONVEG', 620, 310, 5, 'non_veg', false],
            'FISH_CRY'  => ['Fish Curry', 'INDIAN_NONVEG', 580, 290, 5, 'non_veg', false],
            'FISH_TKA'  => ['Fish Tikka Masala', 'INDIAN_NONVEG', 600, 300, 5, 'non_veg', false],
            'PRWN_MAS'  => ['Prawn Masala', 'INDIAN_NONVEG', 650, 325, 5, 'non_veg', false],
            'PRWN_BTR'  => ['Butter Prawns', 'INDIAN_NONVEG', 680, 340, 5, 'non_veg', false],
            'EGG_CRY'   => ['Egg Curry', 'INDIAN_NONVEG', 320, 130, 5, 'egg', false],
            'EGG_BHRJ'  => ['Egg Bhurji', 'INDIAN_NONVEG', 280, 110, 5, 'egg', false],
            'EGG_MASA'  => ['Egg Masala', 'INDIAN_NONVEG', 300, 120, 5, 'egg', false],

            // ---------- BIRYANI & PULAO ----------
            'VEG_BIRY'  => ['Veg Biryani', 'BIRYANI', 320, 130, 5, 'veg', false],
            'PNR_BIRY'  => ['Paneer Biryani', 'BIRYANI', 360, 160, 5, 'veg', false],
            'CHKN_BIRY' => ['Chicken Biryani', 'BIRYANI', 420, 190, 5, 'non_veg', false],
            'HYD_CHKN'  => ['Hyderabadi Chicken Biryani', 'BIRYANI', 460, 220, 5, 'non_veg', false],
            'AWADHI_CKN'=> ['Awadhi Chicken Biryani', 'BIRYANI', 470, 225, 5, 'non_veg', false],
            'MUTTON_BRY'=> ['Mutton Biryani', 'BIRYANI', 580, 280, 5, 'non_veg', false],
            'PRAWN_BRY' => ['Prawn Biryani', 'BIRYANI', 580, 290, 5, 'non_veg', false],
            'EGG_BIRY'  => ['Egg Biryani', 'BIRYANI', 320, 140, 5, 'egg', false],
            'VEG_PULA'  => ['Veg Pulao', 'BIRYANI', 280, 110, 5, 'veg', false],
            'PEAS_PULA' => ['Peas Pulao', 'BIRYANI', 260, 100, 5, 'veg', false],
            'JEERA_RICE'=> ['Jeera Rice', 'BIRYANI', 240, 90, 5, 'veg', false],
            'KASH_PULA' => ['Kashmiri Pulao', 'BIRYANI', 320, 130, 5, 'veg', false],

            // ---------- BREAD ----------
            'PLN_NAAN'  => ['Plain Naan', 'BREAD', 60, 18, 5, 'veg', false],
            'BTR_NAAN'  => ['Butter Naan', 'BREAD', 70, 22, 5, 'veg', false],
            'GRL_NAAN'  => ['Garlic Naan', 'BREAD', 90, 30, 5, 'veg', false],
            'CHEESE_NAAN'=> ['Cheese Naan', 'BREAD', 130, 50, 5, 'veg', false],
            'KEEMA_NAAN'=> ['Keema Naan', 'BREAD', 150, 70, 5, 'non_veg', false],
            'TND_ROTI'  => ['Tandoori Roti', 'BREAD', 30, 8, 5, 'veg', false],
            'BTR_ROTI'  => ['Butter Tandoori Roti', 'BREAD', 40, 12, 5, 'veg', false],
            'MISSI_ROTI'=> ['Missi Roti', 'BREAD', 50, 15, 5, 'veg', false],
            'LACHA_PRT' => ['Lachha Paratha', 'BREAD', 80, 30, 5, 'veg', false],
            'AL_PRT'    => ['Aloo Paratha', 'BREAD', 90, 30, 5, 'veg', false],
            'PNR_PRT'   => ['Paneer Paratha', 'BREAD', 110, 40, 5, 'veg', false],
            'PUDINA_PRT'=> ['Pudina Paratha', 'BREAD', 80, 28, 5, 'veg', false],
            'KULCHA_AL' => ['Aloo Kulcha', 'BREAD', 90, 30, 5, 'veg', false],
            'KULCHA_PNR'=> ['Paneer Kulcha', 'BREAD', 110, 40, 5, 'veg', false],

            // ---------- RICE ----------
            'STM_RICE'  => ['Steamed Rice', 'RICE', 180, 60, 5, 'veg', false],
            'GHEE_RICE' => ['Ghee Rice', 'RICE', 220, 80, 5, 'veg', false],
            'CRD_RICE'  => ['Curd Rice', 'RICE', 220, 80, 5, 'veg', false],
            'LMN_RICE'  => ['Lemon Rice', 'RICE', 220, 80, 5, 'veg', false],
            'TAM_RICE'  => ['Tamarind Rice', 'RICE', 220, 80, 5, 'veg', false],

            // ---------- CHINESE ----------
            'VEG_FNDR'  => ['Veg Fried Rice', 'CHINESE', 280, 110, 5, 'veg', false],
            'CHKN_FNDR' => ['Chicken Fried Rice', 'CHINESE', 320, 140, 5, 'non_veg', false],
            'PRWN_FNDR' => ['Prawn Fried Rice', 'CHINESE', 380, 180, 5, 'non_veg', false],
            'EGG_FNDR'  => ['Egg Fried Rice', 'CHINESE', 280, 110, 5, 'egg', false],
            'SCH_FNDR_V'=> ['Schezwan Fried Rice (Veg)', 'CHINESE', 320, 130, 5, 'veg', false],
            'SCH_FNDR_C'=> ['Schezwan Fried Rice (Chkn)', 'CHINESE', 360, 160, 5, 'non_veg', false],
            'SCH_FNDR_M'=> ['Schezwan Fried Rice (Mixed)', 'CHINESE', 380, 170, 5, 'non_veg', false],
            'BURNT_GAR' => ['Burnt Garlic Fried Rice', 'CHINESE', 320, 130, 5, 'veg', false],
            'TRI_FNDR_V'=> ['Triple Schezwan Rice', 'CHINESE', 360, 160, 5, 'non_veg', false],
            'NOODLES_V' => ['Hakka Noodles (Veg)', 'CHINESE', 280, 110, 5, 'veg', false],
            'NOODLES_C' => ['Hakka Noodles (Chicken)', 'CHINESE', 320, 140, 5, 'non_veg', false],
            'CHOWMEIN'  => ['Veg Chowmein', 'CHINESE', 280, 110, 5, 'veg', false],
            'AMERICAN_C'=> ['American Chopsuey', 'CHINESE', 320, 130, 5, 'veg', false],
            'CANTON_C'  => ['Cantonese Chicken', 'CHINESE', 380, 180, 5, 'non_veg', false],
            'KUNG_PAO'  => ['Kung Pao Chicken', 'CHINESE', 380, 180, 5, 'non_veg', false],
            'GINGER_CKN'=> ['Ginger Chicken', 'CHINESE', 380, 180, 5, 'non_veg', false],
            'SCH_PRWN'  => ['Schezwan Prawns', 'CHINESE', 480, 230, 5, 'non_veg', false],
            'GOBHI_MAN' => ['Gobi Manchurian', 'CHINESE', 280, 110, 5, 'veg', false],
            'PNR_MAN'   => ['Paneer Manchurian', 'CHINESE', 320, 140, 5, 'veg', false],
            'CHKN_MAN'  => ['Chicken Manchurian', 'CHINESE', 360, 170, 5, 'non_veg', false],
            'VEG_MAN'   => ['Veg Manchurian (Dry)', 'CHINESE', 280, 110, 5, 'veg', false],

            // ---------- CONTINENTAL ----------
            'GRL_CHKN'  => ['Grilled Chicken', 'CONTINENTAL', 480, 230, 5, 'non_veg', false],
            'CHKN_STK'  => ['Chicken Steak', 'CONTINENTAL', 580, 290, 5, 'non_veg', false],
            'FISH_GRL'  => ['Grilled Fish', 'CONTINENTAL', 620, 310, 5, 'non_veg', false],
            'FNG_FISH'  => ['Fish & Chips', 'CONTINENTAL', 580, 280, 5, 'non_veg', false],
            'PNR_STK'   => ['Cottage Cheese Steak', 'CONTINENTAL', 420, 200, 5, 'veg', false],
            'VEG_AGR'   => ['Vegetable au Gratin', 'CONTINENTAL', 380, 170, 5, 'veg', false],
            'BAKED_PNR' => ['Baked Paneer Florentine', 'CONTINENTAL', 420, 200, 5, 'veg', false],
            'STR_FRY_V' => ['Stir-fry Vegetables', 'CONTINENTAL', 320, 130, 5, 'veg', false],
            'CLB_SAND_V'=> ['Veg Club Sandwich', 'CONTINENTAL', 280, 110, 5, 'veg', false],
            'CLB_SAND_C'=> ['Chicken Club Sandwich', 'CONTINENTAL', 320, 140, 5, 'non_veg', false],
            'BURGER_V'  => ['Veg Burger', 'CONTINENTAL', 240, 95, 5, 'veg', false],
            'BURGER_C'  => ['Chicken Burger', 'CONTINENTAL', 280, 130, 5, 'non_veg', false],
            'WRAP_V'    => ['Veg Wrap', 'CONTINENTAL', 220, 90, 5, 'veg', false],
            'WRAP_C'    => ['Chicken Wrap', 'CONTINENTAL', 260, 110, 5, 'non_veg', false],

            // ---------- PIZZA & PASTA ----------
            'PIZ_MARGRT'=> ['Margherita Pizza', 'PIZZA', 320, 130, 5, 'veg', false],
            'PIZ_FARMR' => ['Farmhouse Pizza', 'PIZZA', 380, 160, 5, 'veg', false],
            'PIZ_VDLX'  => ['Veg Deluxe Pizza', 'PIZZA', 380, 160, 5, 'veg', false],
            'PIZ_MEXICA'=> ['Mexican Pizza', 'PIZZA', 380, 160, 5, 'veg', false],
            'PIZ_PEPER' => ['Pepperoni Pizza', 'PIZZA', 480, 220, 5, 'non_veg', false],
            'PIZ_CHKBBQ'=> ['Chicken BBQ Pizza', 'PIZZA', 480, 220, 5, 'non_veg', false],
            'PIZ_TANCK' => ['Tandoori Chicken Pizza', 'PIZZA', 480, 220, 5, 'non_veg', false],
            'PAST_AGLIO'=> ['Pasta Aglio Olio', 'PIZZA', 360, 150, 5, 'veg', false],
            'PAST_ARRBT'=> ['Pasta Arrabiata', 'PIZZA', 380, 160, 5, 'veg', false],
            'PAST_ALFRD'=> ['Pasta Alfredo', 'PIZZA', 420, 180, 5, 'veg', false],
            'PAST_PESTO'=> ['Pasta Pesto', 'PIZZA', 420, 180, 5, 'veg', false],
            'PAST_BLGNS'=> ['Pasta Bolognese', 'PIZZA', 460, 210, 5, 'non_veg', false],
            'LASGN_VEG' => ['Vegetable Lasagne', 'PIZZA', 460, 210, 5, 'veg', false],

            // ---------- TANDOOR & GRILLS ----------
            'TND_PLATTR'=> ['Tandoori Platter (Veg)', 'TANDOOR', 580, 250, 5, 'veg', true],
            'TND_PLAT_C'=> ['Tandoori Platter (Non-veg)', 'TANDOOR', 780, 360, 5, 'non_veg', true],
            'KAB_SHK'   => ['Sheekh Kebab', 'TANDOOR', 460, 220, 5, 'non_veg', false],
            'KAB_SHKVEG'=> ['Veg Sheekh Kebab', 'TANDOOR', 360, 160, 5, 'veg', false],
            'KAB_RESH'  => ['Reshmi Kebab', 'TANDOOR', 480, 230, 5, 'non_veg', false],
            'KAB_HRBR'  => ['Hara Bhara Kebab (Tandoor)', 'TANDOOR', 320, 140, 5, 'veg', false],

            // ---------- SOUTH INDIAN ----------
            'PLN_DOSA'  => ['Plain Dosa', 'SOUTH_INDIAN', 180, 50, 5, 'veg', false],
            'MAS_DOSA'  => ['Masala Dosa', 'SOUTH_INDIAN', 220, 70, 5, 'veg', false],
            'PNR_DOSA'  => ['Paneer Dosa', 'SOUTH_INDIAN', 260, 100, 5, 'veg', false],
            'CHEESE_DOS'=> ['Cheese Dosa', 'SOUTH_INDIAN', 260, 100, 5, 'veg', false],
            'GHEE_RST'  => ['Ghee Roast Dosa', 'SOUTH_INDIAN', 240, 80, 5, 'veg', false],
            'IDLI_VAD'  => ['Idli Vada Combo', 'SOUTH_INDIAN', 200, 70, 5, 'veg', true],
            'UTHAPM'    => ['Onion Uthapam', 'SOUTH_INDIAN', 200, 70, 5, 'veg', false],
            'MED_VAD'   => ['Medu Vada', 'SOUTH_INDIAN', 180, 50, 5, 'veg', false],
            'POOR_BAJI' => ['Poori Bhaji', 'SOUTH_INDIAN', 200, 70, 5, 'veg', false],
            'IDIYAPPM'  => ['Idiyappam', 'SOUTH_INDIAN', 200, 70, 5, 'veg', false],

            // ---------- BREAKFAST ----------
            'AME_BRK'   => ['American Breakfast', 'BREAKFAST', 580, 220, 5, 'egg', true],
            'CONT_BRK'  => ['Continental Breakfast', 'BREAKFAST', 460, 180, 5, 'veg', true],
            'IND_BRK'   => ['Indian Breakfast Thali', 'BREAKFAST', 420, 160, 5, 'veg', true],
            'EGG_BENED' => ['Eggs Benedict', 'BREAKFAST', 380, 170, 5, 'egg', false],
            'OMLT'      => ['Plain Omelette', 'BREAKFAST', 180, 60, 5, 'egg', false],
            'OMLT_CHEES'=> ['Cheese Omelette', 'BREAKFAST', 240, 90, 5, 'egg', false],
            'OMLT_MASA' => ['Masala Omelette', 'BREAKFAST', 220, 80, 5, 'egg', false],
            'SCRAMBLE'  => ['Scrambled Eggs on Toast', 'BREAKFAST', 220, 80, 5, 'egg', false],
            'PANCAKE'   => ['Pancakes with Maple Syrup', 'BREAKFAST', 280, 110, 5, 'veg', false],
            'WAFFLES'   => ['Belgian Waffles', 'BREAKFAST', 320, 130, 5, 'veg', false],
            'PARATHA_BR'=> ['Paratha with Curd', 'BREAKFAST', 220, 80, 5, 'veg', false],
            'PUFF_PAV'  => ['Pav Bhaji', 'BREAKFAST', 240, 100, 5, 'veg', false],

            // ---------- DESSERTS ----------
            'GULB_JAM'  => ['Gulab Jamun (2 pc)', 'DESSERTS', 180, 70, 5, 'veg', false],
            'RAS_MAL'   => ['Rasmalai (2 pc)', 'DESSERTS', 220, 90, 5, 'veg', false],
            'KULFI'     => ['Kulfi Falooda', 'DESSERTS', 220, 80, 5, 'veg', false],
            'JALEBI_RAB'=> ['Jalebi Rabri', 'DESSERTS', 240, 100, 5, 'veg', false],
            'GAJAR_HALW'=> ['Gajar Halwa', 'DESSERTS', 220, 90, 5, 'veg', false],
            'KHEER'     => ['Rice Kheer', 'DESSERTS', 200, 80, 5, 'veg', false],
            'PHIRNI'    => ['Phirni', 'DESSERTS', 220, 90, 5, 'veg', false],
            'ICE_VAN'   => ['Vanilla Ice Cream', 'DESSERTS', 160, 50, 5, 'veg', false],
            'ICE_CHC'   => ['Chocolate Ice Cream', 'DESSERTS', 160, 50, 5, 'veg', false],
            'ICE_STR'   => ['Strawberry Ice Cream', 'DESSERTS', 160, 50, 5, 'veg', false],
            'ICE_PIST'  => ['Pistachio Kulfi Ice Cream', 'DESSERTS', 220, 90, 5, 'veg', false],
            'BR_PUDDING'=> ['Bread & Butter Pudding', 'DESSERTS', 240, 100, 5, 'veg', false],
            'TIRAMISU'  => ['Tiramisu', 'DESSERTS', 320, 140, 5, 'veg', false],
            'CHC_BROWNI'=> ['Chocolate Brownie with Ice Cream', 'DESSERTS', 280, 120, 5, 'veg', false],
            'CHEESE_CK' => ['New York Cheesecake', 'DESSERTS', 320, 140, 5, 'veg', false],

            // ---------- BEVERAGES ----------
            'COKE'      => ['Coca Cola (300ml)', 'BEVERAGES', 80, 30, 18, 'beverage', false],
            'PEPSI'     => ['Pepsi (300ml)', 'BEVERAGES', 80, 30, 18, 'beverage', false],
            'SPRITE'    => ['Sprite (300ml)', 'BEVERAGES', 80, 30, 18, 'beverage', false],
            'FANTA'     => ['Fanta (300ml)', 'BEVERAGES', 80, 30, 18, 'beverage', false],
            'DIET_COKE' => ['Diet Coke (300ml)', 'BEVERAGES', 90, 35, 18, 'beverage', false],
            'BISLERI'   => ['Mineral Water (1L)', 'BEVERAGES', 60, 18, 18, 'beverage', false],
            'BISLERI_S' => ['Mineral Water (500ml)', 'BEVERAGES', 40, 12, 18, 'beverage', false],
            'PERRIER'   => ['Perrier Sparkling Water', 'BEVERAGES', 280, 130, 18, 'beverage', false],
            'JUICE_FRSH'=> ['Fresh Lime Soda', 'BEVERAGES', 120, 40, 18, 'beverage', false],
            'JUICE_ORG' => ['Orange Juice (Fresh)', 'BEVERAGES', 180, 60, 18, 'beverage', false],
            'JUICE_PNAP'=> ['Pineapple Juice', 'BEVERAGES', 180, 60, 18, 'beverage', false],
            'JUICE_WTRM'=> ['Watermelon Juice', 'BEVERAGES', 180, 60, 18, 'beverage', false],
            'JUICE_MIX' => ['Mixed Fruit Juice', 'BEVERAGES', 220, 80, 18, 'beverage', false],
            'LASSI_SWT' => ['Sweet Lassi', 'BEVERAGES', 140, 50, 5, 'beverage', false],
            'LASSI_SLT' => ['Salted Lassi', 'BEVERAGES', 140, 50, 5, 'beverage', false],
            'LASSI_MNG' => ['Mango Lassi', 'BEVERAGES', 180, 70, 5, 'beverage', false],
            'BUTTERMILK'=> ['Buttermilk', 'BEVERAGES', 100, 35, 5, 'beverage', false],
            'COLD_COFEE'=> ['Cold Coffee', 'BEVERAGES', 220, 80, 18, 'beverage', false],
            'CHC_SHK'   => ['Chocolate Milkshake', 'BEVERAGES', 240, 90, 18, 'beverage', false],
            'STR_SHK'   => ['Strawberry Milkshake', 'BEVERAGES', 240, 90, 18, 'beverage', false],
            'BAN_SHK'   => ['Banana Milkshake', 'BEVERAGES', 220, 80, 18, 'beverage', false],
            'OREO_SHK'  => ['Oreo Milkshake', 'BEVERAGES', 280, 120, 18, 'beverage', false],

            // ---------- HOT DRINKS ----------
            'TEA_MASA'  => ['Masala Chai', 'HOT_DRINKS', 80, 25, 5, 'beverage', false],
            'TEA_GRN'   => ['Green Tea', 'HOT_DRINKS', 100, 30, 5, 'beverage', false],
            'TEA_LMN'   => ['Lemon Tea', 'HOT_DRINKS', 90, 28, 5, 'beverage', false],
            'TEA_ENG'   => ['English Breakfast Tea', 'HOT_DRINKS', 120, 40, 5, 'beverage', false],
            'TEA_DRJL'  => ['Darjeeling Tea', 'HOT_DRINKS', 120, 40, 5, 'beverage', false],
            'COFFEE_BLK'=> ['Black Coffee', 'HOT_DRINKS', 100, 30, 5, 'beverage', false],
            'COFFEE_FLT'=> ['Filter Coffee', 'HOT_DRINKS', 100, 30, 5, 'beverage', false],
            'COFFEE_CAP'=> ['Cappuccino', 'HOT_DRINKS', 180, 60, 18, 'beverage', false],
            'COFFEE_LAT'=> ['Cafe Latte', 'HOT_DRINKS', 200, 70, 18, 'beverage', false],
            'COFFEE_AME'=> ['Americano', 'HOT_DRINKS', 180, 60, 18, 'beverage', false],
            'COFFEE_ESP'=> ['Espresso', 'HOT_DRINKS', 160, 50, 18, 'beverage', false],
            'COFFEE_MOC'=> ['Cafe Mocha', 'HOT_DRINKS', 220, 80, 18, 'beverage', false],
            'HOT_CHC'   => ['Hot Chocolate', 'HOT_DRINKS', 220, 80, 18, 'beverage', false],

            // ---------- BAR — BEER ----------
            'KING_SR'   => ['Kingfisher Strong (650ml)', 'BAR_BEER', 380, 180, 0, 'liquor', false],
            'KING_PRMM' => ['Kingfisher Premium (650ml)', 'BAR_BEER', 360, 170, 0, 'liquor', false],
            'CORONA'    => ['Corona Extra (330ml)', 'BAR_BEER', 480, 240, 0, 'liquor', false],
            'BUDLT'     => ['Bud Light (330ml)', 'BAR_BEER', 420, 200, 0, 'liquor', false],
            'HEINEKEN'  => ['Heineken (330ml)', 'BAR_BEER', 460, 220, 0, 'liquor', false],
            'TUBORG'    => ['Tuborg Strong (650ml)', 'BAR_BEER', 380, 180, 0, 'liquor', false],
            'BIRA_WHT'  => ['Bira 91 White (330ml)', 'BAR_BEER', 320, 150, 0, 'liquor', false],
            'BIRA_BLN'  => ['Bira 91 Blonde (330ml)', 'BAR_BEER', 320, 150, 0, 'liquor', false],

            // ---------- BAR — SPIRITS ----------
            'SMIRNOFF'  => ['Smirnoff Vodka 60ml', 'BAR_SPIRITS', 380, 180, 0, 'liquor', false],
            'GREYGOOSE' => ['Grey Goose Vodka 60ml', 'BAR_SPIRITS', 1100, 550, 0, 'liquor', false],
            'BACARDI'   => ['Bacardi White Rum 60ml', 'BAR_SPIRITS', 380, 180, 0, 'liquor', false],
            'OLDMNK'    => ['Old Monk Rum 60ml', 'BAR_SPIRITS', 280, 130, 0, 'liquor', false],
            'GOR_GIN'   => ['Gordon\'s Gin 60ml', 'BAR_SPIRITS', 480, 220, 0, 'liquor', false],
            'BOMBAY_SAP'=> ['Bombay Sapphire Gin 60ml', 'BAR_SPIRITS', 580, 270, 0, 'liquor', false],
            'JD_WHISKY' => ['Jack Daniel\'s 60ml', 'BAR_SPIRITS', 980, 480, 0, 'liquor', false],
            'CHIVAS_REG'=> ['Chivas Regal 12yr 60ml', 'BAR_SPIRITS', 980, 480, 0, 'liquor', false],
            'BL_LBL'    => ['Black Label 60ml', 'BAR_SPIRITS', 1200, 600, 0, 'liquor', false],
            'GLEN_FID'  => ['Glenfiddich 12yr 60ml', 'BAR_SPIRITS', 1450, 720, 0, 'liquor', false],
            'TEACHERS'  => ['Teacher\'s Highland Cream 60ml', 'BAR_SPIRITS', 480, 220, 0, 'liquor', false],
            'BLENDR_PRD'=> ['Blender\'s Pride 60ml', 'BAR_SPIRITS', 380, 170, 0, 'liquor', false],
            'ROYL_STG'  => ['Royal Stag 60ml', 'BAR_SPIRITS', 320, 150, 0, 'liquor', false],

            // ---------- BAR — WINE ----------
            'SULA_RED'  => ['Sula Cabernet Shiraz (Glass)', 'BAR_WINE', 380, 180, 0, 'liquor', false],
            'SULA_WHT'  => ['Sula Sauvignon Blanc (Glass)', 'BAR_WINE', 380, 180, 0, 'liquor', false],
            'SULA_RED_B'=> ['Sula Cabernet Shiraz (Bottle 750ml)', 'BAR_WINE', 1850, 920, 0, 'liquor', false],
            'JACOBS_RED'=> ['Jacob\'s Creek Shiraz (Glass)', 'BAR_WINE', 580, 280, 0, 'liquor', false],
            'GROVER_LRZ'=> ['Grover La Reserve (Bottle)', 'BAR_WINE', 2200, 1100, 0, 'liquor', false],
            'CHANDON_BRT'=> ['Chandon Brut (Glass)', 'BAR_WINE', 680, 320, 0, 'liquor', false],
            'CHANDON_BRTB'=> ['Chandon Brut (Bottle)', 'BAR_WINE', 3200, 1600, 0, 'liquor', false],

            // ---------- BAR — COCKTAILS ----------
            'COCK_MOJO'   => ['Mojito', 'BAR_COCKTAIL', 480, 220, 0, 'liquor', false],
            'COCK_MARG'   => ['Margarita', 'BAR_COCKTAIL', 520, 250, 0, 'liquor', false],
            'COCK_LIIT'   => ['Long Island Iced Tea', 'BAR_COCKTAIL', 580, 280, 0, 'liquor', false],
            'COCK_PIN_C'  => ['Piña Colada', 'BAR_COCKTAIL', 520, 250, 0, 'liquor', false],
            'COCK_BLM'    => ['Bloody Mary', 'BAR_COCKTAIL', 480, 220, 0, 'liquor', false],
            'COCK_COSMO'  => ['Cosmopolitan', 'BAR_COCKTAIL', 520, 250, 0, 'liquor', false],
            'COCK_OLDFAS' => ['Old Fashioned', 'BAR_COCKTAIL', 580, 280, 0, 'liquor', false],
            'COCK_NEGRN'  => ['Negroni', 'BAR_COCKTAIL', 580, 280, 0, 'liquor', false],
            'COCK_WHISKY_S'=> ['Whisky Sour', 'BAR_COCKTAIL', 520, 250, 0, 'liquor', false],
            'MOCK_VRG_M'  => ['Virgin Mojito', 'BAR_COCKTAIL', 280, 110, 18, 'beverage', false],
            'MOCK_VRG_PC' => ['Virgin Piña Colada', 'BAR_COCKTAIL', 280, 110, 18, 'beverage', false],
            'MOCK_BLU_LG' => ['Blue Lagoon Mocktail', 'BAR_COCKTAIL', 280, 110, 18, 'beverage', false],
            'MOCK_FRT_PNCH'=> ['Fruit Punch Mocktail', 'BAR_COCKTAIL', 240, 90, 18, 'beverage', false],
        ];
    }
}
