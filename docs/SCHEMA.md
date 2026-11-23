# Lumenix Schema Plan

This describes the PostgreSQL target schema; the core Phase 1–3 tables are implemented with integer primary keys. Timestamps are stored with timezone support. Tenant-owned tables include `tenant_id NOT NULL`, indexed, and use tenant-aware unique constraints. Use integer minor units for money, never floating point. Encrypt credentials and sensitive PPPoE secrets at the application layer.

## Platform and identity

| Table | Key columns and constraints | Relationships / indexes |
|---|---|---|
| `tenants` | `id`, `name`, `slug UNIQUE`, `status` (`active`,`suspended`), `timezone`, `currency`, `vat_rate_basis_points DEFAULT 0`, `suspended_at`, timestamps | Platform root; index status; VAT rate is snapshotted per invoice line |
| `tenants` branding fields | `brand_name`, `brand_primary_color`, `powered_by_enabled DEFAULT true` | Portal and invoice presentation; Powered by Lumenix defaults on |
| `users` | `id`, `name`, `email`, `password`, `email_verified_at`, legacy encrypted 2FA columns retained but unused, timestamps | Staff/platform login; unique normalized email |
| `tenant_memberships` | `id`, `tenant_id`, `user_id`, `role`, `status`, timestamps | FKs tenant/user; `UNIQUE(tenant_id,user_id)`, index `(tenant_id,role)` |
| `platform_admins` | `id`, `user_id UNIQUE`, timestamps | Explicit platform privilege, separate from tenant roles |
| `customer_portal_accounts` | `tenant_id`, `customer_id`, email, hashed password, active, `last_login_at` | One portal login per customer; composite tenant/customer FK and tenant/email uniqueness |
| `audit_logs` | `id`, nullable `tenant_id`, `actor_user_id`, `subject_type`, `subject_id`, `action`, JSON `metadata` (allowlisted), `request_id`, `created_at` | Index `(tenant_id,created_at)`, `(subject_type,subject_id)`; do not copy secrets or full personal payloads |

## ISP network and subscribers

| Table | Key columns and constraints | Relationships / indexes |
|---|---|---|
| `plans` | `id`, `tenant_id`, `name`, `price_minor`, `currency`, `speed_down_kbps`, `speed_up_kbps`, `billing_period`, nullable `data_cap_bytes`, `active`, timestamps | FK tenant; `UNIQUE(tenant_id,name)`; index active |
| `customers` | `id`, `tenant_id`, nullable `plan_id`, `account_number`, `name`, `phone`, nullable `email`, address fields, globally unique `pppoe_username`, encrypted `pppoe_password`, `status`, `billing_day`, `billing_anchor_date`, `deleted_at`, timestamps | FKs tenant/plan with composite tenant-plan constraint; `UNIQUE(tenant_id,account_number)`, globally `UNIQUE(pppoe_username)` for standard RADIUS username lookup, indexes `(tenant_id,status)` and `(tenant_id,plan_id)` |
| `customer_portal_accounts` | `id`, `tenant_id`, `customer_id UNIQUE`, `user_id UNIQUE`, `status`, timestamps | FKs tenant/customer/user; customer portal identity is separate from staff membership |
| `routers` | `id`, `tenant_id`, `name`, `connection_mode`, endpoint/agent identity, encrypted API username/password or token, API port, TLS flag, status, last_seen_at, timestamps | FKs tenant; `UNIQUE(tenant_id,name)`, `UNIQUE(tenant_id,agent_id)`, index status; never expose secrets in reads |
| `router_commands` | `id`, `tenant_id`, `router_id`, nullable `customer_id`, `command_type`, JSON sanitized payload, `idempotency_key`, `status`, attempts, result code, timestamps | Unique `(tenant_id,idempotency_key)`; index status/created_at; no credentials in payload |

## Customer billing and payment records

| Table | Key columns and constraints | Relationships / indexes |
|---|---|---|
| `invoices` | `id`, `tenant_id`, `customer_id`, `number`, `period_start`, `period_end`, `issued_at`, `due_at`, `currency`, `subtotal_minor`, `tax_minor`, `total_minor`, `balance_minor`, `status`, `idempotency_key`, timestamps | Composite FKs enforce tenant/customer ownership; `UNIQUE(tenant_id,number)`, `UNIQUE(tenant_id,idempotency_key)`, indexes `(tenant_id,status,due_at)`, `(tenant_id,customer_id,issued_at)` |
| `invoice_lines` | `id`, `tenant_id`, `invoice_id`, `description`, integer `quantity`, `unit_amount_minor`, `subtotal_minor`, `tax_rate_basis_points`, `tax_minor`, `total_minor`, optional source type/id, timestamps | Composite tenant/invoice FK; immutable description, amounts, and tax snapshots; index `(tenant_id,invoice_id)` |
| `credit_ledger_entries` | `id`, `tenant_id`, `customer_id`, nullable `invoice_id`, `amount_minor` (positive grant, negative application), `currency`, `kind`, `description`, `idempotency_key`, `created_by`, `created_at` | Composite tenant/customer/invoice FKs; unique `(tenant_id,idempotency_key)`; index customer/created_at; append-only balance history |
| `invoice_allocations` | `id`, `tenant_id`, `invoice_id`, `payment_id` or credit reference, `amount_minor`, `created_at` | Enforce exactly one source; FKs and indexes by invoice/payment; allocations cannot exceed available balance under transaction lock |
| `billing_operations` | `id`, `tenant_id`, `customer_id`, `operation`, `effective_at`, `idempotency_key`, JSON result, `created_at` | Unique tenant/idempotency key; records plan and billing-day adjustments so retries return the original result |
| `daraja_accounts` | `id`, `tenant_id`, `shortcode`, `account_type`, encrypted consumer key/secret, encrypted passkey, environment, callback config/reference, active, timestamps | FK tenant; unique active shortcode/environment as operationally appropriate; never expose secrets |
| `payment_requests` | `id`, `tenant_id`, `customer_id`, nullable `invoice_id`, `daraja_account_id`, `merchant_request_id`, `checkout_request_id`, `phone`, `amount_minor`, `status`, `idempotency_key`, provider timestamps, timestamps | Unique tenant/idempotency key; unique provider request IDs when present; index status/customer |
| `payments` | `id`, `tenant_id`, nullable `customer_id`, `daraja_account_id`, `provider`, `provider_transaction_id`, `account_reference`, `amount_minor`, `currency`, `phone`, `paid_at`, `status`, `raw_event_id`, timestamps | `UNIQUE(tenant_id,provider,provider_transaction_id)`; index `(tenant_id,customer_id,paid_at)`; unmatched payment has null customer |
| `payment_callback_events` | `id`, `tenant_id` nullable until resolved, provider, provider event/transaction reference, payload hash, minimized validated payload, received_at, processing status, processed_at | Unique provider event identity where provided; index status/received_at; idempotency ledger |
| `unmatched_payment_allocations` | `id`, `tenant_id`, `payment_id UNIQUE`, `customer_id`, `allocated_by`, `note`, `created_at` | FKs; audit manual allocation and transactionally update payment/invoice allocations |

## Platform subscription billing

| Table | Key columns and constraints | Relationships / indexes |
|---|---|---|
| `platform_subscriptions` | `id`, `tenant_id`, `status`, `price_per_subscriber_minor`, currency, billing period/anchor, `suspended_at`, timestamps | FK tenant; typically one current subscription per tenant |
| `platform_usage_snapshots` | `id`, `tenant_id`, `period_start`, `period_end`, `active_subscriber_count`, `unit_price_minor`, `amount_minor`, `created_at` | `UNIQUE(tenant_id,period_start,period_end)`; immutable period measurement |
| `platform_invoices` | `id`, `tenant_id`, `number`, period, totals/balance/status/due_at, idempotency key, timestamps | `UNIQUE(tenant_id,number)`, unique idempotency key; distinct from ISP customer invoices |
| `platform_usage_snapshots` | `tenant_id`, period start/end, active subscriber count, unit price and amount in minor units | One immutable metering snapshot per tenant and period |

## Automation and privacy

| Table | Key columns and constraints | Relationships / indexes |
|---|---|---|
| `notification_templates` | `id`, `tenant_id`, `channel`, `event_key`, `body`, active, timestamps | `UNIQUE(tenant_id,channel,event_key)`; templates accept allowlisted variables only |
| `notification_deliveries` | `id`, `tenant_id`, `customer_id`, channel, event key, destination snapshot (minimized), provider reference, status, error code, sent_at, timestamps | Index `(tenant_id,status,created_at)` and customer; avoid retaining full rendered content by default |
| `customer_data_requests` | `id`, `tenant_id`, `customer_id`, `type` (`export`,`deletion`), status, requested_by, completed_at, timestamps | Index tenant/status; audit fulfillment and deletion/anonymization outcome |

## Payments

| Table | Key columns and constraints | Relationships / indexes |
|---|---|---|
| `daraja_accounts` | Tenant, sandbox/production environment, shortcode/type, encrypted credentials, encrypted callback token and token hash, active flag | Unique callback token hash; composite tenant/account key |
| `payment_requests` | Customer/invoice/account, idempotency key, provider request IDs, phone, integer amount, state/result | Unique tenant/idempotency; unique checkout ID; composite tenant FKs |
| `payments` | Daraja account, optional customer/invoice/request, unique provider receipt, reference/phone, amount/currency, status/time | Unique `(tenant_id,receipt)`; index `(tenant_id,status,paid_at)`; nullable customer/invoice keeps unmatched receipts tenant-owned |
| `payment_callback_events` | Event key, payload hash, event type/result, receive time | Unique `(tenant_id,event_key)` for callback deduplication; raw provider payload is not retained |
| `payment_allocations` | Payment, customer, optional invoice, amount, allocating user, time | Composite tenant FKs and append-only allocation history |

## Tenant automation

| Table | Key columns and constraints | Relationships / indexes |
|---|---|---|
| `tenants` automation fields | `automation_enabled`, `grace_period_days`, configured reminder-day offsets | Defaults disabled; no provider credentials in environment config |
| `customers` access fields | `suspension_reason`, `suspended_at` | Distinguishes automatic overdue suspension from an admin suspension |
| `messaging_accounts` | One tenant account, environment, username, encrypted API key, sender ID, active flag | Unique tenant; credentials hidden from serialization |
| `notification_templates` | Tenant, event/channel, body, active | Unique `(tenant_id,event_key,channel)`; only allowlisted invoice reminder variables are accepted |
| `notification_deliveries` | Customer/invoice, event/channel, encrypted destination, idempotency key, status, attempts, provider reference/error code and retry times | Unique `(tenant_id,idempotency_key)`; no rendered message content stored |
| `automated_access_events` | Customer/invoice, action, idempotency key, status/attempts/error/completion | Unique `(tenant_id,idempotency_key)`; records suspension retry state |

## FreeRADIUS SQL tables

The app migrations implement the PostgreSQL schema from the pinned FreeRADIUS 3.2.10 release (`freeradius/freeradius-server:3.2.10`), including `radacct`, `radcheck`, `radreply`, `radusergroup`, `radgroupcheck`, `radgroupreply`, `radpostauth`, `nas`, and `nasreload`. Do not add tenant columns to these standard tables: upstream queries expect their exact shapes. Standard lookups use usernames without a tenant predicate, so customer PPPoE usernames remain globally unique. The separate `radius_accounting_sessions` projection carries `tenant_id` and a globally unique `acct_unique_id` for tenant-scoped usage queries.

| Table | Planned standard fields / purpose | Isolation and indexes |
|---|---|---|
| `radcheck` | Upstream `id`, `username`, `attribute`, `op`, `value`; PPP credential or `Auth-Type := Reject` | Index `(username,attribute)`; global username uniqueness is enforced by customers |
| `radreply` | Upstream fields; includes `Mikrotik-Rate-Limit` (`upload/download`) | Index `(username,attribute)` |
| `radusergroup` | Upstream `id`, `username`, `groupname`, `priority` | Index `username`; group name is tenant/plan qualified |
| `radgroupcheck`, `radgroupreply` | Upstream group check/reply fields | Index `(groupname,attribute)` |
| `radacct` | All upstream PostgreSQL accounting columns and indexes, including unique `acctuniqueid`, inet NAS/framed fields, timestamps, octet counters, and active-session indexes | Exact FreeRADIUS shape; never tenant-tag this table |
| `radpostauth` | Upstream authentication audit columns | Stock query is customized at image build to blank `pass`; apply retention policy |
| `nas`, `nasreload` | Upstream dynamic client and reload tables | Router observer populates `nas`; the standard table stores the RADIUS shared secret in cleartext |
| `radius_accounting_sessions` | Tenant/customer IDs, unique accounting ID, NAS IP, start/update/stop times, duration, input/output octets | Tenant scoped; idempotent upsert keyed by `acct_unique_id` |

The migration shape was compared against the pinned upstream schema. Runtime SQL driver loading, CHR authentication/accounting, and Disconnect-Request acceptance still require the lab in `docs/ROUTER_LAB.md`.

## General database rules

- Foreign keys protect tenant ownership; where supported, use composite constraints such as `(tenant_id, customer_id)` to prevent cross-tenant references.
- Add indexes to every tenant foreign key and common tenant-filtered lookup.
- Use unique idempotency keys for invoice runs, callback/provider transactions, credit entries, and outbound commands.
- Keep invoice/payment ledgers append-only where practical; corrections are new records, not silent edits.
- Avoid retaining raw provider payloads unless necessary; if retained for support, restrict access, redact secrets, and set retention.
