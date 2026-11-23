<?php

namespace App\Services;

use App\Models\DarajaAccount;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class DarajaClient
{
    public function initiate(DarajaAccount $account, Invoice $invoice, string $phone, string $key): PaymentRequest
    {
        $existing = PaymentRequest::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            abort_unless((int) $existing->invoice_id === (int) $invoice->id && $existing->phone === $phone, 409, 'Idempotency key was used for another payment request.');

            return $existing;
        }
        if ($invoice->balance_minor < 1 || ! $account->active || ! $account->passkey) {
            throw ValidationException::withMessages(['payment' => 'Select an active Daraja account and an invoice with an outstanding balance.']);
        }

        $request = PaymentRequest::create([
            'daraja_account_id' => $account->id, 'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id,
            'idempotency_key' => $key, 'phone' => $phone, 'amount_minor' => $invoice->balance_minor,
        ]);
        $base = $account->environment === 'sandbox' ? 'https://sandbox.safaricom.co.ke' : 'https://api.safaricom.co.ke';
        $token = Http::withBasicAuth($account->consumer_key, $account->consumer_secret)->acceptJson()->get($base.'/oauth/v1/generate', ['grant_type' => 'client_credentials'])->throw()->json('access_token');
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($account->short_code.$account->passkey.$timestamp);
        $response = Http::withToken($token)->acceptJson()->post($base.'/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $account->short_code, 'Password' => $password, 'Timestamp' => $timestamp,
            'TransactionType' => $account->account_type === 'till' ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
            'Amount' => (int) ceil($request->amount_minor / 100), 'PartyA' => $phone, 'PartyB' => $account->short_code,
            'PhoneNumber' => $phone, 'CallBackURL' => route('daraja.stk.callback', ['token' => $account->callback_token]),
            'AccountReference' => substr($invoice->number, 0, 12), 'TransactionDesc' => 'Invoice '.$invoice->number,
        ])->throw()->json();
        if (empty($response['CheckoutRequestID']) || empty($response['MerchantRequestID'])) {
            throw ValidationException::withMessages(['payment' => 'Daraja did not return the expected request identifiers.']);
        }
        $request->update(['merchant_request_id' => $response['MerchantRequestID'], 'checkout_request_id' => $response['CheckoutRequestID']]);

        return $request->refresh();
    }
}
