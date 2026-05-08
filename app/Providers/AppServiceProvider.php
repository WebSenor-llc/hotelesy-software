<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TenantContext must be a singleton so set()/bypass() and the
        // BelongsToTenant trait share state across the request lifecycle.
        $this->app->singleton(\App\Services\TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire's update endpoint (/livewire/update) runs on `web` middleware
        // as a baseline so public-facing Livewire forms (trial signup, contact,
        // hotel-site booking) keep working without forcing auth on everyone.
        //
        // CRITICAL: Livewire 4 does NOT auto-propagate the originating page's
        // middleware to /livewire/update. We must explicitly register middleware
        // as "persistent" via addPersistentMiddleware() — Livewire then inspects
        // the original page's middleware list and re-applies any of these on
        // the update endpoint. Without this, ResolveTenant never fires on
        // Livewire saves, TenantContext::property() returns null, and any
        // component action that calls `app(TenantContext::class)->property()`
        // crashes with "Call to a member function update() on null".
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::setUpdateRoute(function ($handle) {
                return \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                    ->middleware(['web']);
            });

            \Livewire\Livewire::addPersistentMiddleware([
                \App\Http\Middleware\ResolveTenant::class,
                \App\Http\Middleware\EnforceLicense::class,
                \App\Http\Middleware\EnforceRoutePermission::class,
                \App\Http\Middleware\RequireProperty::class,
                \App\Http\Middleware\RequireModule::class,
                \App\Http\Middleware\EnforceNightAuditLock::class,
            ]);
        }
    }
}
