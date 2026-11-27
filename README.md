# Lumenix

Lumenix is a multi-tenant billing and subscriber management platform for Kenyan ISPs using MikroTik PPPoE. Each ISP manages its own subscribers and collections; Lumenix tracks platform subscriptions separately.

## Requirements

- Docker Desktop with Docker Compose.
- Copy `.env.example` to `.env`, set a unique `DB_PASSWORD`, and provide local demo account credentials before seeding. In PowerShell: `Copy-Item .env.example .env`.
- Staff sign in with email and password. Authenticator verification is disabled for now.

The `.env` file contains local secrets and must not be committed. Generate `APP_KEY` after containers start with `docker compose --profile development exec app php artisan key:generate`; Compose mounts `.env` into the app container so the key is saved on the host. Recreate the app and workers so they load the new key.

## Start locally

```sh
docker compose --profile development up -d --build
docker compose --profile development exec app php artisan key:generate
docker compose --profile development up -d --force-recreate app queue scheduler
docker compose --profile development exec app composer install --no-security-blocking
docker compose --profile development exec app php artisan migrate:fresh --seed
```

After changing application code, rebuild the local app and workers with: `docker compose --profile development up -d --build app queue scheduler frontend`.

Open <http://localhost:8000>. The frontend development server runs on port 5173. PostgreSQL is available on loopback port 5432; FreeRADIUS listens on UDP 1812/1813. Rebuild the app and FreeRADIUS images for Phase 4 with `docker compose --profile development up -d --build app queue scheduler frontend freeradius`.

## Test and format

```sh
docker compose --profile development exec app php artisan test
docker compose --profile development exec app ./vendor/bin/pint
```

Tests use an in-memory SQLite database; the application uses PostgreSQL in Compose.

## Phase 4 RADIUS and routers

FreeRADIUS 3.2.10 uses the PostgreSQL SQL tables created by migrations. Customer credentials, plan rates, and suspension status synchronize to RADIUS; accounting is projected into tenant-scoped usage sessions each minute. `radius:ingest-accounting --all` performs a full backfill. VPN router records require a shared secret for Disconnect-Requests. Complete the MikroTik CHR integration checks in [docs/ROUTER_LAB.md](docs/ROUTER_LAB.md) before using the integration with real subscribers.

## Phase 2 core data

Phase 2 adds tenant-scoped plan, customer, and router administration. PPPoE usernames are globally unique for compatibility with standard RADIUS lookup. Router API credentials and PPPoE passwords are encrypted in the database and omitted from model serialization; edit forms never prefill secrets. Demo seed records are non-personal placeholders. Router VPN endpoints accept private network addresses only; no live router API integration is included in this phase.

## Phase 3 billing

Phase 3 adds recurring invoices for daily, weekly, and monthly plans; same-cycle-frequency plan proration; monthly billing-day transitions; one-off charges; tenant-configured VAT; and customer credit grants/applications. Invoice and line totals snapshot the VAT rate and are immutable after issue. Proration uses inclusive dates and rounds half-up to the nearest minor unit. VAT defaults to 0 basis points until the tenant confirms its applicable rate. The scheduler runs `schedule:run`; to run billing manually: `docker compose --profile development exec app php artisan billing:run --date=YYYY-MM-DD`.

## Phase 1 foundation

The foundation includes staff login, tenant selection, tenant memberships and roles, fail-closed tenant scoping, platform tenant create/suspend controls, and audit entries. Staff sign in with email and password; tenant membership and platform-admin role checks still control access. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/SCHEMA.md](docs/SCHEMA.md), and [docs/PLAN.md](docs/PLAN.md).
