# Phase 8 security review

**Review date:** 2026-11-21  
**Review type:** Source/configuration review and automated tests in the local Compose stack. This is not an external penetration test or a legal retention determination.

## Surfaces reviewed

- Staff login, tenant selection, platform/tenant role middleware, customer portal login and session middleware.
- Tenant global scopes, explicit unscoped provisioning/callback queries, nested customer/invoice access, and platform-admin routes.
- Daraja callbacks, STK initiation, SMS credentials/destinations, router credentials and RADIUS subscriber synchronization.
- Customer export/archive behavior, audit metadata, Docker Compose, environment exclusions, logging configuration and scheduler/queue operations.

## Findings and changes

| Severity | Finding | Resolution / evidence |
|---|---|---|
| High | Customer archival previously soft-deleted the record without disabling portal credentials or anonymizing customer identifiers in related records. | Deletion now runs transactionally: disconnects and anonymizes the customer, disables/scrubs its portal login, removes payment contact references, replaces payment-request/SMS destinations, scrubs RADIUS session identifiers and removes authentication log entries. Invoice/payment totals and receipts plus aggregate usage remain for accounting. `CustomerPrivacyTest` covers portal disablement and retained invoice history. |
| Medium | Audit metadata accepted arbitrary keys and values, allowing callers to accidentally record secrets or personal data. | `AuditLogger` now persists only explicitly approved metadata keys and scalar values. Feature test verifies password/email data is discarded. |
| Medium | Staff login and provider callbacks lacked route-level request throttles. | Staff and subscriber login are limited to five attempts per minute; Daraja callback routes are limited to 120 requests per minute. Existing portal STK initiation remains limited to five per minute. Provider retry behavior should be monitored and limits adjusted from observed traffic. |
| Medium | There was no customer data export endpoint with an access audit. | Owner/admin-only CSV export includes profile data, invoice/payment ledger details and aggregate usage; PPPoE passwords and payment phone numbers are omitted. CSV formula-leading cells are neutralized. Exports create `customer.exported` audit events. |
| Low | `.env`, keys, logs, vendor files, build output and dependency credentials could be included in repository/container context if ignore rules drifted. | `.gitignore` and `.dockerignore` exclude `.env`, logs, `vendor`, build output and `auth.json`; `.env.example` is the tracked template. Recheck before each release. |

## Data retention behavior

- Customer deletion is an anonymizing archive, not a physical cascade. The customer row is soft-deleted; a tombstone retains its internal ID so invoices, payment allocations, credit entries and aggregate usage remain referentially intact.
- The customer name/account number/phone/PPPoE username are replaced; email/address are cleared; the PPPoE password is rotated; portal access is disabled and its email/password are replaced.
- Payment receipts, amounts, currency, invoice balances and immutable invoice lines remain. Payment contact phone/reference and STK phone are scrubbed. SMS destination is replaced with an encrypted redaction marker. Provider receipt identifiers and platform/tenant audit trails remain.
- RADIUS accounting aggregates remain linked to the anonymized tombstone. Detailed `radacct` rows have customer username, calling station and framed IP fields scrubbed; `radpostauth` rows for the old username are deleted. NAS identifiers and infrastructure logs are retained.
- Retention periods for financial records and provider receipts must be set by the ISP with its accountant/legal adviser before production; this implementation does not infer a statutory period. Backups may retain pre-deletion data until backup expiry, so backup access and expiry must follow the same retention policy.

## Verification evidence

- `docker compose exec -T app php artisan test --compact`: **44 passed (222 assertions)** in the rebuilt PHP 8.3/PostgreSQL stack.
- `docker compose exec -T app vendor/bin/pint --test`: **passed (127 files)**.
- `GET http://localhost:8000/login`: **HTTP 200**; export route is registered as `admin.customers.export`.
- Tenant-scoped export and soft-delete routes use the existing tenant middleware and customer policy; export also requires owner/admin role.
- Platform-admin billing remains behind the separate platform-admin role middleware.

## Framework upgrade follow-up

- The app and PHP worker images now use Laravel Framework 13.43.0 with Pest 4 and PHPUnit 12. The framework, test dependencies, and lockfile were upgraded together.
- `composer audit --locked`: no known security advisories.
- `composer validate`, Laravel boot/version, route registration, registration-page HTTP 200, and Pint passed after the upgrade.
- The feature test suite was not rerun for this upgrade; run it in CI before promoting a release.

## Residual risks / release gates

- Review deployment-specific TLS termination, trusted proxies, firewall rules and router VPN reachability; the local Compose stack is not a production topology.
- Run a PostgreSQL backup/restore drill and confirm encryption, offsite retention, key recovery and restore access controls. No backup target or key-management provider is configured by this repository.
- Confirm country-specific financial/privacy retention and customer request handling with the operator's counsel/accountant.
- Monitor callback/login throttling and queue backlog. Daraja retries can be legitimate; adjust limits only with provider behavior and abuse telemetry.
- Conduct an independent penetration test and dependency vulnerability review before handling production subscriber data.
- Confirm production Daraja credentials, callback URLs, SMS sender identity and MikroTik/RADIUS behavior in provider/router labs.

