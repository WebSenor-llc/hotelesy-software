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
        // Make Livewire's update endpoint go through auth + tenant so
        // TenantContext is set when component actions (save, etc) run.
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::setUpdateRoute(function ($handle) {
                return \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                    ->middleware(['web', 'auth', 'tenant']);
            });
        }
    }
}
