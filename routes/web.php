<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\PropertyController;
use App\Livewire\Accounts\Hub as AccountsHub;
use App\Livewire\Auth\LicenseExpired;
use App\Livewire\Auth\Register;
use App\Livewire\Amenities\AmenitiesHub;
use App\Livewire\Banquet\Calendar as BanquetCalendar;
use App\Livewire\CRM\GuestDetail;
use App\Livewire\CRM\GuestList;
use App\Livewire\Compliance\EInvoices as ComplianceEInvoices;
use App\Livewire\Compliance\FormC as ComplianceFormC;
use App\Livewire\Compliance\FormCPrint as ComplianceFormCPrint;
use App\Livewire\Compliance\PoliceRegister as CompliancePoliceRegister;
use App\Livewire\Compliance\TdsReport as ComplianceTdsReport;
use App\Livewire\Channel\Manager as ChannelManager;
use App\Livewire\Dashboard\Overview;
use App\Livewire\FrontOffice\CashierShift;
use App\Livewire\FrontOffice\CheckIn;
use App\Livewire\FrontOffice\CheckOut;
use App\Livewire\FrontOffice\FolioView;
use App\Livewire\FrontOffice\MovementLists;
use App\Livewire\FrontOffice\WalkInCheckIn;
use App\Livewire\Housekeeping\RoomBoard as HousekeepingBoard;
use App\Livewire\Integrations\Status as IntegrationsStatus;
use App\Livewire\KDS\StationView as KdsStationView;
use App\Livewire\Operations\NightAudit;
use App\Livewire\POS\FbManagerDashboard;
use App\Livewire\POS\PosDashboard;
use App\Livewire\Rates\RateCalendar;
use App\Livewire\Reports\DailyFlash;
use App\Livewire\Reports\GstManagement;
use App\Livewire\Reports\Overview as ReportsOverview;
use App\Livewire\Reports\ReservationStatusReport;
use App\Livewire\Reports\RevenueReport;
use App\Livewire\Reports\TaxReport;
use App\Livewire\Reservations\AvailabilityChart;
use App\Livewire\Reservations\NewBooking;
use App\Livewire\Reservations\ReservationDetail;
use App\Livewire\Reservations\ReservationList;
use App\Livewire\Reservations\TapeChart;
use App\Livewire\Revenue\Hub as RevenueHub;
use App\Livewire\Reviews\Inbox as ReviewsInbox;
use App\Livewire\Promotions\PromotionsList;
use App\Livewire\Setup\BanquetHalls as SetupBanquetHalls;
use App\Livewire\Setup\BanquetPackages as SetupBanquetPackages;
use App\Livewire\Setup\Hub as SetupHub;
use App\Livewire\Setup\KdsStations as SetupKdsStations;
use App\Livewire\Setup\MenuCategories as SetupMenuCategories;
use App\Livewire\Setup\MenuItems as SetupMenuItems;
use App\Livewire\Setup\PosOutlets as SetupPosOutlets;
use App\Livewire\Setup\PosTables as SetupPosTables;
use App\Livewire\Setup\PropertiesList;
use App\Livewire\Setup\PropertySettings;
use App\Livewire\Setup\SiteCms;
use App\Livewire\Setup\RatePlans;
use App\Livewire\Setup\RoomTypes;
use App\Livewire\Setup\Rooms as SetupRooms;
use App\Livewire\Setup\Taxes;
use App\Livewire\Setup\Users as SetupUsers;
use App\Livewire\Setup\VoucherTypes as SetupVoucherTypes;
use App\Livewire\CRM\Companies as CRMCompanies;
use App\Livewire\Store\Categories as StoreCategories;
use App\Livewire\Store\Dashboard as StoreDashboard;
use Illuminate\Support\Facades\Route;

// ============================================================
// PUBLIC HOTEL WEBSITE — subdomain routing
// awesomeberg.hotelesy.test → that tenant's public hotel site
// On localhost (127.0.0.1) we fall back to /h/{slug}/... below.
// ============================================================
//
// Registers the subdomain group for EVERY plausible central domain
// (env CENTRAL_DOMAIN + APP_URL host + sensible fallbacks) so the
// routes match even if env is stale or the user runs on a non-default
// host. Route names get a numeric suffix to avoid collisions; we keep
// the main names ("hotel.home", "hotel.rooms" etc.) bound to the first
// (env-preferred) registration.
$centralDomains = collect([
    trim((string) env('CENTRAL_DOMAIN', '')),
    parse_url((string) env('APP_URL', ''), PHP_URL_HOST),
    'hotelesy.test',
    'hotelesy.com',
])->filter()->unique()->values()->all();

foreach ($centralDomains as $idx => $centralDomain) {
    $suffix = $idx === 0 ? '' : '.' . $idx;   // first one keeps the canonical names
    Route::domain('{tenant_slug}.' . $centralDomain)->group(function () use ($suffix) {
        Route::get('/',          \App\Livewire\HotelSite\Home::class)->name('hotel.home' . $suffix);
        Route::get('/rooms',     \App\Livewire\HotelSite\RoomsList::class)->name('hotel.rooms' . $suffix);
        Route::get('/rooms/{roomTypeCode}', \App\Livewire\HotelSite\RoomDetail::class)->name('hotel.room.show' . $suffix);
        Route::get('/gallery',   \App\Livewire\HotelSite\Gallery::class)->name('hotel.gallery' . $suffix);
        Route::get('/contact',   \App\Livewire\HotelSite\Contact::class)->name('hotel.contact' . $suffix);
        Route::get('/about',     \App\Livewire\HotelSite\About::class)->name('hotel.about' . $suffix);
        Route::get('/book',      \App\Livewire\HotelSite\BookingFlow::class)->name('hotel.book' . $suffix);
    });
}

// Local-dev fallback: works without DNS via /h/{slug}/...
Route::prefix('h/{tenant_slug}')->group(function () {
    Route::get('/',          \App\Livewire\HotelSite\Home::class)->name('hotel.home.dev');
    Route::get('/rooms',     \App\Livewire\HotelSite\RoomsList::class)->name('hotel.rooms.dev');
    Route::get('/rooms/{roomTypeCode}', \App\Livewire\HotelSite\RoomDetail::class)->name('hotel.room.show.dev');
    Route::get('/gallery',   \App\Livewire\HotelSite\Gallery::class)->name('hotel.gallery.dev');
    Route::get('/contact',   \App\Livewire\HotelSite\Contact::class)->name('hotel.contact.dev');
    Route::get('/about',     \App\Livewire\HotelSite\About::class)->name('hotel.about.dev');
    Route::get('/book',      \App\Livewire\HotelSite\BookingFlow::class)->name('hotel.book.dev');
});

// Public SaaS marketing routes — only on the central domain.
Route::get('/',           \App\Livewire\Marketing\LandingPage::class)->name('home');
Route::get('/trial',      \App\Livewire\Marketing\TrialSignup::class)->name('trial.start');
Route::get('/checkout',   \App\Livewire\Marketing\Checkout::class)->name('checkout');

// Razorpay webhook — public POST, signature-verified.
Route::post('/webhooks/razorpay', [\App\Http\Controllers\RazorpayWebhookController::class, 'handle'])
    ->name('webhooks.razorpay');

// Logged-in landing → dashboard / super dashboard.
Route::get('/app', function () {
    if (! auth()->check()) {
        return redirect()->route('home');
    }
    return redirect()->route(auth()->user()->is_super_admin ? 'super.dashboard' : 'dashboard');
})->name('app.home');
Route::get('/health', fn () => response()->json(['app'=>config('app.name'),'time'=>now()->toIso8601String(),'status'=>'ok']))->name('health');

Route::middleware('guest')->group(function () {
    // Primary hotelier login URL
    Route::get('hotelier/login',  [LoginController::class, 'show'])->name('login');
    Route::post('hotelier/login', [LoginController::class, 'login']);
    Route::get('hotelier/register', Register::class)->name('register');

    // Legacy aliases — keep working for old bookmarks AND any cached form actions
    Route::get('login',   fn () => redirect()->route('login'));
    Route::post('login',  [LoginController::class, 'login']);   // ← form alias
    Route::get('register', fn () => redirect()->route('register'));
});
Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Super admin console — auth only, no tenant/property/license middleware.
Route::middleware(['auth'])->prefix('super')->name('super.')->group(function () {
    Route::get('/',                 \App\Livewire\Super\Dashboard::class)->name('dashboard');
    Route::get('/tenants',          \App\Livewire\Super\TenantsList::class)->name('tenants');
    Route::get('/tenants/{tenant}', \App\Livewire\Super\TenantDetail::class)->name('tenants.show');
    Route::get('/licenses',         \App\Livewire\Super\LicensesList::class)->name('licenses');
    Route::get('/plans',            \App\Livewire\Super\PlansList::class)->name('plans');
    Route::get('/leads',            \App\Livewire\Super\LeadsList::class)->name('leads');
    Route::get('/revenue',          \App\Livewire\Super\RevenueReport::class)->name('revenue');
});

// License lockout pages — auth required but NO tenant/license enforcement.
Route::middleware(['auth'])->group(function () {
    Route::get('license/expired', LicenseExpired::class)->name('license.expired');
    Route::get('license/locked',  LicenseExpired::class)->name('license.locked');
});

// Tax invoice access — auth + tenant only (no full RBAC since invoices need to be viewable by guests/billing roles).
Route::middleware(['auth','tenant','license'])->group(function () {
    Route::get('/invoices',                    \App\Livewire\Invoices\InvoicesList::class)->name('invoices.index');
    Route::get('/invoices/{id}',               [\App\Http\Controllers\TaxInvoiceController::class, 'show'])->where('id','[0-9]+')->name('invoice.show');
    Route::get('/invoices/{id}/pdf',           [\App\Http\Controllers\TaxInvoiceController::class, 'pdf'])->where('id','[0-9]+')->name('invoice.pdf');
    Route::post('/folios/{folio}/build-invoice',[\App\Http\Controllers\TaxInvoiceController::class, 'buildFromFolio'])->where('folio','[0-9]+')->name('folio.build-invoice');
});

Route::middleware(['auth', 'tenant', 'license', 'rbac'])->group(function () {

    Route::get('property/select',  [PropertyController::class, 'select'])->name('property.select');
    Route::post('property/switch', [PropertyController::class, 'switch'])->name('property.switch');

    // Tenant-scoped (no specific property required) — managing all properties
    Route::get('setup/properties', PropertiesList::class)->name('setup.properties');

    Route::middleware('property')->group(function () {

        Route::get('dashboard', Overview::class)->name('dashboard');

        // ----- Reservations
        Route::get('reservations',                ReservationList::class)->name('reservations.index');
        Route::get('reservations/new',            NewBooking::class)->name('reservations.new');
        Route::get('reservations/group-new',      \App\Livewire\Reservations\GroupBooking::class)->name('reservations.group');
        Route::get('reservations/tape-chart',     TapeChart::class)->name('reservations.tape');
        Route::get('reservations/availability',   AvailabilityChart::class)->name('availability');
        Route::get('reservations/{reservation}',  ReservationDetail::class)->where('reservation', '[0-9]+')->name('reservations.show');

        // ----- Front Office
        Route::get('front-office/movement',   MovementLists::class)->name('frontoffice.movement');
        Route::get('front-office/walk-in',    WalkInCheckIn::class)->name('frontoffice.walkin');
        Route::get('front-office/check-in',   CheckIn::class)->name('frontoffice.checkin');
        Route::get('front-office/check-out',  CheckOut::class)->name('frontoffice.checkout');
        Route::get('front-office/cashier',    CashierShift::class)->name('frontoffice.cashier');
        Route::get('folios',                  \App\Livewire\FrontOffice\FolioList::class)->name('folio.index');
        Route::get('folio/{folio}',           FolioView::class)->where('folio','[0-9]+')->name('folio.show');
        // Friendly redirect: /folio (no ID) → folio list
        Route::get('folio', fn () => redirect()->route('folio.index'));

        // ----- Operations
        Route::get('housekeeping', HousekeepingBoard::class)->name('housekeeping.index');
        Route::get('pos',                 PosDashboard::class)->name('pos.index');
        Route::get('pos/fb-manager',      FbManagerDashboard::class)->name('pos.fb-manager');
        Route::get('kds',          KdsStationView::class)->name('kds.index');
        Route::get('amenities',    AmenitiesHub::class)->name('amenities.index');
        Route::get('banquet',      BanquetCalendar::class)->name('banquet.index');
        Route::get('store',        StoreDashboard::class)->name('store.index');
        Route::get('night-audit',  NightAudit::class)->name('night-audit');

        // ----- Distribution & Revenue
        Route::get('rates',           RateCalendar::class)->name('rates.calendar');
        Route::get('channel-manager', ChannelManager::class)->name('channel.index');
        Route::get('revenue',         RevenueHub::class)->name('revenue.index');

        // ----- CRM & Reports
        Route::get('crm/guests',           GuestList::class)->name('crm.guests');
        Route::get('crm/guests/{guest}',   GuestDetail::class)->name('crm.guest.show');
        Route::get('crm/companies',        CRMCompanies::class)->name('crm.companies');
        Route::get('reviews',              ReviewsInbox::class)->name('reviews.index');
        Route::get('reports',              ReportsOverview::class)->name('reports.index');
        Route::get('reports/daily-flash',  DailyFlash::class)->name('reports.flash');
        Route::get('reports/tax',          TaxReport::class)->name('reports.tax');
        Route::get('reports/gst',          GstManagement::class)->name('reports.gst');
        Route::get('reports/revenue',      RevenueReport::class)->name('reports.revenue');
        Route::get('reports/reservations', ReservationStatusReport::class)->name('reports.reservations');

        // ----- Accounts
        Route::get('accounts', AccountsHub::class)->name('accounts.index');

        // ----- Setup
        Route::get('setup',                  SetupHub::class)->name('setup.hub');
        Route::get('setup/property',         PropertySettings::class)->name('setup.property');
        Route::get('setup/site-cms',         SiteCms::class)->name('setup.site-cms');
        Route::get('setup/room-types',       RoomTypes::class)->name('setup.room-types');
        Route::get('setup/rooms',            SetupRooms::class)->name('setup.rooms');
        Route::get('setup/rate-plans',       RatePlans::class)->name('setup.rate-plans');
        Route::get('setup/taxes',            Taxes::class)->name('setup.taxes');
        Route::get('setup/tax-rules',        \App\Livewire\Setup\TaxRules::class)->name('setup.tax-rules');
        Route::get('setup/users',            SetupUsers::class)->name('setup.users');

        // ----- POS masters
        Route::get('setup/pos-outlets',      SetupPosOutlets::class)->name('setup.pos-outlets');
        Route::get('setup/menu-categories',  SetupMenuCategories::class)->name('setup.menu-categories');
        Route::get('setup/menu-items',       SetupMenuItems::class)->name('setup.menu-items');
        Route::get('setup/pos-tables',       SetupPosTables::class)->name('setup.pos-tables');
        Route::get('setup/kds-stations',     SetupKdsStations::class)->name('setup.kds-stations');

        // ----- Banquet masters
        Route::get('setup/banquet-halls',    SetupBanquetHalls::class)->name('setup.banquet-halls');
        Route::get('setup/banquet-packages', SetupBanquetPackages::class)->name('setup.banquet-packages');

        // ----- Other masters
        Route::get('setup/store-categories', StoreCategories::class)->name('setup.store-categories');
        Route::get('setup/voucher-types',    SetupVoucherTypes::class)->name('setup.voucher-types');

        // ----- Promotions / Discounts / Coupons
        Route::get('promotions',        PromotionsList::class)->name('promotions.index');

        // ----- India Compliance
        Route::get('compliance/form-c',                              ComplianceFormC::class)->name('compliance.form-c');
        Route::get('compliance/form-c/{reservation}/print',          ComplianceFormCPrint::class)->where('reservation', '[0-9]+')->name('compliance.form-c.print');
        Route::get('compliance/police-register',                     CompliancePoliceRegister::class)->name('compliance.police-register');
        Route::get('compliance/tds-report',                          ComplianceTdsReport::class)->name('compliance.tds');
        Route::get('compliance/e-invoices',                          ComplianceEInvoices::class)->name('compliance.e-invoices');

        // ----- Integrations
        Route::get('integrations', IntegrationsStatus::class)->name('integrations.index');
    });
});
