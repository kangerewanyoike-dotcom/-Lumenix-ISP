# M-Pesa Daraja sandbox

## Configure a tenant account

1. Create/sign in to a Daraja developer account and create a sandbox app in the [official Safaricom Daraja portal](https://developer.safaricom.co.ke/).
2. In Lumenix, open **Billing → Settings** and add a sandbox collection account. Use the Consumer Key/Secret and shortcode/passkey issued for that Daraja sandbox app. The app encrypts credentials at rest.
3. Copy the account-specific callback URLs shown after saving. They contain bearer-like random tokens; treat them as secrets. Configure the STK callback URL in Daraja. For C2B, register the separate validation and confirmation URLs using the URL registration flow and payload required by the current Daraja portal. The validation endpoint currently accepts the transaction; it does not apply business validation rules.
4. Use an HTTPS callback URL reachable from Safaricom. Localhost is not reachable by the provider; use an approved public HTTPS development tunnel and remove it when finished.
5. Request an STK Push from an outstanding invoice. Verify the result and receipt in the payment ledger, and test a C2B callback using the sandbox simulator.

## Adapter contract and limitations

The implementation uses the published Daraja STK Push process path and OAuth client-credentials flow, with the conventional `BusinessShortCode`, `Password`, `Timestamp`, `TransactionType`, `PartyA`, `PartyB`, `PhoneNumber`, `CallBackURL`, `AccountReference`, and `TransactionDesc` request fields. Successful callbacks are correlated by `CheckoutRequestID`; callback metadata supplies `Amount` and `MpesaReceiptNumber`. C2B confirmation uses `TransID`, `TransAmount`, `ShortCode`, `BillRefNumber`, and `MSISDN`.

Safaricom now serves detailed API schemas dynamically in Daraja 3.0. Confirm these exact fields, current endpoints, account type/transaction type, URL registration requirements, currency rounding, and production onboarding against the documentation in the tenant's own Daraja portal before enabling live credentials. The callback adapter is isolated in `DarajaClient` and `DarajaCallbackController`; do not infer authenticity from the callback payload alone. Callback URLs use unguessable per-account secrets, and the C2B handler additionally checks the shortcode, but URL secrecy is not a substitute for any provider signature or IP-verification facility available to the tenant.

Money is stored as integer minor units (1 KES = 100). Daraja STK `Amount` is an integer number of shillings, so the request rounds a fractional-shilling invoice balance up to the next shilling. Any amount received above the outstanding balance becomes customer credit; partial receipts reduce the balance and leave the invoice partially paid. Unmatched C2B receipts remain unapplied until a tenant admin allocates them. Manual allocations and account creation are audited.

Never commit Daraja credentials, callback URLs, or customer payment data to source control or support logs. Sandbox credentials and shortcode values are issued by Safaricom; no real credentials are included here.

## Local checks

```powershell
docker compose exec app php artisan migrate
docker compose exec app php artisan test --filter=DarajaPaymentsTest
```
