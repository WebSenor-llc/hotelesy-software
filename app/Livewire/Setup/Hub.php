<?php

namespace App\Livewire\Setup;

use App\Models\Accounts\VoucherType;
use App\Models\Banquet\BanquetHall;
use App\Models\Banquet\BanquetPackage;
use App\Models\Company;
use App\Models\KDS\KdsStation;
use App\Models\POS\MenuCategory;
use App\Models\POS\MenuItem;
use App\Models\POS\Outlet;
use App\Models\POS\PosTable;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\Store\StoreCategory;
use App\Models\Tax;
use App\Models\User;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Hub extends Component
{
    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $stats = [
            'properties'       => $ctx->tenant()?->properties()->count() ?? 0,
            'roomTypes'        => RoomType::where('property_id', $propertyId)->count(),
            'rooms'            => \App\Models\Room::where('property_id', $propertyId)->count(),
            'ratePlans'        => RatePlan::where('property_id', $propertyId)->count(),
            'users'            => User::where('tenant_id', $ctx->tenantId())->count(),
            'taxes'            => Tax::where('property_id', $propertyId)->count(),
            'posOutlets'       => Outlet::where('property_id', $propertyId)->count(),
            'menuCategories'   => MenuCategory::where('property_id', $propertyId)->count(),
            'menuItems'        => MenuItem::where('property_id', $propertyId)->count(),
            'posTables'        => PosTable::where('property_id', $propertyId)->count(),
            'kdsStations'      => KdsStation::where('property_id', $propertyId)->count(),
            'banquetHalls'     => BanquetHall::where('property_id', $propertyId)->count(),
            'banquetPackages'  => BanquetPackage::where('property_id', $propertyId)->count(),
            'companies'        => Company::count(),
            'storeCategories'  => StoreCategory::where('property_id', $propertyId)->count(),
            'voucherTypes'     => VoucherType::where('property_id', $propertyId)->count(),
        ];

        return view('livewire.setup.hub', compact('stats'));
    }
}
