# Lumenix Architecture

## Purpose and boundaries

Lumenix is a shared-database SaaS platform for Kenyan ISPs using MikroTik PPPoE. It manages tenant operations and customer payments while each ISP remains merchant of record for its own M-Pesa collections. The platform separately bills each ISP for Lumenix service. The initial release is a modular Laravel application, not a set of microservices.

## Stack

- Laravel 13 on PHP 8.3; Blade and Livewire with Tailwind CSS.
- PostgreSQL is the source of truth, including FreeRADIUS SQL tables.
- Redis backs queues and cache. Queue workers handle callbacks, outbound messages, reconciliation, and network tasks.
- Docker Compose runs the app, PostgreSQL, Redis, and FreeRADIUS for local development.
- MikroTik RouterOS API connectivity is behind a `RouterConnection` interface and an implementation using a maintained PHP RouterOS client.
- M-Pesa Daraja and Africa's Talking integrations are adapters around Laravel HTTP clients. External calls are mocked in tests.
- Pest is the test framework; Pint is the formatter.

## Application modules

Keep business rules in focused services/actions, with thin HTTP and Livewire entry points:

1. **Platform**: super-admin identity, tenant lifecycle, platform subscription metering and suspension.
2. **Tenant access**: tenant membership, roles, authorization, tenant context and audit events.
3. **Network**: plans, PPPoE customers, routers, RADIUS provisioning/accounting, suspend/reactivate.
4. **Billing**: tenant-customer invoices, line items, taxes, credits, recurring billing and balance calculations.
5. **Payments**: tenant Daraja configuration, STK requests, callbacks, payment matching and unmatched-payment allocation.
6. **Communications**: notification templates, SMS reminders and delivery results.
7. **Portal and reporting**: tenant-branded subscriber experience and tenant-scoped summaries.
8. **Privacy**: customer data access/export and deletion workflows, with minimized retention and access audit.

Suggested dependency direction: UI/controllers → application actions → domain services → persistence/adapters. Network, Daraja, SMS, and clock/queue boundaries should be injectable so tests can use fakes.

## Tenancy and isolation

Use a shared PostgreSQL schema. Every tenant-owned row carries a non-null `tenant_id`, indexed and protected by a foreign key to `tenants`. Tenant context is derived from the authenticated tenant membership or a verified tenant portal login, never from an untrusted request parameter alone. A middleware/context service sets the current tenant for the request/job; a global Eloquent scope applies it to tenant models. Explicit unscoped access is reserved for reviewed platform operations and provisioning paths.

Authorization is layered: tenant-scoped queries, policies checking the current tenant and role, and database constraints/unique keys including `tenant_id`. Background jobs carry a tenant ID, re-establish tenant context on execution, and fail closed if context is missing. Super-admin actions use explicit platform paths and are audited. Callback endpoints resolve tenant via an opaque configured Daraja shortcode/credential mapping and validate callback data before creating tenant-scoped records.

Tenant isolation tests must cover list, detail, update/delete, nested relations, portal access, queued jobs, and attempts to submit another tenant's IDs. Do not rely on a global scope alone as the security boundary.

## Identity and roles

Platform operators use a platform-admin role separate from tenant membership. Tenant users have a membership in one tenant and role `owner`, `admin`, `support`, or `agent`. Policies define capabilities centrally. Staff authenticate with email and password; tenant selection and role policies continue to gate workspace access. Subscriber portal identities are separate from staff memberships and are linked to one customer. Passwords use Laravel's password hashing; secrets use Laravel encrypted casts or an equivalent managed key strategy.

## Router connectivity recommendation

Build the **outbound agent** path first for production architecture, with a local fake driver first in the development phase. ISPs commonly place routers behind NAT or restrictive firewalls; an outbound, tenant-authenticated agent avoids requiring inbound exposure of RouterOS management ports and works with per-tenant VPN deployments. The agent should establish an authenticated outbound channel, receive narrowly scoped commands, and report results. Do not implement an Internet-reachable RouterOS API endpoint as the default.

For the first deployable lab, support a router reachable over a private per-tenant VPN using RouterOS API with TLS where supported and strict firewall allowlists; keep this as the first concrete adapter because it is simpler to validate with a MikroTik CHR. Both modes implement `RouterConnection`, and tenant/router credentials are encrypted at rest. Store connection mode and endpoint metadata per router. Commands must be idempotent where possible, time-limited, audited, and queued. Do not log credentials or PPPoE secrets.

RADIUS should be the normal authorization source for PPPoE and accounting. MikroTik API control is a fallback for disconnect/reconnect operations when CoA/Disconnect support has not been validated in the target FreeRADIUS/RouterOS setup. Confirm NAS CoA ports, shared-secret handling, and RouterOS behavior in the lab before enabling it in production; do not assume behavior beyond the documented protocol/configuration.

## Billing and money

Represent money as integer minor units plus currency (KES) for amounts that align to minor units, or PostgreSQL `numeric` where a calculation requires decimal precision. Never use binary floating point. Define rounding rules explicitly at each tax/line-total boundary and persist the resulting invoice lines and totals so invoices remain immutable records. Use database transactions, unique period keys, and idempotency keys for recurring invoices, payment callbacks, and scheduled work. Proration uses a documented day-count convention and records the period and calculation inputs. Credits are immutable ledger entries applied through transactions.

Keep customer billing and platform subscription billing distinct. Tenant customer payments settle the ISP's invoices and must be reconciled against that tenant's configured collection account. Platform charges are owed by the ISP to Lumenix and have separate invoices/ledger states.

## Integrations and retry safety

- Daraja callback handlers verify expected tenant mapping, validate required fields and amounts, store a unique provider transaction identity, and enqueue reconciliation. Duplicate callbacks return success after recognizing the already-recorded event. Unmatched payments remain visible for reviewed manual allocation.
- STK initiation records a local request before sending and stores provider references/status transitions. Secrets are tenant-specific and encrypted.
- SMS is queued, rate-limited, and records delivery outcome without storing unnecessary message content or credentials.
- Router and RADIUS changes are queued commands with idempotent desired-state operations and retry limits. A failed external action must not silently mark the customer as changed.
- Scheduled billing/reminder/suspension jobs use unique period/action keys and database locking or equivalent concurrency control.

## Branding and configuration

Platform defaults identify the product as **Lumenix**. Tenant brand name, logo asset, and primary colors are configurable, with safe color validation and constrained image uploads. Apply tenant branding to portal, invoice presentation, and notifications. Show “Powered by Lumenix” by default; store an explicit per-tenant setting to hide it. Keep platform identity and tenant presentation separate.

## Operations and security

Keep `.env` out of version control; commit only `.env.example` with placeholders. Use environment-managed encryption/app keys and provider secrets. Production deployment must use TLS, least-privilege database and service accounts, private network paths to routers, queue monitoring, encrypted backups, restore drills, and structured logs with personal data/secrets redacted. Add rate limits to login, portal, STK initiation, and callback surfaces as appropriate. Audit sensitive staff actions and access to personal data. Retention/deletion rules must account for legally required financial records; deletion should anonymize or remove eligible personal data without corrupting immutable accounting history.

## Local workflow

```sh
docker compose --profile development up -d
docker compose --profile development exec app composer install
docker compose --profile development exec app php artisan migrate:fresh --seed
docker compose --profile development exec app php artisan test
docker compose --profile development exec app ./vendor/bin/pint
```

The exact image/build setup, RADIUS SQL bootstrap, and local credentials belong to Phase 1 implementation and must be documented without committing secrets.
