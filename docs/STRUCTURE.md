# Lumenix Code Structure

This structure is the intended Laravel 13 organization. Keep framework integration at the edges and business behavior in domain-oriented application classes.

```text
app/
  Actions/
    Platform/                 # Create/suspend tenant, snapshot subscriber usage
    Billing/                   # Generate invoice, change plan, apply credit
    Payments/                  # Initiate STK, record callback, reconcile payment
    Customers/                 # Export and delete/anonymize customer data
  Console/Commands/            # Operational and reconciliation commands
  Domain/
    Tenancy/                   # Tenant context, scoped model contract, resolver
    Identity/                  # Membership roles and authorization helpers
    Plans/
    Customers/
    Routers/                   # RouterConnection contract and implementations
    Billing/                   # Money, proration, VAT and ledger rules
    Radius/                    # Provisioning/accounting services
    Payments/                  # Provider-neutral payment/reconciliation model
    Notifications/
    PlatformBilling/
  Http/
    Controllers/               # Thin web, portal, super-admin, callback endpoints
    Middleware/                # Resolve tenant, authentication, rate limits
    Requests/                  # Validated request objects
  Jobs/                        # Tenant-aware queued operations
  Livewire/                    # Admin, portal, and super-admin screens
  Models/                       # Eloquent records and relationships
  Policies/                     # Model access rules
  Providers/                    # Service bindings and bootstrapping
  Services/                     # Cross-domain orchestration only when appropriate
  Support/                      # Money/value objects, idempotency, redaction
config/
database/
  factories/
  migrations/                  # Tenant/core migrations and versioned RADIUS SQL schema
  seeders/                     # Demo platform, tenant, plans, customers
resources/
  css/                          # Tailwind inputs
  js/
  views/
    layouts/
    components/
    admin/
    portal/
    platform/
routes/
  web.php
  api.php                       # Only if needed for integrations/agent API
  console.php
tests/
  Feature/
    Tenancy/
    Billing/
    Payments/
    Radius/
    Portal/
  Unit/
    Billing/
    Payments/
    Routers/
docker/
  app/                           # PHP image and entrypoint
  freeradius/                    # Version-pinned config and SQL query config
docs/
  ARCHITECTURE.md
  SCHEMA.md
  STRUCTURE.md
  PLAN.md
  DECISIONS.md
```

## Namespace conventions

- `App\Actions\{Area}`: one-use-case orchestration, transaction boundaries, idempotency.
- `App\Domain\{Area}`: domain rules and provider/router/RADIUS contracts.
- `App\Models`: persistence records; avoid placing multi-step business workflows in model hooks.
- `App\Http\Controllers`: authentication, request validation, action invocation, response only.
- `App\Jobs`: serializable identifiers only; restore tenant context, re-check current state, invoke actions.
- `App\Policies`: authorization by current tenant, role, and record ownership.
- `App\Livewire`: UI behavior calling the same actions/policies as HTTP endpoints.

Use interfaces at external boundaries (`RouterConnection`, payment gateway, SMS gateway, clock if needed). Bind production adapters in providers and deterministic fakes in tests. Avoid a generic repository layer unless a concrete persistence boundary requires one.
