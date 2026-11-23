<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;

function privacyFixture(): array
{
    $tenant = Tenant::create(['name' => 'Privacy ISP', 'slug' => 'privacy-isp', 'status' => 'active']);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Privacy plan', 'price_minor' => 10000, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'monthly', 'active' => true]);
    $customer = Customer::create(['plan_id' => $plan->id, 'account_number' => 'PRIV-1', 'name' => 'Private Subscriber', 'phone' => '+254700000001', 'email' => 'private@example.test', 'address' => 'Secret address', 'pppoe_username' => 'private-user', 'pppoe_password' => 'private-pppoe-secret', 'status' => 'active', 'billing_day' => 1]);
    $invoice = Invoice::create(['customer_id' => $customer->id, 'number' => 'PRIV-INV-1', 'status' => 'issued', 'issued_at' => '2026-10-01', 'due_at' => '2026-10-31', 'currency' => 'KES', 'subtotal_minor' => 10000, 'tax_minor' => 0, 'total_minor' => 10000, 'balance_minor' => 10000, 'idempotency_key' => 'priv-invoice-1']);
    $portal = CustomerPortalAccount::create(['customer_id' => $customer->id, 'email' => 'private@example.test', 'password' => Hash::make('portal-password-123'), 'active' => true]);
    app(TenantContext::class)->clear();
    $user = User::create(['name' => 'Privacy Owner', 'email' => 'owner@example.test', 'password' => 'test-password']);
    $user->forceFill(['two_factor_secret' => 'GEZDGNBVGT3TQOJQGEZDGNBVGT3TQOJQ', 'two_factor_confirmed_at' => now()])->save();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);

    return [$tenant, $customer, $invoice, $portal, $user];
}

it('exports customer data to an audited CSV without exporting service passwords', function (): void {
    [$tenant, $customer, $invoice, , $user] = privacyFixture();
    $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'two_factor_passed' => true])
        ->get(route('admin.customers.export', $customer->id));

    $response->assertDownload('customer-'.$customer->id.'-export.csv');
    expect($response->streamedContent())->toContain('Private Subscriber', 'PRIV-INV-1')
        ->and($response->streamedContent())->not->toContain('private-pppoe-secret')
        ->and(AuditLog::query()->where('action', 'customer.exported')->exists())->toBeTrue();
});

it('anonymizes personal identifiers and disables portal access while preserving financial history', function (): void {
    [$tenant, $customer, $invoice, $portal, $user] = privacyFixture();
    $oldPasswordHash = $portal->password;

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'two_factor_passed' => true])
        ->delete(route('admin.customers.destroy', $customer->id))->assertRedirect(route('admin.customers.index'));

    app(TenantContext::class)->set($tenant);
    $archived = Customer::withTrashed()->findOrFail($customer->id);
    expect($archived->trashed())->toBeTrue()
        ->and($archived->name)->toBe('Deleted customer '.$customer->id)
        ->and($archived->email)->toBeNull()
        ->and($archived->address)->toBeNull()
        ->and($archived->pppoe_username)->toBe('deleted-'.$customer->id)
        ->and($invoice->fresh())->not->toBeNull()
        ->and($portal->fresh()->active)->toBeFalse()
        ->and($portal->fresh()->password)->not->toBe($oldPasswordHash)
        ->and(AuditLog::query()->where('action', 'customer.anonymized_and_archived')->exists())->toBeTrue();
});

it('filters secret-like values from audit metadata', function (): void {
    app(AuditLogger::class)->record('security.test', null, null, null, ['amount_minor' => 500, 'password' => 'do-not-log', 'email' => 'private@example.test']);

    expect(AuditLog::query()->where('action', 'security.test')->first()->metadata)->toBe(['amount_minor' => 500]);
});
