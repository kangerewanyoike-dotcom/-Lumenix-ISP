# Lumenix MVP Delivery Plan

Phases run only when explicitly started by the user. Each phase ends with review of tenant isolation, authorization, idempotency, and security implications. Tests and Pint are run at each phase completion when the environment supports them; unavailable commands and exact local commands are reported.

## Phase 1 — Foundation

**Scope:** Docker Compose app/PostgreSQL/Redis/FreeRADIUS; Laravel 13/PHP 8.3; migrations; shared database tenancy; tenant context and global scopes; super-admin tenant create/suspend; authentication and tenant membership roles.

**Acceptance criteria:**

- `docker compose up -d`, install, migration/seed, test, and Pint commands work as documented.
- Demo platform admin and at least two tenants can be seeded without committed secrets.
- Tenant-scoped models fail closed without context; policies protect tenant data.
- Tests prove tenant A cannot list, read, modify, delete, or traverse tenant B records, including nested relations and queued work where implemented.
- Platform admin can create and suspend a tenant; actions are audited.
- Platform and tenant role-based access is enforced without an authenticator challenge.

## Phase 2 — Core data

**Scope:** Plans, customers, routers CRUD/admin UI, validation, policies, encrypted router credentials, demo data.

**Acceptance criteria:**

- Tenant-scoped CRUD and validation for all three resource types.
- Customer PPPoE usernames are unique according to the selected RADIUS isolation strategy.
- Credentials are encrypted at rest and omitted from UI/API serialization and logs.
- Role policies restrict actions; cross-tenant access tests cover nested IDs.
- Demo seeders provide realistic but non-personal placeholder data.

**Status:** Implemented in the current workspace. Automated tests cover tenant isolation, resource creation and listing, role restrictions, secret encryption/serialization, and private router endpoint validation.

## Phase 3 — Billing engine

**Scope:** Recurring invoices, mid-cycle proration, plan changes, billing-day changes, one-off charges, VAT, credits.

**Acceptance criteria:**

- Money calculations use integer minor units or decimal arithmetic, never floats.
- Calculation and rounding conventions are documented and captured on invoice lines.
- Tests cover upgrades, downgrades, billing-day changes, credit application, VAT rounding, and a repeated scheduled run without duplicates.
- Transactions and unique period/idempotency keys prevent duplicate invoices under retry/concurrency.
- Invoice history remains stable after later plan or tax configuration changes.

**Status:** Implemented in the current workspace. Recurring billing supports daily, weekly, and monthly plan cycles; plan proration supports same-cycle-frequency changes, while billing-day changes apply to monthly plans. VAT is tenant-configured in basis points and defaults to 0 until verified for that tenant. Credit balances are append-only and can be granted or applied to invoices. The scheduler command is `php artisan billing:run`.

## Phase 4 — RADIUS and routers

**Scope:** FreeRADIUS SQL sync and accounting ingestion; suspend/reactivate via CoA/Disconnect or MikroTik API behind `RouterConnection`; fake driver; CHR lab steps.

**Acceptance criteria:**

- Exact SQL schema/query compatibility is checked against pinned FreeRADIUS version.
- Customer plan/status changes synchronize to check/reply/usergroup rows, including `Mikrotik-Rate-Limit` formatting verified in lab.
- Accounting records ingest idempotently and report usage by tenant/customer.
- Fake driver tests command generation, retries, failures, and state transitions.
- CHR lab documentation verifies auth, accounting, disconnect/reactivate, firewall/network assumptions, and secrets handling.
- Production connectivity mode decision is revisited with lab results; VPN reachability is supported before an outbound agent is exposed.

**Status:** Subscriber sync, tenant-scoped accounting projection, and a `radclient` Disconnect-Request adapter are implemented. Tests cover rate formatting, plan/status synchronization, retries/failures, and idempotent usage ingestion. FreeRADIUS SQL startup and MikroTik CHR interoperability still need lab verification because this environment cannot currently access Docker Desktop's engine pipe. See `docs/ROUTER_LAB.md`.

## Phase 5 status

Tenant-specific Daraja accounts, encrypted credentials, STK initiation, STK/C2B callback processing, duplicate-safe receipts, overpayment credit, underpayment balance tracking, unmatched-payment queue/manual allocation, and sandbox documentation are implemented. Automated callback tests are in `tests/Feature/Billing/DarajaPaymentsTest.php`. Current Daraja payload field compatibility and live URL registration still require sandbox verification against the tenant's Daraja portal before production credentials are enabled.

## Phase 5 — Payments

**Scope:** Tenant-specific Daraja credentials; STK Push; C2B callbacks; reconciliation; idempotency; unmatched-payment queue/manual allocation; sandbox guide.

**Acceptance criteria:**

- Daraja request/response fields and callback validation follow verified official specifications; uncertain assumptions are documented and isolated in adapters.
- Each payment is associated with the correct tenant collection account; no cross-tenant allocation is possible.
- Duplicate callbacks are idempotent; unmatched payments can be safely allocated with an audit trail.
- Tests cover duplicate callback, wrong account number, overpayment, underpayment, and late callback.
- All external HTTP calls are mocked in automated tests; docs/MPESA_SANDBOX.md explains safe sandbox setup without real secrets.

## Phase 6 — Automation

**Scope:** SMS reminders, grace periods, automatic suspension/reactivation, Africa's Talking integration, per-tenant templates.

**Acceptance criteria:**

- Reminders and status transitions are scheduled, tenant-aware, idempotent, and retry-safe.
- Payment during grace period restores service according to policy without duplicate commands.
- Message templates accept only supported variables; secrets and unnecessary personal data are not logged.
- Africa's Talking HTTP calls are mocked; delivery failures and retries are visible.

**Status:** Tenant automation settings, encrypted Africa’s Talking credentials, allowlisted invoice reminder templates, idempotent/retryable SMS delivery, grace-period RADIUS suspension, and payment-triggered restoration are implemented. Automation is opt-in and scheduled every ten minutes. VPN Disconnect-Request delivery still depends on an active, reachable tenant VPN router; authorization rejects new sessions regardless. See `docs/AUTOMATION.md`.

## Phase 7 — Portals and reports

**Scope:** Branded customer portal, invoices, M-Pesa payment initiation, usage/status, admin dashboard, revenue/outstanding reports, super-admin platform billing view.

**Acceptance criteria:**

- Portal identity is limited to one customer and tenant; portal actions use policies and rate limits.
- Tenant branding applies to portal/invoices/notifications; “Powered by Lumenix” defaults on and can be disabled per tenant.
- Reports reconcile to invoice/payment ledgers and are tenant-isolated.
- Platform subscription snapshots and invoices remain distinct from ISP customer billing.
- Responsive UI and accessible status/error states are reviewed.

**Status:** Complete in the current workspace. The customer portal has separate customer-linked credentials, tenant-scoped invoices/usage, and rate-limited M-Pesa STK initiation. Tenant admins can configure portal branding; reports summarize tenant billing ledgers. Super-admins can configure Lumenix per-active-subscriber subscriptions and inspect a separate platform invoice ledger. `platform-billing:run` snapshots the previous calendar month and is scheduled monthly. Verification: 41 tests pass (205 assertions) on Docker PHP 8.3/PostgreSQL, Pint passes (125 files), Phase 7 migrations apply, and the local login endpoint responds with HTTP 200. See `docs/PORTAL.md`.

## Phase 8 — Hardening

**Scope:** Security review, rate limiting, audit coverage, backups, logging, customer data export/deletion, deployment guide.

**Acceptance criteria:**

- docs/SECURITY_REVIEW.md records threat surfaces, findings, fixes, residual risks, and verification evidence.
- Sensitive actions and personal data access have appropriate audit coverage without logging secrets.
- Customer export/deletion handles related data and legal financial retention safely; behavior is documented and tested.
- docs/DEPLOYMENT.md covers secrets, TLS, least privilege, backup/restore, queue operations, monitoring, and router network isolation.
- Tests and Pint pass; known operational gaps are listed before release.

**Status:** Complete for the current workspace. Owner/admin customer export is audited and omits service passwords; archive/delete anonymizes eligible personal data and disables the portal login while preserving financial records and aggregate usage. Audit metadata is allowlisted, login/callback throttles are separated by endpoint, and backup/restore and deployment requirements are documented. Verification: 44 tests pass (222 assertions) on Docker PHP 8.3/PostgreSQL, Pint passes (127 files), export route is registered, and the local login endpoint returns HTTP 200. Security review findings and production release gates are in `docs/SECURITY_REVIEW.md`; operational instructions are in `docs/DEPLOYMENT.md`.
