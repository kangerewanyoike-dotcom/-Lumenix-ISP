# Versioned API and network state

The first-party application API is versioned under `/api/v1`. Platform-owner routes are under `/api/v1/admin`; tenant workspace routes are under `/api/v1/tenant`. The current API uses the authenticated browser session, tenant selection, and role policies. It is intended for the same-origin Lumenix frontend. A separate `api.yourdomain.co.ke` deployment still needs bearer-token authentication and CORS configuration before it should be exposed to third-party clients.

## Platform owner

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/v1/admin/dashboard` | Tenant, subscription, outstanding balance, and SaaS revenue totals |
| GET, POST | `/api/v1/admin/tenants` | List and register ISP tenants |
| GET | `/api/v1/admin/tenants/{id}` | Tenant details and SaaS subscription |
| PATCH | `/api/v1/admin/tenants/{id}/status` | Activate or suspend an ISP |
| GET, POST | `/api/v1/admin/plans` | Read and create SaaS plans |
| GET | `/api/v1/admin/subscriptions` | Platform subscriptions |
| GET | `/api/v1/admin/revenue` | SaaS revenue and outstanding balances by currency |

SaaS invoices and payments use `platform_invoices` and `platform_payments`. Subscriber invoices and payments use their own tenant-scoped tables. The two ledgers are not combined.

## ISP tenant

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/v1/tenant/dashboard` | Tenant billing and network state |
| GET, POST | `/api/v1/tenant/customers` | List or register subscribers; new subscribers receive an initial invoice and remain pending payment |
| GET, POST | `/api/v1/tenant/packages` | Read or create internet packages |
| POST | `/api/v1/tenant/pppoe/accounts` | Register a PPPoE subscriber account |
| POST | `/api/v1/tenant/hotspot/vouchers` | Issue a RADIUS voucher; its password is returned once |
| GET, POST | `/api/v1/tenant/routers` | Read or register tenant routers |
| POST | `/api/v1/tenant/invoices` | Issue an idempotent recurring invoice (`Idempotency-Key` header supported) |
| POST | `/api/v1/tenant/payments/stk-push` | Request an M-Pesa STK push; response is pending provider confirmation |
| GET | `/api/v1/tenant/reports/revenue` | Subscriber revenue report |

## Webhooks and telemetry

M-Pesa callback tokens resolve to a stored Daraja account, then its tenant. The payload's tenant ID is ignored. The existing `/daraja/...` callback URLs remain valid, with versioned aliases at `/api/v1/webhooks/mpesa/...`. Callback processing is queued and uses provider-account receipt uniqueness and database idempotency keys.

Router telemetry is accepted at `POST /api/v1/webhooks/routers/{router}/telemetry`. Sign the exact raw request body as `HMAC-SHA256(secret, timestamp + "\n" + body)`, and send `X-Router-Timestamp` (Unix seconds) and `X-Router-Signature`. The router ID resolves the registered tenant; request tenant IDs are not used. Payload fields are `cpu_percent`, `memory_percent`, `uptime_seconds`, `download_bps`, and `upload_bps`.

RADIUS accounting is accepted at `POST /api/v1/webhooks/radius/accounting` with the same timestamp and signature headers, using the registered router's RADIUS secret and `nas_ip_address`. Accounting usernames resolve only against that router tenant's PPPoE customers or hotspot vouchers. The normal `radacct` ingester remains available as well.

## Separate business states

Payment confirmation settles the invoice. It does not claim the subscriber is online. The service subscription state, RADIUS provisioning state, and live network session are reported separately. New customers start with `pending_payment`; a verified payment or fully applied credit activates the subscription and queues RADIUS synchronization. Provisioning remains pending until a fresh router telemetry check-in is available. Online state comes from RADIUS accounting.

## Background processing

- `ProcessPaymentCallback` handles STK and C2B callbacks with retry and idempotent database writes.
- `SyncCustomerRadius` provisions or blocks PPPoE credentials in the queue.
- `DisconnectExpiredUsers` sends queued RADIUS disconnect requests and retries router failures.
- `network:sync-router-status` marks stale routers offline and retries pending provisioning.
- `billing:run`, `radius:ingest-accounting`, `automation:run`, `subscriptions:expire`, and `platform-billing:run` are scheduled commands, outside browser requests.

Email/WhatsApp delivery and provider-side M-Pesa transaction reconciliation are not implemented yet. Unmatched Paybill receipts remain available for authorized manual allocation.
