<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function tenantResourceUser(Tenant $tenant, string $role = 'owner'): User
{
    $user = User::factory()->create();
    if (in_array($role, ['owner', 'admin'], true)) {
        $user->forceFill(['two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'two_factor_confirmed_at' => now()])->save();
    }
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);

    return $user;
}

it('keeps plan, customer, and router records isolated between tenant workspaces', function (): void {
    $tenantA = Tenant::create(['name' => 'A', 'slug' => 'tenant-a', 'status' => 'active']);
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'tenant-b', 'status' => 'active']);
    $userA = tenantResourceUser($tenantA);
    $context = app(TenantContext::class);
    $context->set($tenantB);
    $plan = Plan::create(['name' => 'Private plan', 'price_minor' => 12345, 'currency' => 'KES', 'speed_down_kbps' => 20000, 'speed_up_kbps' => 5000, 'billing_period' => 'monthly', 'active' => true]);
    $customer = Customer::create(['plan_id' => $plan->id, 'account_number' => 'B-1', 'name' => 'Private customer', 'phone' => '+254700000001', 'pppoe_username' => 'private-b', 'pppoe_password' => 'private-passphrase', 'status' => 'active', 'billing_day' => 1]);
    $router = Router::create(['name' => 'Private router', 'connection_mode' => 'agent', 'agent_id' => 'private-agent', 'api_port' => 8729, 'use_tls' => true, 'status' => 'active']);
    $context->clear();

    $this->actingAs($userA)->withSession(['tenant_id' => $tenantA->id, 'two_factor_passed' => true])
        ->get(route('admin.customers.show', $customer->id))->assertNotFound();
    $this->actingAs($userA)->withSession(['tenant_id' => $tenantA->id, 'two_factor_passed' => true])
        ->get(route('admin.plans.edit', $plan->id))->assertNotFound();
    $this->actingAs($userA)->withSession(['tenant_id' => $tenantA->id, 'two_factor_passed' => true])
        ->get(route('admin.routers.edit', $router->id))->assertNotFound();

    $context->set($tenantB);
    expect(Plan::query()->find($plan->id))->not->toBeNull();
    $context->set($tenantA);
    expect(Plan::query()->find($plan->id))->toBeNull();
    $context->clear();
});

it('creates tenant plans, subscribers, and routers through validated admin forms', function (): void {
    $tenant = Tenant::create(['name' => 'A', 'slug' => 'tenant-a', 'status' => 'active']);
    $owner = tenantResourceUser($tenant);
    $session = ['tenant_id' => $tenant->id, 'two_factor_passed' => true];

    $this->actingAs($owner)->withSession($session)->post(route('admin.plans.store'), [
        'name' => 'Home 20', 'price_minor' => 250000, 'speed_down_kbps' => 20000,
        'speed_up_kbps' => 5000, 'billing_period' => 'monthly', 'active' => '1',
    ])->assertRedirect(route('admin.plans.index'));
    $plan = Plan::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    $this->actingAs($owner)->withSession($session)->post(route('admin.customers.store'), [
        'account_number' => 'A-001', 'name' => 'Demo subscriber', 'phone' => '+254700000001',
        'plan_id' => $plan->id, 'pppoe_username' => 'demo-a', 'pppoe_password' => 'subscriber-secret',
        'status' => 'active', 'billing_day' => 1,
    ])->assertRedirect();

    $this->actingAs($owner)->withSession($session)->post(route('admin.routers.store'), [
        'name' => 'Office router', 'connection_mode' => 'vpn', 'endpoint' => '10.20.0.4',
        'api_username' => 'router-user', 'api_password' => 'router-secret', 'api_port' => 8729,
        'radius_secret' => 'test-radius-shared-secret',
        'use_tls' => '1', 'status' => 'active',
    ])->assertRedirect(route('admin.routers.index'));
    $this->actingAs($owner)->withSession($session)->post(route('admin.routers.store'), [
        'name' => 'Public endpoint', 'connection_mode' => 'vpn', 'endpoint' => '8.8.8.8',
        'api_username' => 'router-user', 'api_password' => 'router-secret', 'api_port' => 8729,
        'radius_secret' => 'test-radius-shared-secret',
        'use_tls' => '1', 'status' => 'active',
    ])->assertSessionHasErrors('endpoint');

    $this->actingAs($owner)->withSession($session)->get(route('admin.plans.index'))->assertOk();
    $this->actingAs($owner)->withSession($session)->get(route('admin.customers.index'))->assertOk();
    $this->actingAs($owner)->withSession($session)->get(route('admin.routers.index'))->assertOk();

    expect(Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists())->toBeTrue()
        ->and(Router::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Office router')->exists())->toBeTrue()
        ->and(Router::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Public endpoint')->exists())->toBeFalse();
});

it('encrypts subscriber secrets at rest and excludes them from serialized models', function (): void {
    $tenant = Tenant::create(['name' => 'A', 'slug' => 'tenant-a', 'status' => 'active']);
    $context = app(TenantContext::class);
    $context->set($tenant);
    $customer = Customer::create(['account_number' => 'A-1', 'name' => 'Subscriber', 'phone' => '+254700000001', 'pppoe_username' => 'subscriber-a', 'pppoe_password' => 'private-passphrase', 'status' => 'active', 'billing_day' => 1]);
    $router = Router::create(['name' => 'Router A', 'connection_mode' => 'vpn', 'endpoint' => '10.0.0.4', 'api_username' => 'router-user', 'api_password' => 'router-passphrase', 'radius_secret' => 'router-radius-passphrase', 'api_port' => 8729, 'use_tls' => true, 'status' => 'active']);
    $context->clear();

    expect(DB::table('customers')->where('id', $customer->id)->value('pppoe_password'))->not->toBe('private-passphrase')
        ->and($customer->fresh()->pppoe_password)->toBe('private-passphrase')
        ->and($customer->toArray())->not->toHaveKey('pppoe_password')
        ->and(DB::table('routers')->where('id', $router->id)->value('api_password'))->not->toBe('router-passphrase')
        ->and($router->fresh()->api_password)->toBe('router-passphrase')
        ->and($router->toArray())->not->toHaveKey('api_password')
        ->and(DB::table('routers')->where('id', $router->id)->value('radius_secret'))->not->toBe('router-radius-passphrase')
        ->and($router->fresh()->radius_secret)->toBe('router-radius-passphrase')
        ->and($router->toArray())->not->toHaveKey('radius_secret');
});

it('lets support agents view resources but prevents plan and router management', function (): void {
    $tenant = Tenant::create(['name' => 'A', 'slug' => 'tenant-a', 'status' => 'active']);
    $agent = tenantResourceUser($tenant, 'agent');
    $context = app(TenantContext::class);
    $context->set($tenant);
    $router = Router::create(['name' => 'Router A', 'connection_mode' => 'agent', 'agent_id' => 'agent-a', 'api_port' => 8729, 'use_tls' => true, 'status' => 'active']);
    $context->clear();

    $this->actingAs($agent)->withSession(['tenant_id' => $tenant->id, 'two_factor_passed' => true])
        ->get(route('admin.routers.index'))->assertOk();
    $this->actingAs($agent)->withSession(['tenant_id' => $tenant->id, 'two_factor_passed' => true])
        ->get(route('admin.routers.create'))->assertForbidden();
    $this->actingAs($agent)->withSession(['tenant_id' => $tenant->id, 'two_factor_passed' => true])
        ->get(route('admin.routers.edit', $router->id))->assertForbidden();
});
