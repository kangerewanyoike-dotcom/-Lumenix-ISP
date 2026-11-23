# Deployment and operations guide

This is a release checklist for an operator deploying Lumenix. Docker Compose is for development and a small lab; production should use managed or separately hardened services and a private network.

## Secrets and application configuration

1. Use a release-managed secret store for `APP_KEY`, database credentials, Redis credentials, provider credentials and router secrets. Never commit `.env` or copy production `.env` into a build artifact.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, a strong unique `APP_KEY`, the correct `APP_URL`, and the tenant/app timezone. Terminate HTTPS at a configured reverse proxy and set trusted proxy handling only for known proxy addresses.
3. Use a dedicated least-privilege PostgreSQL role for the application. Restrict PostgreSQL and Redis to private application networks; do not expose database/cache ports publicly.
4. Use a separate database role/process for FreeRADIUS where practical. Firewall UDP 1812/1813 to known NAS/router addresses and protect shared secrets. Router management stays on private VPN paths; do not expose RouterOS API to the public Internet.
5. Keep Daraja and SMS credentials tenant-specific and enter them through the application. Confirm callback URLs are HTTPS and registered with the provider. Do not put provider secrets in audit metadata, logs, tickets, or shell history.

## Release and migrations

1. Build and scan a pinned application image from the reviewed release commit; deploy the same immutable image to web, queue and scheduler workers.
2. Take a verified pre-release database backup, then apply additive migrations with `php artisan migrate --force` as a controlled release step.
3. Run the test suite and `vendor/bin/pint --test` in CI before promotion. Verify `/up`, login, queue health, scheduler activity and provider callbacks after deployment.
4. Use rolling/restart behavior compatible with the release: restart queue workers after code deployment, confirm scheduler has one active instance, and watch failed jobs and application logs.
5. Do not run `migrate:fresh` against production. Roll back application releases only when the previous version remains compatible with applied migrations; use forward fixes for destructive schema changes.

## PostgreSQL backup and restore

Create an encrypted, access-controlled backup on a host with the required Docker/Compose context. Example for PowerShell 7; create the destination directory first and protect it with restrictive ACLs:

```powershell
New-Item -ItemType Directory -Force .\backups | Out-Null
docker compose exec -T postgres sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -Fc -U "$POSTGRES_USER" "$POSTGRES_DB"' > .\backups\lumenix-$(Get-Date -Format yyyyMMdd-HHmmss).dump
```

Encrypt the resulting dump with the organization's approved key-management process before moving it off-host. Back up `APP_KEY` and recovery material separately in a secure vault: losing `APP_KEY` makes Laravel-encrypted provider/router secrets and encrypted notification destinations unreadable. A database backup without its matching key is incomplete.

Periodically restore into an isolated, access-controlled PostgreSQL instance. Run `pg_restore --list` first, restore the dump, then verify migrations, tenant counts, sample invoice/payment ledger balances and application boot. Record the restore date, image version, key version, elapsed time and results. Do not test restore over the live database.

Set and enforce backup retention with the operator's financial/privacy retention policy. A deletion request does not remove previously captured data from backups until those backup sets expire; limit access and document that lag.

## Queue, scheduler and observability

- Run at least one `queue:work` process with bounded retries/timeouts, restart it on release, and alert on failed jobs, growing queues and worker downtime.
- Run exactly one scheduler leader for `schedule:run`. Verify recurring billing, automation, accounting ingestion and platform billing schedule executions in logs/monitoring.
- Use structured logs and a production log level appropriate for the incident process. Avoid request-body logging on payment callbacks, authentication, portal, and provider credential routes. Restrict and rotate logs because they may still contain operational identifiers.
- Monitor database, Redis, disk, HTTP 5xx, queue lag, callback failure/rate limits, and FreeRADIUS authentication/accounting health. Never expose detailed exception pages to end users.

## Network and access control

- Enforce TLS for all user and provider traffic; add HSTS only after HTTPS is correct for every host.
- Restrict admin and platform-admin access, periodically review memberships and remove dormant identities. Staff sign-in uses passwords without a second-factor challenge.
- Keep NAS/router paths private and allowlisted. Test tenant isolation, suspension/reactivation, disconnect behavior and accounting against a CHR/lab before connecting a real subscriber network.
- Run dependency updates and vulnerability scans on a reviewed cadence; deploy security fixes promptly and keep a tested rollback/forward-fix path.
