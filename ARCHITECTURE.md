# AFCS Platform Architecture

Evolved from a single-terminal DART demo into a modular, offline-capable
Automated Fare Collection platform foundation.

## Stack
- **API**: PHP 8+ / PDO / MySQL (InnoDB)
- **UI**: React 18 via CDN + Babel (no build step)
- **Auth**: PHP sessions + optional TOTP MFA
- **Devices**: Registered entities with hashed tokens
- **Sync**: Outbox/inbox event model (`api/sync.php`)
- **Offline**: SQLite schema for terminals + browser localStorage outbox

## Domain modules
| Module | Location |
|--------|----------|
| Identity & sessions | `api/auth.php`, helpers/auth, permissions |
| Cards & wallets | `api/cards.php`, helpers/ledger |
| QR tickets | `api/qr.php` (HMAC signed) |
| Fares | `api/fares.php`, helpers/fare_engine |
| Network | `api/network.php` (stations, routes, offline policies) |
| Devices | `api/devices.php` |
| Finance / refunds | `api/finance.php` |
| Settlement | `api/settlement.php` |
| Sync | `api/sync.php` |
| Anomaly | `api/anomaly.php` |
| Health | `api/health.php` |
| Audit | helpers/audit → `audit_logs` |

## Financial integrity
- `cards.balance` is a **cache**
- `ledger_entries` is the **immutable journal** (dual-write on top-up/transfer/refund)
- All money mutations use DB transactions + row locks
- Idempotency keys prevent double processing

## Security baseline
- Password hashing (bcrypt), lockout, force change on seeded accounts
- Permission-based RBAC (not only role strings)
- CORS restricted; request IDs on responses
- Signed QR; atomic single-use validation
- MFA (TOTP) optional per user
- Device tokens hashed at rest

## Deployment checklist
1. Set env vars from `.env.example` (especially `AFCS_HMAC_SECRET`, DB password)
2. Create MySQL database and app user with least privilege
3. Serve over HTTPS only
4. Ensure PHP sessions are secure (`session.cookie_secure`, `httponly`, `samesite`)
5. Rotate HMAC secret with a planned ticket version cutover
6. Monitor `api/health.php?action=ready`
7. Schedule reconciliation runs and review `anomaly_events`

## Scaling path
Single operator → multi-operator via `organizations` → city authority:
add read replicas for reports, queue workers for settlement/anomaly,
partition `ledger_entries` / `transactions` by time when volume requires it.
