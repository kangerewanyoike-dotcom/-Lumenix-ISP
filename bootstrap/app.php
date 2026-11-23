<?php

use App\Http\Middleware\RequirePlatformAdmin;
use App\Http\Middleware\ResolveCustomerPortal;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['daraja/*']);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'customer.portal' => ResolveCustomerPortal::class,
            'platform.admin' => RequirePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['consumer_key', 'consumer_secret', 'passkey', 'api_key']);
    })->create();
