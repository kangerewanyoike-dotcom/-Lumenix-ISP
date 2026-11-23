<?php

namespace App\Providers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Router;
use App\Observers\CustomerObserver;
use App\Observers\PlanObserver;
use App\Observers\RouterObserver;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PlanPolicy;
use App\Policies\RouterPolicy;
use App\Services\RadclientRouterConnection;
use App\Services\RouterConnection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(RouterConnection::class, RadclientRouterConnection::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('staff-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('tenant-register', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('portal-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('daraja-callback', fn (Request $request) => Limit::perMinute(120)->by(($request->route()?->getName() ?? 'callback').'|'.$request->ip()));

        Customer::observe(CustomerObserver::class);
        Plan::observe(PlanObserver::class);
        Router::observe(RouterObserver::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Router::class, RouterPolicy::class);
    }
}
