<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

// Conditionally register the desktop service provider when running the
// offline / desktop build. The cloud build never loads this provider,
// so cloud routing and SaaS service stay untouched.
if (env('APP_MODE') === 'desktop') {
    $providers[] = App\Providers\DesktopServiceProvider::class;
}

return $providers;
