<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantReportingService;

it('calculates tenant billing summaries from its own invoice and payment ledgers', function (): void {
    $tenantA = Tenant::create(['name' => 'Reports A', 'slug' => 'reports-a', 'status' => 'active']);
    app(TenantContext::class)->set($tenantA);
    $planA = Plan::create(['name' => 'A plan', 'price_minor' => 10000, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'monthly', 'active' => true]);
    $customerA = Customer::create(['plan_id' => $planA->id, 'account_number' => 'REPORT-A', 'name' => 'A', 'phone' => '+254700000001', 'pppoe_username' => 'report-a', 'pppoe_password' => 'secret', 'status' => 'active', 'billing_day' => 1]);
    Invoice::create(['customer_id' => $customerA->id, 'number' => 'RA-INV', 'status' => 'issued', 'issued_at' => now()->toDateString(), 'due_at' => now()->addDays(5)->toDateString(), 'currency' => 'KES', 'subtotal_minor' => 10000, 'tax_minor' => 0, 'total_minor' => 10000, 'balance_minor' => 6000, 'idempotency_key' => 'report-a-invoice']);
    app(TenantContext::class)->clear();

    $tenantB = Tenant::create(['name' => 'Reports B', 'slug' => 'reports-b', 'status' => 'active']);
    app(TenantContext::class)->set($tenantB);
    $planB = Plan::create(['name' => 'B plan', 'price_minor' => 10000, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'monthly', 'active' => true]);
    Customer::create(['plan_id' => $planB->id, 'account_number' => 'REPORT-B', 'name' => 'B', 'phone' => '+254700000002', 'pppoe_username' => 'report-b', 'pppoe_password' => 'secret', 'status' => 'active', 'billing_day' => 1]);
    $summary = app(TenantReportingService::class)->summary();

    expect($summary['active_customers'])->toBe(1)
        ->and($summary['outstanding_minor'])->toBe(0)
        ->and($summary['outstanding_invoice_count'])->toBe(0);
});
