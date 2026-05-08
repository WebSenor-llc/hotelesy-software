<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * DesktopServiceProvider — only registers when APP_MODE=desktop.
 *
 * Responsibilities:
 *   - Register desktop routes (no SaaS landing, no public hotel website,
 *     no super-admin cross-tenant tools).
 *   - Bind a permanent tenant + property to TenantContext (single-tenant).
 *   - Share a "desktop" view variable so layouts can hide cloud-only UI.
 *   - Bridge to NativePHP if the package is installed.
 */
class DesktopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Isolate the desktop build's runtime state from the cloud build so
        // both can run side-by-side on the same machine without stomping on
        // each other:
        //   - sessions in their own folder so logins don't clobber
        //   - a different session cookie name so the browser stores them separately
        //   - a unique cache prefix so cache keys don't collide
        config([
            'session.files'  => storage_path('framework/sessions-desktop'),
            'session.cookie' => 'hotelesy_desktop_session',
            'cache.prefix'   => 'hotelesy_desktop_cache',
        ]);
        if (! is_dir(storage_path('framework/sessions-desktop'))) {
            @mkdir(storage_path('framework/sessions-desktop'), 0755, true);
        }

        // Force-bind tenant + property as soon as the service container boots,
        // so every Eloquent model with BelongsToTenant scope works without
        // running ResolveTenant middleware.
        $this->app->resolving(\App\Services\TenantContext::class, function ($ctx) {
            $tenantId   = (int) config('desktop.tenant_id', 1);
            $propertyId = (int) config('desktop.property_id', 1);

            try {
                if ($tenantId > 0 && \Illuminate\Support\Facades\Schema::hasTable('tenants')) {
                    $tenant = \App\Models\Tenant::find($tenantId);
                    if ($tenant) $ctx->set($tenant);
                }
                if ($propertyId > 0 && \Illuminate\Support\Facades\Schema::hasTable('properties')) {
                    $property = \App\Models\Property::find($propertyId);
                    if ($property) $ctx->setProperty($property);
                }
            } catch (\Throwable $e) {
                // Fresh install — tables not migrated yet. Setup wizard will
                // create the tenant + property on first run.
            }
        });
    }

    public function boot(): void
    {
        // 1. Register desktop-only routes
        if (file_exists(base_path('routes/desktop.php'))) {
            Route::middleware('web')->group(base_path('routes/desktop.php'));
        }

        // 1a. Push the desktop-license enforcement middleware onto the web
        // group so every web route gets gated. We do it here (instead of in
        // bootstrap/app.php) because it's only needed in the desktop build.
        $router = $this->app->make(\Illuminate\Routing\Router::class);
        $router->pushMiddlewareToGroup('web', \App\Http\Middleware\EnforceDesktopLicense::class);

        // 1b. Register the artisan phone-home command so the scheduler can
        // pick it up.
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\DesktopPhoneHome::class,
            ]);
        }

        // 2. Share a "desktop" flag with all views so the cloud-specific
        //    UI bits (subscription banner, license expiry timer, "buy
        //    plan" CTAs, etc.) can be hidden.
        View::share('isDesktopBuild', true);
        View::share('desktopVersion', config('desktop.version', '1.0.0'));

        // 3. Hook into NativePHP if available (only loaded when the user
        //    runs `composer require nativephp/electron`).
        if (class_exists(\Native\Laravel\Facades\Window::class)) {
            $this->bootNativePhp();
        }
    }

    /**
     * Wire the main desktop window. Runs only when nativephp/electron is
     * installed — the cloud build doesn't ship this dependency.
     */
    private function bootNativePhp(): void
    {
        \Native\Laravel\Facades\Window::open('hotelesy')
            ->title(config('desktop.window_title', 'Hotelesy'))
            ->width(config('desktop.window_width', 1440))
            ->height(config('desktop.window_height', 900))
            ->minWidth(config('desktop.min_width', 1180))
            ->minHeight(config('desktop.min_height', 720))
            ->showDevTools(app()->environment('local'))
            ->url(url('/desktop'));   // first-run setup or direct dashboard
    }
}
