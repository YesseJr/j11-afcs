# Stage 3 — Fare Engine, Network Model, Settlement, Offline Client Foundation

## What changed

### Fare engine
- `helpers/fare_engine.php` — `resolve_fare()` matches media, passenger category, route/zone, effective dates, priority
- `api/fares.php` — list / resolve / save / deactivate fare products
- QR sell now prices from fare products (fallback to system_config)

### Transport network
- Tables: `stations`, `routes`, `route_stops`
- Seeded Kigamboni / CBD / Mwenge + default route
- `api/network.php` — stations, routes, save_station, save_route, offline_policies

### Offline policies
- `offline_policies` table per device type (cashier_terminal, gate_validator, inspector_mobile)
- Configurable allow_topup / allow_qr_sale / max_offline_topup / offline_auth_ttl_hours
- Browser helper `assets/js/core/offline.js` — local outbox, policy cache, flush on reconnect
- StatusBar shows real ONLINE / OFFLINE + queued event count

### Settlement & reconciliation
- `settlement_batches` / `settlement_lines` / `reconciliation_runs`
- `api/settlement.php`:
  - `run_reconciliation` — period totals, cash variance, balance mismatch scan, issue list
  - `create_settlement` — revenue / top-up / refund lines + net
  - list/detail endpoints

### Tests
- `tests/stage3_smoke.php` — schema + fare resolution smoke checks

## Preserved
- All prior cashier/admin flows
- Stage 1–2 security, ledger, idempotency, RBAC, devices, refunds, sync API

## Notes
- Browser outbox is a stepping stone; native terminals should use SQLite later
- Fare changes do not require code deploys
- Set production `AFCS_HMAC_SECRET` before go-live

## Remaining roadmap
- Native device SQLite offline runtime
- Admin UI screens for fares / devices / settlement
- Automated concurrency tests
- MFA, fraud scoring, multi-currency settlement
