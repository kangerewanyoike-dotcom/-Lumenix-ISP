<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PlatformInvoice;
use App\Models\PlatformSubscription;
use App\Models\PlatformUsageSnapshot;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('creates a separate idempotent Lumenix invoice from an active subscriber snapshot', function (): void {
    $tenant = Tenant::create(['name' => 'Platform ISP', 'slug' => 'platform-isp', 'status' => 'active', 'currency' => 'KES']);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Home', 'price_minor' => 10000, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'monthly', 'active' => true]);
    Customer::create(['plan_id' => $plan->id, 'account_number' => 'PLAT-1', 'name' => 'Active', 'phone' => '+254700000001', 'pppoe_username' => 'platform-active', 'pppoe_password' => 'secret', 'status' => 'active', 'billing_day' => 1]);
    Customer::create(['plan_id' => $plan->id, 'account_number' => 'PLAT-2', 'name' => 'Suspended', 'phone' => '+254700000002', 'pppoe_username' => 'platform-suspended', 'pppoe_password' => 'secret', 'status' => 'suspended', 'billing_day' => 1]);
    app(TenantContext::class)->clear();
    PlatformSubscription::create(['tenant_id' => $tenant->id, 'price_per_subscriber_minor' => 1250, 'currency' => 'KES', 'billing_day' => 1, 'status' => 'active']);

    Artisan::call('platform-billing:run', ['--period' => '2026-10']);
    Artisan::call('platform-billing:run', ['--period' => '2026-10']);

    expect(PlatformUsageSnapshot::query()->count())->toBe(1)
        ->and(PlatformUsageSnapshot::query()->first()->active_subscriber_count)->toBe(1)
        ->and(PlatformUsageSnapshot::query()->first()->amount_minor)->toBe(1250)
        ->and(PlatformInvoice::query()->count())->toBe(1)
        ->and(PlatformInvoice::query()->first()->total_minor)->toBe(1250)
        ->and(PlatformInvoice::query()->first()->balance_minor)->toBe(1250)
        ->and(DB::table('invoices')->count())->toBe(0);
});
