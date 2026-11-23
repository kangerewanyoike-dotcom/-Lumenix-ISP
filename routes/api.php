<?php

use App\Http\Controllers\Api\V1\PlatformApiController;
use App\Http\Controllers\Api\V1\TenantApiController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\RouterTelemetryController;
use App\Http\Controllers\RadiusAccountingWebhookController;
use App\Http\Controllers\DarajaCallbackController;
use Illuminate\Support\Facades\Route;

// First-party SPA API: session-authenticated with tenant selection and platform-role gates.
Route::post('/v1/webhooks/routers/{router}/telemetry', [RouterTelemetryController::class, 'store'])->middleware('throttle:120,1');
Route::post('/v1/webhooks/radius/accounting', [RadiusAccountingWebhookController::class, 'store'])->middleware('throttle:600,1');
Route::post('/v1/webhooks/mpesa/stk-callback/{token}', [DarajaCallbackController::class, 'stk'])->middleware('throttle:daraja-callback');
Route::post('/v1/webhooks/mpesa/c2b-validation/{token}', [DarajaCallbackController::class, 'validateC2b'])->middleware('throttle:daraja-callback');
Route::post('/v1/webhooks/mpesa/c2b-confirmation/{token}', [DarajaCallbackController::class, 'c2b'])->middleware('throttle:daraja-callback');
Route::prefix('v1')->middleware(['web', 'auth'])->group(function (): void {
    Route::prefix('admin')->middleware('platform.admin')->group(function (): void {
        Route::get('/dashboard', [PlatformApiController::class, 'dashboard']);
        Route::get('/tenants', [PlatformApiController::class, 'tenants']);
        Route::post('/tenants', [PlatformApiController::class, 'createTenant']);
        Route::get('/tenants/{tenant}', [PlatformApiController::class, 'tenant']);
        Route::patch('/tenants/{tenant}/status', [PlatformApiController::class, 'tenantStatus']);
        Route::get('/plans', [PlatformApiController::class, 'plans']);
        Route::post('/plans', [PlatformApiController::class, 'createPlan']);
        Route::get('/subscriptions', [PlatformApiController::class, 'subscriptions']);
        Route::get('/revenue', [PlatformApiController::class, 'revenue']);
    });

    Route::prefix('tenant')->middleware('tenant')->group(function (): void {
        Route::get('/dashboard', [TenantApiController::class, 'dashboard']);
        Route::get('/customers', [TenantApiController::class, 'customers']);
        Route::post('/customers', [TenantApiController::class, 'createCustomer']);
        Route::get('/packages', [TenantApiController::class, 'packages']);
        Route::post('/packages', [TenantApiController::class, 'createPackage']);
        Route::post('/pppoe/accounts', [TenantApiController::class, 'createCustomer']);
        Route::post('/hotspot/vouchers', [HotspotController::class, 'apiStore'])->middleware('throttle:20,1');
        Route::get('/routers', [TenantApiController::class, 'routers']);
        Route::post('/routers', [TenantApiController::class, 'createRouter']);
        Route::post('/invoices', [TenantApiController::class, 'createInvoice']);
        Route::post('/payments/stk-push', [TenantApiController::class, 'stkPush']);
        Route::get('/reports/revenue', [TenantApiController::class, 'revenue']);
    });
});
