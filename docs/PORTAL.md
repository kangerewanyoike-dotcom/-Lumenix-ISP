# Subscriber portal and platform billing

## Subscriber access

1. A tenant owner or admin opens a customer record and creates or resets its portal account.
2. Share the email and initial password with the subscriber through the ISP's secure channel. Passwords must be at least 12 characters.
3. The subscriber signs in at `/portal/login`. Their portal account is separate from staff credentials and can access only its linked customer and tenant.
4. The portal shows service status, current-month RADIUS usage, invoices, and allocated payments. An unpaid invoice can start an M-Pesa STK request when the tenant has an active Daraja account.

The portal login POST is limited to five attempts per minute per client, as is invoice STK initiation. Payment is confirmed asynchronously by the Daraja callback; the initial STK response does not mark the invoice paid.

## Branding and tenant reports

Tenant owners/admins configure the portal name, primary color, and Powered by Lumenix footer under **Branding**. The footer is enabled by default. Invoice reminder templates may use `{{brand_name}}`, which resolves to the configured brand name or the tenant name.

Tenant dashboard and reports read only the current tenant's customer, invoice, payment, and accounting ledgers. Revenue totals include allocated payments for the current month; outstanding totals sum invoice balances.

## Lumenix subscription billing

Platform admins open **Platform → Lumenix platform billing** to set each tenant's price per active subscriber, currency, billing day, and subscription state. Configure the rate before issuing platform invoices. Generate a period manually with:

```sh
docker compose --profile development exec app php artisan platform-billing:run --period=2026-10
```

Without `--period`, the command uses the previous calendar month. It snapshots active subscribers once per tenant/period and creates a separate, idempotent Lumenix invoice. The scheduler runs it on the first day of each month. Platform invoices are not ISP customer invoices and are not settled through tenant Daraja accounts.

## Local verification

```sh
docker compose --profile development exec app php artisan migrate --force
docker compose --profile development exec app php artisan test --compact
docker compose --profile development exec app vendor/bin/pint --test
```
