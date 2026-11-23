<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function billingAdminFixture(string $role = 'owner'): array
{
    $tenant = Tenant::create(['name' => 'Admin Billing ISP', 'slug' => 'admin-billing', 'status' => 'active', 'currency' => 'KES']);
    $user = User::factory()->create();
    if (in_array($role, ['owner', 'admin'], true)) {
        $user->forceFill(['two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'two_factor_confirmed_at' => now()])->save();
    }
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Home', 'price_minor' => 250000, 'currency' => 'KES', 'speed_down_kbps' => 20000, 'speed_up_kbps' => 5000, 'billing_period' => 'monthly', 'active' => true]);
    $customer = Customer::create(['plan_id' => $plan->id, 'account_number' => 'ADM-1', 'name' => 'Demo Customer', 'phone' => '+254700000001', 'pppoe_username' => 'admin-customer', 'pppoe_password' => 'subscriber-secret', 'status' => 'active', 'billing_day' => 1, 'billing_anchor_date' => now()->toDateString()]);
    app(TenantContext::class)->clear();

    return [$tenant, $user, $plan, $customer];
}

it('lets tenant admins configure VAT, issue charges, grant credit, and render billing pages', function (): void {
    [$tenant, $owner, , $customer] = billingAdminFixture();
    $session = ['tenant_id' => $tenant->id, 'two_factor_passed' => true];

    $this->actingAs($owner)->withSession($session)->put(route('admin.billing.settings.update'), ['vat_rate_basis_points' => 1600])->assertRedirect();
    $this->actingAs($owner)->withSession($session)->post(route('admin.billing.charges.store'), [
        'customer_id' => $customer->id, 'description' => 'Installation', 'amount_minor' => 10000, 'idempotency_key' => 'admin-install-1',
    ])->assertRedirect();
    $invoiceId = DB::table('invoices')->value('id');

    $this->actingAs($owner)->withSession($session)->get(route('admin.billing.index'))->assertOk();
    $this->actingAs($owner)->withSession($session)->get(route('admin.billing.show', $invoiceId))->assertOk();
    $this->actingAs($owner)->withSession($session)->get(route('admin.customers.show', $customer->id))->assertOk();
    $this->actingAs($owner)->withSession($session)->post(route('admin.billing.customers.credits', $customer), [
        'amount_minor' => 11600, 'description' => 'Support adjustment', 'idempotency_key' => 'admin-credit-1',
    ])->assertRedirect();
    $this->actingAs($owner)->withSession($session)->post(route('admin.billing.invoices.credits', $invoiceId), [
        'idempotency_key' => 'admin-apply-1',
    ])->assertRedirect();

    expect(DB::table('tenants')->where('id', $tenant->id)->value('vat_rate_basis_points'))->toBe(1600)
        ->and(DB::table('invoices')->where('id', $invoiceId)->value('total_minor'))->toBe(11600)
        ->and(DB::table('invoices')->where('id', $invoiceId)->value('balance_minor'))->toBe(0);
});

it('allows support staff to view invoices but denies billing changes', function (): void {
    [$tenant, $agent] = billingAdminFixture('agent');
    $session = ['tenant_id' => $tenant->id, 'two_factor_passed' => true];

    $this->actingAs($agent)->withSession($session)->get(route('admin.billing.index'))->assertOk();
    $this->actingAs($agent)->withSession($session)->get(route('admin.billing.settings'))->assertForbidden();
    $this->actingAs($agent)->withSession($session)->get(route('admin.billing.charges.create'))->assertForbidden();
});
