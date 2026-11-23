<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Tenant;
use App\Services\CustomerAccessService;
use App\Services\FakeRouterConnection;
use App\Services\RadiusDisconnectRequest;
use App\Services\RouterConnection;
use Illuminate\Support\Facades\DB;

function routerAccessFixture(): array
{
    $tenant = Tenant::create(['name' => 'Router ISP', 'slug' => 'router-isp', 'status' => 'active']);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Basic', 'price_minor' => 1000, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'monthly', 'active' => true]);
    $router = Router::create(['name' => 'Edge one', 'connection_mode' => 'vpn', 'endpoint' => '10.10.1.2', 'radius_secret' => 'a-long-router-shared-secret', 'api_port' => 8729, 'use_tls' => true, 'status' => 'active']);
    $customer = Customer::create(['plan_id' => $plan->id, 'account_number' => 'RT-1', 'name' => 'Subscriber', 'phone' => '+254700000002', 'pppoe_username' => 'router-user', 'pppoe_password' => 'secret-password', 'status' => 'active', 'billing_day' => 1]);

    return [$tenant, $router, $customer];
}

it('retries disconnects and syncs the rejected RADIUS state before suspension', function (): void {
    [, $router, $customer] = routerAccessFixture();
    $driver = new FakeRouterConnection([false, true]);
    app()->instance(RouterConnection::class, $driver);

    app(CustomerAccessService::class)->suspend($customer, $router);

    expect($customer->fresh()->status)->toBe('suspended')
        ->and(count($driver->calls))->toBe(2)
        ->and(DB::table('radcheck')->where('username', 'router-user')->value('value'))->toBe('Reject');
});

it('keeps the safe RADIUS state if disconnect acknowledgements fail all retries', function (): void {
    [, $router, $customer] = routerAccessFixture();
    app()->instance(RouterConnection::class, new FakeRouterConnection([false, false, false]));

    expect(fn () => app(CustomerAccessService::class)->suspend($customer, $router))
        ->toThrow(RuntimeException::class, 'after 3 attempts');
    expect($customer->fresh()->status)->toBe('suspended');
});

it('reactivates subscriber credentials before requesting a fresh PPP session', function (): void {
    [, $router, $customer] = routerAccessFixture();
    $customer->update(['status' => 'suspended']);
    $driver = new FakeRouterConnection([true]);
    app()->instance(RouterConnection::class, $driver);

    app(CustomerAccessService::class)->reactivate($customer, $router);

    expect($customer->fresh()->status)->toBe('active')
        ->and(DB::table('radcheck')->where('username', 'router-user')->value('attribute'))->toBe('Cleartext-Password')
        ->and(count($driver->calls))->toBe(1)
        ->and(DB::table('nas')->where('description', 'lumenix-router-'.$router->id)->value('secret'))->toBe('a-long-router-shared-secret');
});

it('builds a bounded Disconnect-Request for the subscriber and NAS address', function (): void {
    [, $router, $customer] = routerAccessFixture();
    $payload = app(RadiusDisconnectRequest::class)->payload($router, $customer);

    expect($payload)->toBe("User-Name = \"router-user\"\nNAS-IP-Address = 10.10.1.2\n");
});
