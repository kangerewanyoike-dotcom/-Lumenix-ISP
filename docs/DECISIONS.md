# Lumenix Decisions

Architecture and implementation decisions.

| ID | Decision | Reason / consequence |
|---|---|---|
| D-001 | Use Laravel 13, PHP 8.3, PostgreSQL, Redis, Blade/Livewire/Tailwind. | Laravel 11 was the original baseline; it was upgraded after its security support window ended. |
| D-002 | Start as a modular monolith with shared-database tenancy. | Keeps MVP operations straightforward while retaining clear module and adapter boundaries. |
| D-003 | Put `tenant_id` on every tenant-owned row; combine global scopes with policies, tenant-aware constraints, and tenant context restoration in jobs. | A global scope by itself is not a sufficient tenant security boundary. |
| D-004 | Use separate platform administrator identity/authorization and tenant memberships; subscriber portal accounts are separate from staff access. | Prevents platform and ISP privileges from being conflated. |
| D-005 | Develop a fake `RouterConnection` first, then validate a MikroTik CHR over a private per-tenant VPN; design for an outbound agent as the preferred production path. | Private VPN is practical for first lab validation; outbound agent avoids inbound router management exposure and NAT issues. Agent protocol and rollout remain a later design detail. |
| D-006 | Prefer FreeRADIUS as the PPPoE authorization/accounting source; validate CoA/Disconnect in the pinned RADIUS and RouterOS lab, with MikroTik API control as fallback. | CoA behavior/configuration depends on the actual NAS and deployment; lab verification is required. |
| D-007 | Require globally unique RADIUS usernames even though customer account identifiers are tenant-scoped. | Standard SQL authorization lookups commonly use username without tenant context; global uniqueness reduces cross-tenant ambiguity. |
| D-008 | Represent money using integer minor units; round VAT and prorated amounts half-up to the nearest minor unit; persist rounded invoice and line snapshots. | Avoids floating-point drift and preserves issued invoice history. |
| D-016 | Use inclusive service-period dates; prorate plan and billing-day changes by actual covered days over the existing cycle's actual day count, with inclusive remaining days from the effective date. Apply VAT to each signed adjustment using the tenant's configured basis-point rate. | Makes date boundaries and rounding reproducible. VAT defaults to 0 basis points until each tenant's applicable rate is confirmed; this setting is configuration, not tax advice. |
| D-017 | Store issued invoice lines and credit ledger entries as append-only records; invoice totals and line VAT snapshots are immutable after issue, while invoice balance/status can change through credit application. | Preserves billing history and makes adjustments auditable. |
| D-009 | Treat ISP customer collections and Lumenix tenant subscriptions as separate billing ledgers and invoice types. | Clarifies merchant-of-record and reconciliation boundaries. |
| D-010 | Use database idempotency keys and unique provider transaction constraints for billing runs and payment callbacks. | Makes retries and duplicate webhook delivery safe. |
| D-011 | Match the exact FreeRADIUS SQL schema to a pinned version instead of designing a proprietary replacement. | FreeRADIUS table/query expectations vary; implementation must verify upstream-compatible shape. |
| D-012 | Keep provider/router secrets encrypted, excluded from logs and repository; use placeholders only in `.env.example`. | Protects credentials and supports per-tenant configuration. |
| D-013 | Default tenant white-label setting to display “Powered by Lumenix”; make removal explicit per tenant. | Implements requested platform branding default. |
| D-014 | Plan deletion as a privacy workflow that removes/anonymizes eligible personal data while preserving legally required financial records. | Supports customer privacy requests without breaking accounting history; retention period needs deployment/legal policy confirmation. |
| D-015 | Retain Laravel 11 as explicitly requested, while recording that its upstream security support ended on March 12, 2026. | Superseded by D-021 after the project’s production-readiness review. |
| D-018 | Match the pinned FreeRADIUS 3.2.10 PostgreSQL schema exactly and enforce global PPPoE username uniqueness; isolate app usage in a separate tenant-scoped projection. | Stock authorization SQL has no tenant context, and adding tenant columns breaks upstream query compatibility. |
| D-019 | Store router RADIUS secrets encrypted on router records and sync to the standard `nas` table; use private VPN plus `radclient` Disconnect-Requests for the first router adapter. | FreeRADIUS needs the shared secret and RouterOS must be reachable on UDP 1700; CHR validation is required. Standard RADIUS tables need cleartext-equivalent PPP secrets for CHAP and a readable NAS secret; restrict DB access and encrypt storage/backups. |
| D-020 | Blank the stock post-auth SQL password value and ingest accounting into a tenant projection every minute, with a `--all` backfill option. | Avoids retaining submitted PPP passwords in `radpostauth` and keeps routine ingestion bounded to recently changed records. |
| D-021 | Upgrade the framework baseline to Laravel 13 on PHP 8.3. | Laravel 11 was outside its published security-support window; Composer’s updated lockfile audit reports no known advisories. Full feature-test verification remains a release gate. |

Unresolved implementation choices: UUID versus ULID selection (recommend UUID), each tenant's legally applicable VAT rate, Daraja callback validation fields, and customer financial-record retention period.
