<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\DarajaAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function darajaFixture(int $balanceMinor = 10000): array
{
    $tenant = Tenant::create(['name' => 'Payments ISP', 'slug' => 'payments-isp', 'status' => 'active', 'currency' => 'KES']);
    app(TenantContext::class)->set($tenant);
    $plan = Plan::create(['name' => 'Payment plan', 'price_minor' => $balanceMinor, 'currency' => 'KES', 'speed_down_kbps' => 1000, 'speed_up_kbps' => 500, 'billing_period' => 'monthly', 'active' => true]);
    $customer = Customer::create(['plan_id' => $plan->id, 'account_number' => 'ACC-PAY', 'name' => 'Payment customer', 'phone' => '+254700000001', 'pppoe_username' => 'payment-user', 'pppoe_password' => 'test-secret', 'status' => 'active', 'billing_day' => 1]);
    $invoice = app(BillingService::class)->createOneOffCharge($customer, 'Test service', $balanceMinor, CarbonImmutable::parse('2026-11-21'), 'test-invoice-'.$balanceMinor);
    $token = Str::random(48);
    $account = DarajaAccount::create(['name' => 'Sandbox', 'environment' => 'sandbox', 'short_code' => '600000', 'account_type' => 'paybill', 'consumer_key' => 'test-key', 'consumer_secret' => 'test-secret', 'passkey' => 'test-passkey', 'callback_token' => $token, 'callback_token_hash' => hash('sha256', $token), 'active' => true]);

    return [$tenant, $customer, $invoice, $account, $token];
}

function stkCallback(string $checkout, string $receipt, string $amount): array
{
    return ['Body' => ['stkCallback' => ['MerchantRequestID' => 'merchant-1', 'CheckoutRequestID' => $checkout, 'ResultCode' => 0, 'ResultDesc' => 'Success', 'CallbackMetadata' => ['Item' => [['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt]]]]]];
}

it('processes duplicate STK callbacks once and preserves a late successful callback', function (): void {
    [, , $invoice, $account, $token] = darajaFixture();
    $request = PaymentRequest::create(['daraja_account_id' => $account->id, 'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'idempotency_key' => 'stk-late', 'checkout_request_id' => 'ws_CO_late', 'phone' => '254700000001', 'amount_minor' => 10000, 'status' => 'failed']);
    $payload = stkCallback('ws_CO_late', 'QK123ABC', '100');
    $this->postJson(route('daraja.stk.callback', $token), $payload)->assertOk();
    $this->postJson(route('daraja.stk.callback', $token), $payload)->assertOk();
    expect(Payment::query()->count())->toBe(1)
        ->and(PaymentRequest::query()->findOrFail($request->id)->status)->toBe('succeeded')
        ->and(Invoice::query()->findOrFail($invoice->id)->balance_minor)->toBe(0);
});

it('leaves a partial payment balance for an underpayment', function (): void {
    [, , $invoice, $account, $token] = darajaFixture();
    PaymentRequest::create(['daraja_account_id' => $account->id, 'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'idempotency_key' => 'stk-under', 'checkout_request_id' => 'ws_CO_under', 'phone' => '254700000001', 'amount_minor' => 10000]);
    $this->postJson(route('daraja.stk.callback', $token), stkCallback('ws_CO_under', 'QKUNDER1', '40'))->assertOk();
    expect(Invoice::query()->findOrFail($invoice->id)->balance_minor)->toBe(6000)
        ->and(Invoice::query()->findOrFail($invoice->id)->status)->toBe('partially_paid');
});

it('credits the customer for an overpayment above the invoice balance', function (): void {
    [, $customer, $invoice, $account, $token] = darajaFixture();
    PaymentRequest::create(['daraja_account_id' => $account->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'idempotency_key' => 'stk-over', 'checkout_request_id' => 'ws_CO_over', 'phone' => '254700000001', 'amount_minor' => 10000]);
    $this->postJson(route('daraja.stk.callback', $token), stkCallback('ws_CO_over', 'QKOVER01', '120'))->assertOk();
    expect(Invoice::query()->findOrFail($invoice->id)->balance_minor)->toBe(0)
        ->and((int) CreditLedgerEntry::query()->where('customer_id', $customer->id)->sum('amount_minor'))->toBe(2000);
});

it('rejects C2B callbacks with the wrong shortcode and queues unknown account references', function (): void {
    [, , , $account, $token] = darajaFixture();
    $this->postJson(route('daraja.c2b.callback', $token), ['TransID' => 'QKC2B001', 'TransAmount' => '100.00', 'ShortCode' => '999999', 'BillRefNumber' => 'UNKNOWN'])->assertStatus(400);
    $this->postJson(route('daraja.c2b.callback', $token), ['TransID' => 'QKC2B001', 'TransAmount' => '100.00', 'ShortCode' => $account->short_code, 'BillRefNumber' => 'UNKNOWN'])->assertOk();
    expect(Payment::query()->where('status', 'unmatched')->count())->toBe(1)
        ->and(Payment::query()->where('receipt', 'QKC2B001')->firstOrFail()->amount_minor)->toBe(10000);
});

it('accepts the configured C2B validation callback without creating a payment', function (): void {
    [, , , , $token] = darajaFixture();
    $this->postJson(route('daraja.c2b.validation', $token), ['TransID' => 'NOT-A-CONFIRMATION'])->assertOk()->assertJson(['ResultCode' => 0]);
    expect(Payment::query()->count())->toBe(0);
});
