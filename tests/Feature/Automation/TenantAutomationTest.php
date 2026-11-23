<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\MessagingAccount;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\AutomationService;
use App\Services\BillingService;
use App\Services\FakeRouterConnection;
use App\Services\RouterConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

afterEach(fn () => CarbonImmutable::setTestNow());

function automationFixture(bool $enabled = true, int $grace = 3): array
{
    CarbonImmutable::setTestNow('2026-11-21 09:00:00');
    $tenant = Tenant::create(['name' => 'Automation ISP', 'slug' => 'automation-isp', 'status' => 'active', 'currency' => 'KES', 'automation_enabled' => $enabled, 'grace_period_days' => $grace, 'reminder_days' => [3, 1, 0]]);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Automation plan', 'price_minor' => 10000, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'monthly', 'active' => true]);
    $customer = Customer::create(['plan_id' => $plan->id, 'account_number' => 'AUTO-1', 'name' => 'Automation Customer', 'phone' => '+254700000001', 'pppoe_username' => 'automation-customer', 'pppoe_password' => 'test-password', 'status' => 'active', 'billing_day' => 1]);
    $invoice = app(BillingService::class)->createOneOffCharge($customer, 'Monthly access', 10000, CarbonImmutable::now(), 'automation-invoice');

    return [$tenant, $customer, $invoice];
}

function configureReminder(): void
{
    MessagingAccount::create(['environment' => 'sandbox', 'username' => 'sandbox', 'api_key' => 'fake-api-key', 'active' => true]);
    NotificationTemplate::create(['event_key' => 'invoice_reminder', 'channel' => 'sms', 'body' => 'Hi {{customer_name}}, invoice {{invoice_number}} for {{amount}} KES is due {{due_date}}.', 'active' => true]);
}

it('sends tenant reminders once for each scheduled due-date offset', function (): void {
    [$tenant, , $invoice] = automationFixture();
    configureReminder();
    Http::fake(['api.sandbox.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'messageId' => 'AT-1']]]], 200)]);

    $exitCode = Artisan::call('automation:run', ['--date' => '2026-11-25']);
    expect($exitCode)->toBe(0, Artisan::output());
    $exitCode = Artisan::call('automation:run', ['--date' => '2026-11-25']);
    app(TenantContext::class)->set($tenant);
    expect($exitCode)->toBe(0, Artisan::output())
        ->and(NotificationDelivery::query()->where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('invoice_id', $invoice->id)->first()->status)->toBe('sent');
    Http::assertSentCount(1);
});

it('retries a failed SMS delivery and records the provider outcome without storing message text', function (): void {
    [$tenant, , $invoice] = automationFixture();
    configureReminder();
    Http::fakeSequence()->push(['SMSMessageData' => ['Recipients' => [['statusCode' => 405]]]], 200)
        ->push(['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'messageId' => 'AT-2']]]], 200);
    Artisan::call('automation:run', ['--date' => '2026-11-25']);
    app(TenantContext::class)->set($tenant);
    $delivery = NotificationDelivery::query()->where('invoice_id', $invoice->id)->firstOrFail();
    expect($delivery->status)->toBe('failed')->and($delivery->attempts)->toBe(1);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(6));
    Artisan::call('automation:run', ['--date' => '2026-11-25']);
    app(TenantContext::class)->set($tenant);
    expect($delivery->fresh()->status)->toBe('sent')
        ->and($delivery->fresh()->attempts)->toBe(2)
        ->and($delivery->fresh()->destination)->toBe('+254700000001')
        ->and(DB::table('notification_deliveries')->where('id', $delivery->id)->value('destination'))->not->toBe('+254700000001');
});

it('suspends overdue customers once and restores them after the overdue balance is paid', function (): void {
    [$tenant, $customer, $invoice] = automationFixture(true, 0);
    $router = Router::create(['name' => 'Automation edge', 'connection_mode' => 'vpn', 'endpoint' => '10.10.0.5', 'radius_secret' => 'test-router-secret', 'api_port' => 8729, 'use_tls' => true, 'status' => 'active']);
    $driver = new FakeRouterConnection([true]);
    app()->instance(RouterConnection::class, $driver);
    $date = CarbonImmutable::parse('2026-12-01');

    $first = app(AutomationService::class)->runForTenantDate($tenant, $date);
    app(TenantContext::class)->set($tenant);
    $second = app(AutomationService::class)->runForTenantDate($tenant, $date);
    expect($first['suspended'])->toBe(1)
        ->and($second['suspended'])->toBe(0)
        ->and($customer->fresh()->status)->toBe('suspended')
        ->and($customer->fresh()->suspension_reason)->toBe('payment_overdue')
        ->and(count($driver->calls))->toBe(1);

    $invoice->update(['balance_minor' => 0, 'status' => 'paid']);
    expect(app(AutomationService::class)->restoreAfterInvoicePayment($invoice))->toBeTrue()
        ->and($customer->fresh()->status)->toBe('active')
        ->and($customer->fresh()->suspension_reason)->toBeNull()
        ->and(DB::table('radcheck')->where('username', $customer->pppoe_username)->value('attribute'))->toBe('Cleartext-Password');
});

it('does not suspend manually suspended customers or tenants with automation disabled', function (): void {
    [$tenant, $customer] = automationFixture(false, 0);
    $customer->update(['status' => 'suspended']);
    $stats = app(AutomationService::class)->runForTenantDate($tenant, CarbonImmutable::parse('2026-12-20'));
    expect($stats['suspended'])->toBe(0)->and($customer->fresh()->suspension_reason)->toBeNull();
});

it('limits automation settings to tenant admins and accepts only supported template variables', function (): void {
    $tenant = Tenant::create(['name' => 'Automation Admin ISP', 'slug' => 'automation-admin', 'status' => 'active']);
    $owner = User::factory()->create();
    $owner->forceFill(['two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'two_factor_confirmed_at' => now()])->save();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $session = ['tenant_id' => $tenant->id, 'two_factor_passed' => true];

    $this->actingAs($owner)->withSession($session)->get(route('admin.automation.index'))->assertOk();
    $this->actingAs($owner)->withSession($session)->put(route('admin.automation.settings'), ['automation_enabled' => 1, 'grace_period_days' => 5, 'reminder_days' => [3, 0]])->assertRedirect();
    $this->actingAs($owner)->withSession($session)->put(route('admin.automation.template'), ['body' => 'Hello {{customer_email}}'])->assertSessionHasErrors('body');
    $this->actingAs($owner)->withSession($session)->put(route('admin.automation.template'), ['body' => 'Hello {{customer_name}}. Ref {{account_number}}'])->assertRedirect();

    expect((bool) Tenant::findOrFail($tenant->id)->automation_enabled)->toBeTrue()
        ->and(DB::table('notification_templates')->where('tenant_id', $tenant->id)->value('body'))->toBe('Hello {{customer_name}}. Ref {{account_number}}')
        ->and(DB::table('audit_logs')->where('tenant_id', $tenant->id)->where('action', 'automation.template_updated')->exists())->toBeTrue();
});
