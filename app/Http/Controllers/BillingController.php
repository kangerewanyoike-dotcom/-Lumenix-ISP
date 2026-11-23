<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\DarajaAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\AutomationService;
use App\Services\BillingService;
use App\Services\DarajaClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class BillingController extends Controller
{
    public function index(AuditLogger $audit, TenantContext $context): View
    {
        $this->authorize('viewAny', Invoice::class);
        $audit->record('invoice.listed', (int) $context->id(), Invoice::class);

        return view('admin.billing.index', ['invoices' => Invoice::query()->with('customer')->latest('issued_at')->paginate(25)]);
    }

    public function show(string $invoice, AuditLogger $audit, TenantContext $context): View
    {
        $invoice = Invoice::query()->with(['customer', 'lines'])->findOrFail($invoice);
        $this->authorize('view', $invoice);
        $audit->record('invoice.viewed', (int) $context->id(), Invoice::class, (string) $invoice->id);

        return view('admin.billing.show', ['invoice' => $invoice, 'tenant' => $context->current(), 'darajaAccounts' => DarajaAccount::query()->where('active', true)->get()]);
    }

    public function createCharge(): View
    {
        $this->authorize('create', Invoice::class);

        return view('admin.billing.charge', ['customers' => Customer::query()->where('status', '!=', 'disconnected')->orderBy('name')->get(), 'key' => (string) Str::uuid()]);
    }

    public function storeCharge(Request $request, BillingService $billing, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $tenantId = $context->id();
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id,tenant_id,'.$tenantId],
            'description' => ['required', 'string', 'max:200'], 'amount_minor' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $invoice = $billing->createOneOffCharge($customer, $data['description'], (int) $data['amount_minor'], CarbonImmutable::now($context->current()->timezone ?? config('app.timezone')), $data['idempotency_key']);
        $audit->record('invoice.one_off_created', (int) $tenantId, Invoice::class, (string) $invoice->id);

        return redirect()->route('admin.billing.show', $invoice)->with('status', 'One-off invoice issued.');
    }

    public function grantCredit(Request $request, string $customer, BillingService $billing, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $customer = Customer::query()->findOrFail($customer);
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'description' => ['required', 'string', 'max:200'], 'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $entry = $billing->grantCredit($customer, (int) $data['amount_minor'], $data['description'], $data['idempotency_key']);
        $audit->record('customer.credit_granted', (int) $context->id(), Customer::class, (string) $customer->id);

        return back()->with('status', 'Credit added to the customer ledger.');
    }

    public function changePlan(Request $request, string $customer, BillingService $billing, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $customer = Customer::query()->findOrFail($customer);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id,tenant_id,'.$context->id()],
            'effective_at' => ['required', 'date', 'before_or_equal:today'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->active, 422, 'The selected plan is inactive.');
        $timezone = $context->current()->timezone ?? config('app.timezone');
        $result = $billing->changePlan(
            $customer, $plan, CarbonImmutable::parse($data['effective_at'], $timezone),
            CarbonImmutable::parse($data['period_start'], $timezone), CarbonImmutable::parse($data['period_end'], $timezone), $data['idempotency_key'],
        );
        $audit->record('customer.plan_changed', (int) $context->id(), Customer::class, (string) $customer->id, ['adjustment_minor' => $result['adjustment_minor']]);

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Plan change saved with a prorated adjustment of '.abs($result['adjustment_minor']).' minor units.');
    }

    public function changeBillingDay(Request $request, string $customer, BillingService $billing, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $customer = Customer::query()->findOrFail($customer);
        $data = $request->validate([
            'billing_day' => ['required', 'integer', 'min:1', 'max:28'],
            'cycle_start' => ['required', 'date'], 'old_cycle_end' => ['required', 'date', 'after_or_equal:cycle_start'],
            'new_cycle_end' => ['required', 'date', 'after_or_equal:cycle_start'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $timezone = $context->current()->timezone ?? config('app.timezone');
        $result = $billing->changeBillingDay(
            $customer, (int) $data['billing_day'], CarbonImmutable::parse($data['cycle_start'], $timezone),
            CarbonImmutable::parse($data['old_cycle_end'], $timezone), CarbonImmutable::parse($data['new_cycle_end'], $timezone), $data['idempotency_key'],
        );
        $audit->record('customer.billing_day_changed', (int) $context->id(), Customer::class, (string) $customer->id, ['billing_day' => (int) $data['billing_day'], 'adjustment_minor' => $result['adjustment_minor']]);

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Billing day changed with a prorated adjustment of '.abs($result['adjustment_minor']).' minor units.');
    }

    public function applyCredit(Request $request, string $invoice, BillingService $billing, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($invoice);
        $this->authorize('view', $invoice);
        $this->authorize('create', Invoice::class);
        $data = $request->validate([
            'amount_minor' => ['nullable', 'integer', 'min:1', 'max:99999999999'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $entry = $billing->applyCreditToInvoice($invoice, isset($data['amount_minor']) ? (int) $data['amount_minor'] : null, $data['idempotency_key']);
        if ($entry) {
            $audit->record('invoice.credit_applied', (int) $context->id(), Invoice::class, (string) $invoice->id, ['amount_minor' => abs($entry->amount_minor)]);
        }

        return back()->with('status', $entry ? 'Customer credit applied.' : 'No available customer credit to apply.');
    }

    public function settings(): View
    {
        $this->authorize('create', Invoice::class);

        return view('admin.billing.settings', ['tenant' => app(TenantContext::class)->current(), 'accounts' => DarajaAccount::query()->orderBy('name')->get(), 'unmatched' => Payment::query()->where('status', 'unmatched')->latest('paid_at')->get()]);
    }

    public function storeDarajaAccount(Request $request, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'short_code' => ['required', 'string', 'max:32'], 'account_type' => ['required', Rule::in(['paybill', 'till'])],
            'consumer_key' => ['required', 'string', 'max:500'], 'consumer_secret' => ['required', 'string', 'max:500'],
            'passkey' => ['nullable', 'string', 'max:500'],
        ]);
        $data['callback_token'] = Str::random(48);
        $data['callback_token_hash'] = hash('sha256', $data['callback_token']);
        DarajaAccount::create($data);
        $audit->record('daraja.account_created', (int) $context->id(), DarajaAccount::class, null, ['environment' => $data['environment'], 'short_code' => $data['short_code']]);

        return back()->with('status', 'Daraja account saved. Its callback URLs are shown below.');
    }

    public function allocatePayment(Request $request, string $payment, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $payment = Payment::query()->where('status', 'unmatched')->findOrFail($payment);
        $data = $request->validate(['customer_id' => ['required', 'integer', 'exists:customers,id,tenant_id,'.$context->id()], 'invoice_id' => ['nullable', 'integer', 'exists:invoices,id,tenant_id,'.$context->id()]]);
        $actorId = (int) $request->user()->id;
        DB::transaction(function () use ($payment, $data, $context, $actorId): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($locked->status === 'unmatched', 409);
            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            $invoice = isset($data['invoice_id']) ? Invoice::query()->lockForUpdate()->where('customer_id', $customer->id)->findOrFail($data['invoice_id']) : Invoice::query()->lockForUpdate()->where('customer_id', $customer->id)->where('balance_minor', '>', 0)->orderBy('issued_at')->first();
            $applied = $invoice ? min($locked->amount_minor, $invoice->balance_minor) : 0;
            if ($invoice && $applied > 0) {
                $invoice->balance_minor -= $applied;
                $invoice->status = $invoice->balance_minor === 0 ? 'paid' : 'partially_paid';
                $invoice->save();
                app(AutomationService::class)->restoreAfterInvoicePayment($invoice);
            }
            DB::table('payment_allocations')->insert(['tenant_id' => $context->id(), 'payment_id' => $locked->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice?->id, 'amount_minor' => $applied, 'allocated_by' => $actorId, 'created_at' => now()]);
            $locked->update(['customer_id' => $customer->id, 'invoice_id' => $invoice?->id, 'status' => 'allocated']);
            if ($locked->amount_minor > $applied) {
                app(BillingService::class)->grantCredit($customer, $locked->amount_minor - $applied, 'M-Pesa allocation '.$locked->receipt, 'mpesa-overpayment:'.$locked->daraja_account_id.':'.$locked->receipt);
            }
        });
        $audit->record('payment.manually_allocated', (int) $context->id(), Payment::class, (string) $payment->id, ['customer_id' => (int) $data['customer_id']]);

        return back()->with('status', 'Payment allocation recorded.');
    }

    public function initiatePayment(Request $request, string $invoice, DarajaClient $daraja, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $invoice = Invoice::query()->findOrFail($invoice);
        $data = $request->validate([
            'daraja_account_id' => ['required', 'integer', 'exists:daraja_accounts,id,tenant_id,'.$context->id()],
            'phone' => ['required', 'regex:/^\+?254[17][0-9]{8}$/'], 'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $data['phone'] = ltrim($data['phone'], '+');
        $daraja->initiate(DarajaAccount::query()->findOrFail($data['daraja_account_id']), $invoice, $data['phone'], $data['idempotency_key']);

        return back()->with('status', 'STK Push requested. The invoice updates after a successful callback.');
    }

    public function updateSettings(Request $request, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $data = $request->validate(['vat_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000']]);
        $tenant = $context->current();
        $tenant->update($data);
        $audit->record('billing.vat_rate_updated', (int) $tenant->id, Tenant::class, (string) $tenant->id, ['vat_rate_basis_points' => (int) $tenant->vat_rate_basis_points]);

        return back()->with('status', 'VAT rate saved. New invoices will use this rate; issued invoices keep their saved tax amounts.');
    }
}
