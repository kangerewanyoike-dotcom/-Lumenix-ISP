<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function billingFixture(int $vat = 0, int $price = 250000, string $period = 'monthly'): array
{
    $tenant = Tenant::create(['name' => 'Billing ISP', 'slug' => 'billing-isp', 'status' => 'active', 'currency' => 'KES', 'vat_rate_basis_points' => $vat]);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Home plan', 'price_minor' => $price, 'currency' => 'KES', 'speed_down_kbps' => 20000, 'speed_up_kbps' => 5000, 'billing_period' => $period, 'active' => true]);
    $customer = Customer::create([
        'plan_id' => $plan->id, 'account_number' => 'ACC-1', 'name' => 'Subscriber', 'phone' => '+254700000001',
        'pppoe_username' => 'billing-user', 'pppoe_password' => 'subscriber-secret', 'status' => 'active',
        'billing_day' => 1, 'billing_anchor_date' => '2026-01-01',
    ]);

    return [$tenant, $plan, $customer];
}

it('issues recurring invoices with rounded VAT snapshots and a stable retry key', function (): void {
    [$tenant, $plan, $customer] = billingFixture(1600);
    $billing = app(BillingService::class);
    $start = CarbonImmutable::parse('2026-04-01');
    $end = CarbonImmutable::parse('2026-04-30');

    $invoice = $billing->generateRecurringInvoice($customer, $start, $end);
    $retry = $billing->generateRecurringInvoice($customer, $start, $end);
    $plan->update(['price_minor' => 400000]);
    $tenant->update(['vat_rate_basis_points' => 0]);

    expect($retry->id)->toBe($invoice->id)
        ->and(Invoice::query()->count())->toBe(1)
        ->and($invoice->subtotal_minor)->toBe(250000)
        ->and($invoice->tax_minor)->toBe(40000)
        ->and($invoice->total_minor)->toBe(290000)
        ->and($invoice->lines->first()->tax_rate_basis_points)->toBe(1600)
        ->and($invoice->fresh()->total_minor)->toBe(290000);
});

it('prorates plan upgrades and downgrades by inclusive remaining cycle days', function (): void {
    [, $oldPlan, $customer] = billingFixture(1600);
    $upgrade = Plan::create(['name' => 'Upgrade', 'price_minor' => 350000, 'currency' => 'KES', 'speed_down_kbps' => 30000, 'speed_up_kbps' => 10000, 'billing_period' => 'monthly', 'active' => true]);
    $downgrade = Plan::create(['name' => 'Downgrade', 'price_minor' => 200000, 'currency' => 'KES', 'speed_down_kbps' => 10000, 'speed_up_kbps' => 3000, 'billing_period' => 'monthly', 'active' => true]);
    $billing = app(BillingService::class);
    $periodStart = CarbonImmutable::parse('2026-04-01');
    $periodEnd = CarbonImmutable::parse('2026-04-30');
    $effective = CarbonImmutable::parse('2026-04-16');

    $up = $billing->changePlan($customer, $upgrade, $effective, $periodStart, $periodEnd, 'upgrade-apr');
    $down = $billing->changePlan($customer->fresh(), $downgrade, $effective, $periodStart, $periodEnd, 'downgrade-apr');
    $retry = $billing->changePlan($customer->fresh(), $downgrade, $effective, $periodStart, $periodEnd, 'downgrade-apr');

    expect($up['adjustment_minor'])->toBe(50000)
        ->and($up['invoice']->subtotal_minor)->toBe(50000)
        ->and($up['invoice']->tax_minor)->toBe(8000)
        ->and($down['adjustment_minor'])->toBe(-75000)
        ->and($down['credit']->amount_minor)->toBe(87000)
        ->and($retry['credit']->id)->toBe($down['credit']->id)
        ->and($customer->fresh()->plan_id)->toBe($downgrade->id)
        ->and($oldPlan->fresh()->price_minor)->toBe(250000);
});

it('prorates a billing-day extension or shortening into an invoice or customer credit', function (): void {
    [, , $customer] = billingFixture(1600);
    $billing = app(BillingService::class);
    $result = $billing->changeBillingDay(
        $customer, 25, CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-30'),
        CarbonImmutable::parse('2026-04-24'), 'billing-day-short',
    );
    $beforeNextBillingDay = $billing->runRecurringForTenantDate(CarbonImmutable::parse('2026-04-24'));
    $onNewBillingDay = $billing->runRecurringForTenantDate(CarbonImmutable::parse('2026-04-25'));

    expect($result['adjustment_minor'])->toBe(-50000)
        ->and($result['credit']->amount_minor)->toBe(58000)
        ->and($customer->fresh()->billing_day)->toBe(25)
        ->and($customer->fresh()->billing_anchor_date->toDateString())->toBe('2026-04-25')
        ->and($beforeNextBillingDay)->toBe(0)
        ->and($onNewBillingDay)->toBe(1)
        ->and(Invoice::query()->where('customer_id', $customer->id)->whereNotNull('period_start')->first()->period_start->toDateString())->toBe('2026-04-25');

    $extensionCustomer = Customer::create(['plan_id' => $customer->plan_id, 'account_number' => 'ACC-EXT', 'name' => 'Extension subscriber', 'phone' => '+254700000004', 'pppoe_username' => 'extension-user', 'pppoe_password' => 'subscriber-secret', 'status' => 'active', 'billing_day' => 1, 'billing_anchor_date' => '2026-04-01']);
    $extension = $billing->changeBillingDay(
        $extensionCustomer, 25, CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-30'),
        CarbonImmutable::parse('2026-05-24'), 'billing-day-extension',
    );
    expect($extension['adjustment_minor'])->toBe(200000)
        ->and($extension['invoice']->total_minor)->toBe(232000)
        ->and($extensionCustomer->fresh()->billing_anchor_date->toDateString())->toBe('2026-05-25');
});

it('keeps one-off charges and credit applications idempotent and never overdraws credit', function (): void {
    [, , $customer] = billingFixture(1600);
    $billing = app(BillingService::class);
    $issued = CarbonImmutable::parse('2026-04-01');
    $invoice = $billing->createOneOffCharge($customer, 'Installation', 10000, $issued, 'install-1');
    $retry = $billing->createOneOffCharge($customer, 'Installation', 10000, $issued, 'install-1');
    expect(fn () => $billing->createOneOffCharge($customer, 'Different charge', 20000, $issued, 'install-1'))
        ->toThrow(ValidationException::class);
    $billing->grantCredit($customer, 5000, 'Service adjustment', 'credit-1');
    $application = $billing->applyCreditToInvoice($invoice, null, 'apply-1');
    $applicationRetry = $billing->applyCreditToInvoice($invoice, null, 'apply-1');
    $billing->grantCredit($customer, 7000, 'Courtesy credit', 'credit-2');
    $secondApplication = $billing->applyCreditToInvoice($invoice->fresh(), null, 'apply-2');
    $available = (int) CreditLedgerEntry::query()->where('customer_id', $customer->id)->sum('amount_minor');

    expect($retry->id)->toBe($invoice->id)
        ->and($invoice->total_minor)->toBe(11600)
        ->and($application->amount_minor)->toBe(-5000)
        ->and($applicationRetry->id)->toBe($application->id)
        ->and($secondApplication->amount_minor)->toBe(-6600)
        ->and($invoice->fresh()->balance_minor)->toBe(0)
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($available)->toBe(400);
});

it('isolates invoices across tenants and keeps issued line and credit records immutable', function (): void {
    [, , $customer] = billingFixture();
    $invoice = app(BillingService::class)->createOneOffCharge($customer, 'Fee', 1000, CarbonImmutable::parse('2026-04-01'), 'fee-1');
    $line = $invoice->lines->first();
    app(TenantContext::class)->clear();
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other-isp', 'status' => 'active']);
    app(TenantContext::class)->set($other);
    expect(Invoice::query()->find($invoice->id))->toBeNull();
    app(TenantContext::class)->set(Tenant::where('slug', 'billing-isp')->firstOrFail());

    expect(fn () => $line->update(['description' => 'changed']))->toThrow(LogicException::class, 'Issued invoice lines are immutable.');
    expect(DB::table('invoices')->where('id', $invoice->id)->value('total_minor'))->toBe(1000);
});

it('runs the recurring billing command repeatedly without creating duplicate periods', function (): void {
    [$tenant, , $customer] = billingFixture(0, 250000, 'monthly');
    $customer->update(['billing_day' => 3]);
    app(TenantContext::class)->set($tenant);
    $daily = Plan::create(['name' => 'Daily', 'price_minor' => 1000, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'daily', 'active' => true]);
    $weekly = Plan::create(['name' => 'Weekly', 'price_minor' => 5000, 'currency' => 'KES', 'speed_down_kbps' => 5000, 'speed_up_kbps' => 1000, 'billing_period' => 'weekly', 'active' => true]);
    Customer::create(['plan_id' => $daily->id, 'account_number' => 'ACC-2', 'name' => 'Daily subscriber', 'phone' => '+254700000002', 'pppoe_username' => 'daily-user', 'pppoe_password' => 'subscriber-secret', 'status' => 'active', 'billing_day' => 1, 'billing_anchor_date' => '2026-11-01']);
    Customer::create(['plan_id' => $weekly->id, 'account_number' => 'ACC-3', 'name' => 'Weekly subscriber', 'phone' => '+254700000003', 'pppoe_username' => 'weekly-user', 'pppoe_password' => 'subscriber-secret', 'status' => 'active', 'billing_day' => 1, 'billing_anchor_date' => '2026-11-01']);
    app(TenantContext::class)->clear();

    Artisan::call('billing:run', ['--date' => '2026-11-03']);
    Artisan::call('billing:run', ['--date' => '2026-11-03']);
    Artisan::call('billing:run', ['--date' => '2026-11-08']);
    app(TenantContext::class)->set($tenant);

    expect(Invoice::query()->count())->toBe(4)
        ->and(Invoice::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(Invoice::query()->where('customer_id', $customer->id)->first()->period_start->toDateString())->toBe('2026-11-03')
        ->and(Invoice::query()->where('customer_id', $customer->id)->first()->period_end->toDateString())->toBe('2026-12-02')
        ->and(Invoice::query()->where('customer_id', '!=', $customer->id)->whereNotNull('period_end')->count())->toBe(3);
});
