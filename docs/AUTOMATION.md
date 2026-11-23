# Tenant reminders and access automation

## Tenant setup

1. Open **Automation** as a tenant owner or admin. Automation is disabled by default.
2. Configure an Africa’s Talking app username and API key. Start with the sandbox environment; the official SDK documents the sandbox username as `sandbox` and requires the app API key. Add a sender ID only after it is approved for the tenant.
3. Save the invoice reminder template using only `{{brand_name}}`, `{{customer_name}}`, `{{account_number}}`, `{{invoice_number}}`, `{{amount}}`, and `{{due_date}}`. `brand_name` resolves to the tenant's configured portal name or ISP name.
4. Set reminder day offsets and the grace period. Enable automation only after verifying sandbox sends and confirming customer consent and the tenant sender-ID setup.

The adapter posts form data to the provider SMS messaging endpoint and recognizes provider accepted statuses 100, 101, and 102. Verify current environment details and account rules in the official [Africa’s Talking developer docs](https://developers.africastalking.com/) and [PHP SDK](https://github.com/AfricasTalkingLtd/africastalking-php) before using production credentials. No credentials belong in `.env`, source control, or logs; the tenant API key is encrypted in the database.

## Policy and retries

- `automation:run` is scheduled every ten minutes. Each active tenant is processed with its own timezone and tenant context; automation is opt-in.
- A reminder is generated on the configured number of days before an unpaid invoice due date (offset 0 means the due date). A unique tenant idempotency key deduplicates normal schedule reruns for the same invoice/offset. Delivery is at-least-once if the provider accepts a message but the response is lost before Lumenix records it; a retry in that narrow window can duplicate an SMS.
- Failed or interrupted sends are retried with capped backoff (5, 10, 20, 40, then 60 minutes) for up to five attempts. `Sent` means accepted by Africa’s Talking, not confirmed handset delivery. Status, attempt count, provider reference, and sanitized error code are shown in Automation. Destination numbers are encrypted; message text is not persisted.
- After the due date and the full configured grace period, unpaid invoices suspend an active customer. RADIUS authorization is changed to reject immediately. Active VPN routers also receive Disconnect-Requests through the existing adapter; failures stay visible and retry on later runs. If no active VPN router exists, authorization is still rejected and the delivery record says `authorization_only_no_vpn_router`; an already connected session may remain until it disconnects.
- When a payment settles the overdue invoice(s), a customer suspended by this policy is restored automatically. Manual suspensions are preserved. Customer status changes synchronize RADIUS rows through the existing observer.

## Local sandbox checks

Configure the tenant account and template in the UI with Africa’s Talking sandbox credentials, then run:

```powershell
docker compose --profile development exec app php artisan automation:run --date=2026-11-25
docker compose --profile development exec app php artisan schedule:list
```

Automated tests fake the provider HTTP request; no real SMS is sent by the test suite. The first manual sandbox run is a real request to Africa’s Talking sandbox and needs a valid sandbox API key and destination configuration.
