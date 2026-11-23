<?php

namespace App\Jobs;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\DarajaAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Tenant;
use App\Services\AutomationService;
use App\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class ProcessPaymentCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $darajaAccountId,
        public readonly string $eventType,
        public readonly array $payload,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function handle(TenantContext $context): void
    {
        $account = DarajaAccount::withoutGlobalScopes()->findOrFail($this->darajaAccountId);
        $context->set(Tenant::query()->findOrFail($account->tenant_id));
        try {
            DB::transaction(function () use ($account): void {
                if ($this->eventType === 'stk') {
                    $this->processStk($account);
                } elseif ($this->eventType === 'c2b') {
                    $this->processC2b($account);
                } else {
                    throw new \LogicException('Unsupported payment callback type.');
                }
            }, 3);
        } finally {
            $context->clear();
        }
    }

    private function processStk(DarajaAccount $account): void
    {
        $data = $this->payload;
        $request = $data['Body']['stkCallback'] ?? [];
        $paymentRequest = PaymentRequest::query()->where('daraja_account_id', $account->id)->where('checkout_request_id', $request['CheckoutRequestID'] ?? '')->lockForUpdate()->first();
        if (! $paymentRequest) {
            return;
        }
        $success = (string) ($request['ResultCode'] ?? '') === '0';
        $paymentRequest->update(['status' => $success ? 'succeeded' : 'failed', 'provider_result_code' => (string) $request['ResultCode'], 'completed_at' => now()]);
        $key = 'stk:'.$request['CheckoutRequestID'];
        DB::table('payment_callback_events')->insertOrIgnore(['tenant_id' => $account->tenant_id, 'event_key' => $key, 'payload_hash' => hash('sha256', json_encode($request)), 'event_type' => 'stk', 'result' => $success ? 'succeeded' : 'failed', 'created_at' => now()]);
        if (! $success || Payment::query()->where('payment_request_id', $paymentRequest->id)->exists()) {
            return;
        }
        $items = collect($request['CallbackMetadata']['Item'] ?? [])->keyBy('Name');
        $receipt = (string) ($items->get('MpesaReceiptNumber')['Value'] ?? '');
        $amount = $this->minorUnits((string) ($items->get('Amount')['Value'] ?? '0'));
        if ($receipt === '' || $amount < 1) {
            return;
        }
        $invoice = Invoice::query()->lockForUpdate()->find($paymentRequest->invoice_id);
        if (! $invoice) {
            return;
        }
        $payment = Payment::query()->create([
            'daraja_account_id' => $account->id, 'customer_id' => $paymentRequest->customer_id, 'invoice_id' => $invoice->id,
            'payment_request_id' => $paymentRequest->id, 'receipt' => $receipt, 'account_reference' => $invoice->number,
            'phone' => $paymentRequest->phone, 'amount_minor' => $amount, 'currency' => $invoice->currency, 'status' => 'allocated', 'paid_at' => now(),
        ]);
        $this->applyPayment($invoice, $payment);
    }

    private function processC2b(DarajaAccount $account): void
    {
        $data = $this->payload;
        $receipt = (string) $data['TransID'];
        DB::table('payment_callback_events')->insertOrIgnore([
            'tenant_id' => $account->tenant_id, 'event_key' => 'c2b:'.$account->id.':'.$receipt,
            'payload_hash' => hash('sha256', json_encode($data)), 'event_type' => 'c2b', 'result' => 'received', 'created_at' => now(),
        ]);
        if (Payment::query()->where('daraja_account_id', $account->id)->where('receipt', $receipt)->exists()) {
            return;
        }
        $reference = trim((string) ($data['BillRefNumber'] ?? ''));
        $customer = Customer::query()->where('account_number', $reference)->first();
        $invoice = $customer ? Invoice::query()->where('customer_id', $customer->id)->where('balance_minor', '>', 0)->orderBy('issued_at')->lockForUpdate()->first() : null;
        $amount = $this->minorUnits((string) $data['TransAmount']);
        if ($amount < 1) {
            return;
        }
        $payment = Payment::query()->create([
            'daraja_account_id' => $account->id, 'customer_id' => $invoice ? $customer?->id : null, 'invoice_id' => $invoice?->id,
            'receipt' => $receipt, 'account_reference' => $reference, 'phone' => $data['MSISDN'] ?? null, 'amount_minor' => $amount,
            'currency' => 'KES', 'status' => $invoice ? 'allocated' : 'unmatched', 'paid_at' => now(),
        ]);
        if ($invoice) {
            $this->applyPayment($invoice, $payment);
        }
    }

    private function applyPayment(Invoice $invoice, Payment $payment): void
    {
        $applied = min((int) $payment->amount_minor, (int) $invoice->balance_minor);
        $invoice->balance_minor -= $applied;
        $invoice->status = $invoice->balance_minor === 0 ? 'paid' : 'partially_paid';
        $invoice->save();
        app(AutomationService::class)->restoreAfterInvoicePayment($invoice);
        if ($payment->amount_minor > $applied) {
            app(BillingService::class)->grantCredit($invoice->customer, $payment->amount_minor - $applied, 'M-Pesa overpayment '.$payment->receipt, 'mpesa-overpayment:'.$payment->daraja_account_id.':'.$payment->receipt);
        }
    }

    private function minorUnits(string $amount): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', $amount, $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
