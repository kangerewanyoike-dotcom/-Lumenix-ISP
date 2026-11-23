# FreeRADIUS and MikroTik CHR lab

This is the Phase 4 integration checklist. Run it with a disposable MikroTik CHR and test subscriber; do not use production credentials or customer accounts.

## 1. Start and migrate

From the repository root, with Docker Desktop running:

```powershell
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec freeradius freeradius -XC
docker compose logs freeradius
```

Confirm the pinned FreeRADIUS 3.2.10 image loads its PostgreSQL SQL driver and starts without configuration errors. The app migration creates the upstream PostgreSQL SQL tables and the separate tenant-scoped usage projection.

## 2. Network and secrets

- Give the CHR a private VPN-reachable endpoint. The address stored on the router must be the same NAS address FreeRADIUS sees in packets.
- Permit CHR to FreeRADIUS UDP 1812 (authentication) and UDP 1813 (accounting).
- Permit the app container/host to CHR UDP 1700 (Disconnect-Requests). Restrict this path to the management VPN; do not expose it to the public Internet.
- Set one random shared secret on the CHR's `/radius` entry and the router record's **RADIUS shared secret**. The RouterOS RADIUS server client and FreeRADIUS NAS client must use the same value.
- Router API credentials remain separate. This Phase 4 adapter uses RADIUS Disconnect-Requests; router API fallback is not implemented.
- RADIUS must read a cleartext-equivalent PPP secret for CHAP and a readable NAS shared secret. Keep DB access private, use encrypted disks and backups, and never print these values into logs or support output.

The `nas` table is read for FreeRADIUS clients when that process starts. Saving or changing router registration writes the row; restart FreeRADIUS to refresh its in-memory client list:

```powershell
docker compose restart freeradius
```

Avoid overlapping NAS endpoint addresses across tenants on the same FreeRADIUS instance. Standard client lookup is by source address and does not carry tenant context.

## 3. Configure RouterOS CHR

Replace placeholders using the private FreeRADIUS address reachable by the CHR and a locally generated shared secret. Commands may vary slightly by RouterOS release; verify in the CHR before applying.

```routeros
/radius add address=<FREERADIUS_PRIVATE_IP> service=ppp secret="<RADIUS_SHARED_SECRET>" authentication-port=1812 accounting-port=1813
/ppp aaa set use-radius=yes accounting=yes interim-update=5m
/radius incoming set accept=yes port=1700
```

Configure a PPPoE server and a test client whose username/password exist only in Lumenix RADIUS. Ensure no local `/ppp secret` entry has the same username because RouterOS checks its local user database before RADIUS.

## 4. Verify authorization and rate limits

Create a test plan with `speed_up_kbps=10000` and `speed_down_kbps=50000`, then create an active customer on it. The app should write:

- `radcheck`: `Cleartext-Password := <test PPP password>`
- `radreply`: `Mikrotik-Rate-Limit = 10000k/50000k`
- `radusergroup`: a tenant/plan-qualified group membership

The order is upload/download because RouterOS defines `rx` as client upload and `tx` as client download; the `k` suffix uses decimal kilobits. Verify the actual connected session in `/ppp active` and inspect its rate limit. Update the plan speeds and repeat with a fresh authentication.

## 5. Verify accounting and tenant usage

Connect the test PPPoE client and confirm start, interim, and stop records in `radacct`. The scheduler runs `radius:ingest-accounting` every minute and upserts recent changes by `AcctUniqueId` into `radius_accounting_sessions`.

For a one-time full-table backfill:

```powershell
docker compose exec app php artisan radius:ingest-accounting --all
```

Repeat the command and verify it creates no duplicate app session. Check that the projection's `tenant_id` and `customer_id` match the test subscriber and that octets/session duration are updated from the latest RADIUS record.

## 6. Verify suspend and reactivate

The application service first updates RADIUS authorization data, then sends a Disconnect-Request matching `User-Name` and `NAS-IP-Address`. RouterOS requires `/radius incoming accept=yes`; it supports Disconnect-Messages (DM), not the older Packet of Disconnect (POD). A suspended subscriber receives `Auth-Type := Reject`, then the active PPP session is disconnected. Reactivation restores credentials/rate attributes and sends DM again so the customer can authenticate on reconnect.

Use `CustomerAccessService::suspend()` and `reactivate()` with tenant-scoped `Customer` and `Router` models in a controlled Tinker session. Verify `/ppp active` removes the session, the next attempt is rejected while suspended, and it is accepted after reactivation. A failed DM leaves the safe RADIUS status in place and throws after three attempts so the caller can retry or alert an operator.

## 7. Firewall and diagnostics

- CHR outbound firewall: allow UDP 1812/1813 only to the FreeRADIUS private address.
- CHR input firewall: allow UDP 1700 only from the app's stable VPN source address.
- Host/network firewall: allow only established management VPN routes between the app, FreeRADIUS, and CHR.
- Check `/radius monitor`, CHR logs, `docker compose logs freeradius`, and app logs for request counters/timeouts. Never enable verbose packet logging in production because it may expose PPP credentials or attributes.

## Verification status

Automated tests use a fake RouterConnection and cover generated Disconnect-Request attributes, retries, failures, state transitions, rate formatting, and idempotent accounting. The CHR interoperability steps have not yet been run; complete them before production use.

Reference the [pinned FreeRADIUS 3.2.10 PostgreSQL schema](https://github.com/FreeRADIUS/freeradius-server/blob/release_3_2_10/raddb/mods-config/sql/main/postgresql/schema.sql), [FreeRADIUS SQL queries](https://github.com/FreeRADIUS/freeradius-server/blob/release_3_2_10/raddb/mods-config/sql/main/postgresql/queries.conf), [MikroTik RADIUS](https://help.mikrotik.com/docs/spaces/ROS/pages/328097/RADIUS), and [MikroTik PPP AAA](https://help.mikrotik.com/docs/spaces/ROS/pages/132350049/PPP%2BAAA) while validating the lab.
