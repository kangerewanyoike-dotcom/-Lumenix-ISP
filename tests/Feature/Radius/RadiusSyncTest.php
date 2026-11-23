<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\RadiusAccountingSession;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function radiusFixture(): array
{
    $tenant = Tenant::create(['name' => 'RADIUS ISP', 'slug' => 'radius-isp', 'status' => 'active']);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => '50 Mbps', 'price_minor' => 300000, 'currency' => 'KES', 'speed_down_kbps' => 50000, 'speed_up_kbps' => 10000, 'billing_period' => 'monthly', 'active' => true]);
    $customer = Customer::create([
        'plan_id' => $plan->id, 'account_number' => 'R-1', 'name' => 'Subscriber', 'phone' => '+254700000001',
        'pppoe_username' => 'radius-subscriber', 'pppoe_password' => 'radius-password', 'status' => 'active', 'billing_day' => 1,
    ]);

    return [$tenant, $plan, $customer];
}

it('syncs credentials, MikroTik rates and user groups, and rejects suspended subscribers', function (): void {
    [, $plan, $customer] = radiusFixture();

    expect(DB::table('radcheck')->where('username', $customer->pppoe_username)->value('attribute'))->toBe('Cleartext-Password')
        ->and(DB::table('radcheck')->where('username', $customer->pppoe_username)->value('value'))->toBe('radius-password')
        ->and(DB::table('radreply')->where('username', $customer->pppoe_username)->value('value'))->toBe('10000k/50000k')
        ->and(DB::table('radusergroup')->where('username', $customer->pppoe_username)->value('groupname'))->toBe('tenant-'.$customer->tenant_id.'-plan-'.$plan->id);

    $plan->update(['speed_down_kbps' => 60000]);
    expect(DB::table('radreply')->where('username', $customer->pppoe_username)->value('value'))->toBe('10000k/60000k');

    $customer->update(['status' => 'suspended']);
    expect(DB::table('radcheck')->where('username', $customer->pppoe_username)->value('attribute'))->toBe('Auth-Type')
        ->and(DB::table('radcheck')->where('username', $customer->pppoe_username)->value('value'))->toBe('Reject')
        ->and(DB::table('radreply')->where('username', $customer->pppoe_username)->exists())->toBeFalse()
        ->and(DB::table('radusergroup')->where('username', $customer->pppoe_username)->exists())->toBeFalse();
});

it('removes stale RADIUS rows when a subscriber username changes', function (): void {
    [, , $customer] = radiusFixture();
    $customer->update(['pppoe_username' => 'radius-subscriber-renamed']);

    expect(DB::table('radcheck')->where('username', 'radius-subscriber')->exists())->toBeFalse()
        ->and(DB::table('radcheck')->where('username', 'radius-subscriber-renamed')->value('value'))->toBe('radius-password');
});

it('projects accounting idempotently and keeps usage tenant scoped', function (): void {
    [$tenant, , $customer] = radiusFixture();
    DB::table('radacct')->insert([
        'acctsessionid' => 'session-1', 'acctuniqueid' => 'unique-1', 'username' => $customer->pppoe_username,
        'nasipaddress' => '10.0.0.2', 'acctstarttime' => now(), 'acctsessiontime' => 120,
        'acctinputoctets' => 3000, 'acctoutputoctets' => 9000,
    ]);
    app(TenantContext::class)->clear();

    expect(Artisan::call('radius:ingest-accounting'))->toBe(0)
        ->and(Artisan::call('radius:ingest-accounting'))->toBe(0);

    app(TenantContext::class)->set($tenant);
    expect(RadiusAccountingSession::query()->count())->toBe(1)
        ->and(RadiusAccountingSession::query()->first()->input_octets)->toBe(3000)
        ->and(RadiusAccountingSession::query()->first()->output_octets)->toBe(9000);
});
