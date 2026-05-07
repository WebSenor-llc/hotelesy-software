<?php

use App\Http\Middleware\RequireModule;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant.resolve' => ResolveTenant::class,
            'tenant'         => ResolveTenant::class,
            'property'       => \App\Http\Middleware\RequireProperty::class,
            'module'         => RequireModule::class,
            'license'        => \App\Http\Middleware\EnforceLicense::class,
            'rbac'           => \App\Http\Middleware\EnforceRoutePermission::class,
            'role'           => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'     => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
