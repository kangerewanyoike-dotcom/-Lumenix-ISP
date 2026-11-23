<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function portalTestCustomer(Tenant $tenant, string $suffix): Customer
{
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Portal '.$suffix, 'price_minor' => 150000, 'currency' => 'KES', 'speed_down_kbps' => 10000, 'speed_up_kbps' => 2000, 'billing_period' => 'monthly', 'active' => true]);

    return Customer::create(['plan_id' => $plan->id, 'account_number' => 'PORTAL-'.$suffix, 'name' => 'Portal '.$suffix, 'phone' => '+254700000001', 'pppoe_username' => 'portal-'.$suffix, 'pppoe_password' => 'secret', 'status' => 'active', 'billing_day' => 1]);
}

function portalTestInvoice(Customer $customer, string $number): Invoice
{
    return Invoice::create(['customer_id' => $customer->id, 'number' => $number, 'status' => 'issued', 'issued_at' => '2026-10-01', 'due_at' => '2026-10-31', 'currency' => 'KES', 'subtotal_minor' => 10000, 'tax_minor' => 0, 'total_minor' => 10000, 'balance_minor' => 10000, 'idempotency_key' => $number]);
}

it('keeps the subscriber login visually separate from a signed-in staff layout', function (): void {
    $staff = User::factory()->create();

    $this->actingAs($staff)->get(route('portal.login'))
        ->assertOk()->assertSee('Subscriber portal')->assertSee('Sign in securely')->assertDontSee('Sign out');
});

it('limits a portal login to its linked customer and tenant invoices', function (): void {
    $tenantA = Tenant::create(['name' => 'A ISP', 'slug' => 'a-portal', 'status' => 'active']);
    $customerA = portalTestCustomer($tenantA, 'A');
    $invoiceA = portalTestInvoice($customerA, 'A-INV');
    CustomerPortalAccount::create(['customer_id' => $customerA->id, 'email' => 'subscriber@example.test', 'password' => Hash::make('correct horse battery'), 'active' => true]);
    app(TenantContext::class)->clear();

    $tenantB = Tenant::create(['name' => 'B ISP', 'slug' => 'b-portal', 'status' => 'active']);
    $customerB = portalTestCustomer($tenantB, 'B');
    $invoiceB = portalTestInvoice($customerB, 'B-INV');
    app(TenantContext::class)->clear();

    $this->post(route('portal.login.store'), ['email' => 'subscriber@example.test', 'password' => 'correct horse battery'])->assertRedirect(route('portal.dashboard'));
    $this->get(route('portal.invoices.show', $invoiceA))->assertOk()->assertSee('A-INV');
    $this->get(route('portal.invoices.show', $invoiceB))->assertNotFound();
});
