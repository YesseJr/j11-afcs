# AFCS Project Status — Production Foundation

**Version:** 4.5 (platform foundation)  
**Origin:** Single-terminal DART/J11 fare collection demo  
**Target:** Configurable, offline-capable Automated Fare Collection platform

## What works today (operator-ready demo + hardened core)

### Cashier terminal
- Login (with optional MFA), force password change on first use
- Session open / resume / end with **cash drawer count & variance**
- QR student ticket sale (fare-engine priced, HMAC-signed)
- Smart card register, top-up, activate/deactivate, balance transfer, penalty
- Session reports + print
- Online/offline indicator + local outbox queue (browser)

### Admin console
- Session force-close
- Card inventory
- Fare products CRUD
- Device registration & status
- Settlement & reconciliation runs
- Security / MFA enrollment

### Platform backbone
| Area | Implementation |
|------|----------------|
| Ledger | Dual-write `ledger_entries` + cached `cards.balance` |
| Idempotency | Header/body keys on top-up, transfer, QR sale, refund |
| RBAC | permissions / roles / user_roles + `require_permission` |
| QR security | HMAC-SHA256 signature + atomic validate |
| Devices | Registry, token hash, heartbeat |
| Refunds | Full/partial/reversal without rewriting history |
| Fares | Data-driven `fare_products` + resolve engine |
| Network | Stations, routes, offline policies |
| Sync | Outbox/inbox push-pull-ack API |
| Anomaly | Heuristic scan + investigation states |
| MFA | TOTP enroll/verify/disable |
| Health | live / ready / info |
| Offline design | SQLite schema + policy model |

## Go-live checklist (minimum)
1. Set `AFCS_HMAC_SECRET`, strong DB credentials (see `.env.example`)
2. HTTPS only; lock down CORS
3. Force all users off default passwords
4. Enable MFA for admin/finance roles
5. Register physical devices; store tokens securely
6. Run `php tests/stage3_smoke.php` and `php tests/concurrency_sim.php`
7. Schedule daily `run_reconciliation`
8. Backup MySQL with tested restore

## Explicitly not finished (next engineering cycles)
- Native terminal app with embedded SQLite runtime
- Asymmetric ticket signing (devices hold public key only)
- Full multi-currency settlement packs
- CI pipeline + load tests at city scale
- Hardware adapters (printer/NFC) beyond abstraction intent
- MFA recovery codes / admin reset workflow polish

## Principle followed
Improve → refactor → harden → extend — without discarding working cashier UX.

See `ARCHITECTURE.md` and `STAGE1`–`STAGE6` changelogs for evolution detail.
