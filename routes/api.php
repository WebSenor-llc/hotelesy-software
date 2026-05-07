<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FrontOfficeController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\KDS\KdsController;
use App\Http\Controllers\Api\POS\PosMenuController;
use App\Http\Controllers\Api\POS\PosOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Miraj Hotel Suite
|--------------------------------------------------------------------------
| All authenticated routes are wrapped in:
|   - auth:sanctum     — token / cookie auth
|   - tenant.resolve   — sets TenantContext
| Module-gated routes additionally use:
|   - module:{code}    — checks ModuleService::enabled()
*/

// Public
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'tenant.resolve'])->group(function () {
    /* Auth */
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    /* ============ PMS (core, always enabled) ============ */
    Route::apiResource('properties', PropertyController::class)->except(['destroy']);
    Route::get('properties/{property}/availability', [ReservationController::class, 'availability']);
    Route::apiResource('room-types', RoomTypeController::class)->except(['show', 'destroy']);
    Route::apiResource('rooms', RoomController::class)->only(['index', 'store']);
    Route::patch('rooms/{room}/status', [RoomController::class, 'updateStatus']);

    Route::apiResource('reservations', ReservationController::class)->only(['index', 'show', 'store']);
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::post('reservations/{reservation}/no-show', [ReservationController::class, 'noShow']);
    Route::post('reservations/{reservation}/check-in', [FrontOfficeController::class, 'checkInAction']);
    Route::post('reservations/{reservation}/check-out', [FrontOfficeController::class, 'checkOutAction']);

    Route::post('folios/{folio}/charges', [FrontOfficeController::class, 'postCharge']);
    Route::post('folios/{folio}/payments', [FrontOfficeController::class, 'recordPayment']);
    Route::post('folios/{folio}/settle', [FrontOfficeController::class, 'settle']);

    /* ============ POS (module-gated) ============ */
    Route::middleware('module:pos')->prefix('pos')->group(function () {
        // Outlets / tables / menu
        Route::get('outlets', [PosMenuController::class, 'outlets']);
        Route::post('outlets', [PosMenuController::class, 'storeOutlet']);
        Route::get('outlets/{outlet}/tables', [PosMenuController::class, 'tables']);
        Route::post('outlets/{outlet}/tables', [PosMenuController::class, 'storeTable']);
        Route::get('outlets/{outlet}/menu', [PosMenuController::class, 'menu']);

        Route::post('menu-categories', [PosMenuController::class, 'storeCategory']);
        Route::post('menu-items', [PosMenuController::class, 'storeItem']);

        // Orders (KOT/BOT)
        Route::get('orders', [PosOrderController::class, 'index']);
        Route::get('orders/{order}', [PosOrderController::class, 'show']);
        Route::post('outlets/{outlet}/orders', [PosOrderController::class, 'store']);
        Route::post('orders/{order}/items', [PosOrderController::class, 'addItems']);
        Route::post('orders/{order}/items/{item}/void', [PosOrderController::class, 'voidItem']);
        Route::post('orders/{order}/send-to-kitchen', [PosOrderController::class, 'sendToKitchen']);
        Route::post('orders/{order}/discount', [PosOrderController::class, 'applyDiscount']);
        Route::post('orders/{order}/bill', [PosOrderController::class, 'bill']);
        Route::post('orders/{order}/charge-to-folio', [PosOrderController::class, 'chargeToFolio']);
        Route::post('orders/{order}/settle', [PosOrderController::class, 'settle']);
    });

    /* ============ KDS (module-gated; depends on pos) ============ */
    Route::middleware('module:kds')->prefix('kds')->group(function () {
        Route::get('stations', [KdsController::class, 'stations']);
        Route::get('stations/{station}/queue', [KdsController::class, 'queue']);
        Route::post('tickets/{ticket}/start', [KdsController::class, 'startTicket']);
        Route::post('tickets/{ticket}/ready', [KdsController::class, 'markReady']);
        Route::post('tickets/{ticket}/served', [KdsController::class, 'markServed']);
        Route::post('tickets/{ticket}/recall', [KdsController::class, 'recall']);
        Route::patch('tickets/{ticket}/priority', [KdsController::class, 'setPriority']);
    });

    /* ============ Reviews (module-gated) ============ */
    Route::middleware('module:reviews')->prefix('reviews')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Reviews\ReviewController::class, 'index']);
        Route::get('summary', [\App\Http\Controllers\Api\Reviews\ReviewController::class, 'summary']);
        Route::get('{review}', [\App\Http\Controllers\Api\Reviews\ReviewController::class, 'show']);
        Route::post('{review}/respond', [\App\Http\Controllers\Api\Reviews\ReviewController::class, 'respond']);
    });

    /* ============ Revenue Management (module-gated) ============ */
    Route::middleware('module:revenue')->prefix('revenue')->group(function () {
        Route::get('rate-recommendation', [\App\Http\Controllers\Api\Revenue\RevenueController::class, 'rateRecommendation']);
        Route::get('rate-shop', [\App\Http\Controllers\Api\Revenue\RevenueController::class, 'rateShop']);
        Route::get('rules', [\App\Http\Controllers\Api\Revenue\RevenueController::class, 'indexRules']);
        Route::post('rules', [\App\Http\Controllers\Api\Revenue\RevenueController::class, 'storeRule']);
        Route::get('competitors', [\App\Http\Controllers\Api\Revenue\RevenueController::class, 'indexCompetitors']);
        Route::post('competitors', [\App\Http\Controllers\Api\Revenue\RevenueController::class, 'storeCompetitor']);
    });

    /* ============ Amenities (module-gated) ============ */
    Route::middleware('module:amenities')->prefix('amenities')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Amenities\AmenityController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Amenities\AmenityController::class, 'store']);
        Route::post('{amenity}/order', [\App\Http\Controllers\Api\Amenities\AmenityController::class, 'order']);
    });

    /* ============ CMS (module-gated, admin) ============ */
    Route::middleware('module:cms')->prefix('cms')->group(function () {
        Route::get('sites', [\App\Http\Controllers\Api\CMS\CmsController::class, 'index']);
        Route::get('sites/{site}', [\App\Http\Controllers\Api\CMS\CmsController::class, 'show']);
        Route::post('sites/{site}/publish', [\App\Http\Controllers\Api\CMS\CmsController::class, 'publish']);
        Route::post('sites/{site}/pages', [\App\Http\Controllers\Api\CMS\CmsController::class, 'storePage']);
        Route::patch('pages/{page}', [\App\Http\Controllers\Api\CMS\CmsController::class, 'updatePage']);
    });

    /* ============ Module Administration ============ */
    Route::prefix('admin')->group(function () {
        Route::get('modules', [\App\Http\Controllers\Api\ModuleAdminController::class, 'index']);
        Route::post('modules/{moduleCode}/enable', [\App\Http\Controllers\Api\ModuleAdminController::class, 'enable']);
        Route::post('modules/{moduleCode}/disable', [\App\Http\Controllers\Api\ModuleAdminController::class, 'disable']);
    });

    /* ============ Banquet (module-gated) ============ */
    Route::middleware('module:banquet')->prefix('banquet')->group(function () {
        Route::get('halls', [\App\Http\Controllers\Api\Banquet\BanquetController::class, 'halls']);
        Route::get('bookings', [\App\Http\Controllers\Api\Banquet\BanquetController::class, 'bookings']);
        Route::post('bookings', [\App\Http\Controllers\Api\Banquet\BanquetController::class, 'store']);
        Route::get('bookings/{booking}', [\App\Http\Controllers\Api\Banquet\BanquetController::class, 'show']);
        Route::post('bookings/{booking}/transition', [\App\Http\Controllers\Api\Banquet\BanquetController::class, 'transition']);
        Route::post('bookings/{booking}/advance', [\App\Http\Controllers\Api\Banquet\BanquetController::class, 'recordAdvance']);
    });

    /* ============ Housekeeping (module-gated) ============ */
    Route::middleware('module:housekeeping')->prefix('housekeeping')->group(function () {
        Route::get('tasks', [\App\Http\Controllers\Api\Housekeeping\HousekeepingController::class, 'index']);
        Route::post('tasks/generate-daily', [\App\Http\Controllers\Api\Housekeeping\HousekeepingController::class, 'generateDaily']);
        Route::post('tasks/auto-assign', [\App\Http\Controllers\Api\Housekeeping\HousekeepingController::class, 'autoAssign']);
        Route::post('tasks/{task}/start', [\App\Http\Controllers\Api\Housekeeping\HousekeepingController::class, 'start']);
        Route::post('tasks/{task}/complete', [\App\Http\Controllers\Api\Housekeeping\HousekeepingController::class, 'complete']);
        Route::post('tasks/{task}/verify', [\App\Http\Controllers\Api\Housekeeping\HousekeepingController::class, 'verify']);
    });

    /* ============ Store (module-gated) ============ */
    Route::middleware('module:store')->prefix('store')->group(function () {
        Route::get('items', [\App\Http\Controllers\Api\Store\StoreController::class, 'items']);
        Route::get('purchase-orders', [\App\Http\Controllers\Api\Store\StoreController::class, 'purchaseOrders']);
        Route::post('purchase-orders', [\App\Http\Controllers\Api\Store\StoreController::class, 'createPO']);
        Route::post('purchase-orders/{po}/approve', [\App\Http\Controllers\Api\Store\StoreController::class, 'approvePO']);
        Route::post('purchase-orders/{po}/receive', [\App\Http\Controllers\Api\Store\StoreController::class, 'receiveGoods']);
        Route::post('items/{item}/issue', [\App\Http\Controllers\Api\Store\StoreController::class, 'issue']);
    });

    /* ============ Accounts (module-gated) ============ */
    Route::middleware('module:accounts')->prefix('accounts')->group(function () {
        Route::get('chart', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'chart']);
        Route::get('vouchers', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'vouchers']);
        Route::post('vouchers', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'postVoucher']);
        Route::post('vouchers/{voucher}/reverse', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'reverseVoucher']);
        Route::get('trial-balance', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'trialBalance']);

        Route::get('gst-returns', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'gstReturns']);
        Route::post('gst-returns/compute', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'computeGst']);
        Route::post('gst-returns/{return}/mark-filed', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'markGstFiled']);

        Route::get('night-audit', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'nightAuditHistory']);
        Route::post('night-audit/run', [\App\Http\Controllers\Api\Accounts\AccountsController::class, 'runNightAudit']);
    });
});

/* ============ Public endpoints (no auth) ============ */
Route::prefix('public/cms')->group(function () {
    Route::get('site', [\App\Http\Controllers\Api\CMS\CmsController::class, 'publicSite']);
    Route::get('page', [\App\Http\Controllers\Api\CMS\CmsController::class, 'publicPage']);
});
