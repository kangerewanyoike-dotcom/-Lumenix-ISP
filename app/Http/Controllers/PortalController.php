<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\DarajaAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RadiusAccountingSession;
use App\Services\DarajaClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PortalController extends Controller
{
    public function dashboard(Request $request, TenantContext $context): View
    {
        $account = $request->attributes->get('customer_portal_account');
        $customer = $account->customer;
        $monthStart = CarbonImmutable::now($context->current()->timezone ?? config('app.timezone'))->startOfMonth();
        $sessions = RadiusAccountingSession::query()->where('customer_id', $customer->id)->where('started_at', '>=', $monthStart)->get();

        return view('portal.dashboard', [
            'tenant' => $context->current(), 'customer' => $customer->load('plan'),
            'invoices' => Invoice::query()->where('customer_id', $customer->id)->latest('issued_at')->limit(12)->get(),
            'payments' => Payment::query()->where('customer_id', $customer->id)->where('status', 'allocated')->latest('paid_at')->limit(12)->get(),
            'usageInputBytes' => (int) $sessions->sum('input_octets'), 'usageOutputBytes' => (int) $sessions->sum('output_octets'),
            'activeSessions' => $sessions->whereNull('stopped_at')->count(),
        ]);
    }

    public function invoice(Request $request, string $invoice, TenantContext $context): View
    {
        $account = $request->attributes->get('customer_portal_account');
        $invoice = Invoice::query()->with('lines')->where('customer_id', $account->customer_id)->findOrFail($invoice);

        return view('portal.invoice', [
            'tenant' => $context->current(), 'customer' => $account->customer, 'invoice' => $invoice,
            'darajaAccounts' => DarajaAccount::query()->where('active', true)->get(), 'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function pay(Request $request, string $invoice, DarajaClient $daraja, TenantContext $context): RedirectResponse
    {
        $account = $request->attributes->get('customer_portal_account');
        $data = $request->validate([
            'daraja_account_id' => ['required', 'integer', 'exists:daraja_accounts,id,tenant_id,'.$context->id()],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $invoice = Invoice::query()->where('customer_id', $account->customer_id)->findOrFail($invoice);
        if ($invoice->balance_minor < 1) {
            return back()->with('status', 'This invoice is already paid.');
        }
        $customer = Customer::query()->findOrFail($account->customer_id);
        $phone = ltrim($customer->phone, '+');
        $daraja->initiate(DarajaAccount::query()->findOrFail($data['daraja_account_id']), $invoice, $phone, 'portal:'.$account->id.':'.$data['idempotency_key']);

        return back()->with('status', 'M-Pesa payment request sent. This invoice will update when payment is confirmed.');
    }
}
