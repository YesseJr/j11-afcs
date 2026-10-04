# Stage 1 — Security, Ledger Foundation, Idempotency, Audit

## What changed

### Database (`api/config/database.php`)
- New tables: `organizations`, `devices`, `ledger_entries`, `idempotency_keys`, `audit_logs`, `system_config`
- New user columns: `organization_id`, `must_change_password`, `failed_login_attempts`, `locked_until`, `last_login_at`, `password_changed_at`
- Transaction columns: `correlation_id`, `idempotency_key`, `status`, `currency`
- QR tickets: `signature` column
- Seeded default organization `DART` and system config (fare, limits, password policy)
- Seeded users now have `must_change_password = 1`
- Env-var support for DB credentials and `AFCS_HMAC_SECRET` / `AFCS_CORS_ORIGIN`
- `get_config()` helper for data-driven settings

### Helpers
- `helpers/response.php` — structured envelope with `error_code` + `request_id`
- `helpers/audit.php` — immutable audit logger
- `helpers/ledger.php` — dual-write ledger append + balance reconstruction
- `helpers/idempotency.php` — key reservation, replay, commit
- `helpers/auth.php` — `require_password_ok()`

### Auth (`api/auth.php`)
- Failed-login counter + temporary account lockout
- Login / logout / session events audited
- Stronger password policy (min length from config, reject weak numeric)
- `must_change_password` cleared on successful change
- All error responses use `error_code`

### Cards (`api/cards.php`)
- Top-up and balance transfer wrapped in DB transactions with `SELECT … FOR UPDATE`
- Dual-write to `ledger_entries` (CREDIT/DEBIT)
- Idempotency on top-up and balance transfer
- Consistent deadlock-safe lock ordering on transfer
- Audit events for register / top-up / activate / deactivate / transfer / penalty

### QR (`api/qr.php`)
- HMAC-SHA256 signed ticket payloads (`signature` column + `sig` in payload)
- Signature verification on validate (legacy unsigned still accepted during migration)
- Atomic validation with row lock (prevents double-redemption races)
- Fare / validity driven by `system_config`
- Idempotency on sell
- Sale wrapped in DB transaction

### Bootstrap / CORS
- Request ID generation and reflection (`X-Request-Id`)
- CORS no longer defaults to `*`; allows localhost for dev; configurable via env

### Frontend
- API client supports `Idempotency-Key` header
- Top-up, QR sell, balance transfer send idempotency keys
- Forced password-change screen after login when `must_change_password` is set
- Login screen no longer advertises default passwords

## Preserved functionality
- All existing cashier screens and workflows
- Session resume / terminal lock / admin force-close
- Card inventory, registration, activation, reports
- Admin console
- Demo seed cards and blank inventory

## Security implications
- Default accounts must change password before operational use
- Account lockout after repeated failures
- Financial operations are atomic and idempotent
- QR tickets are signed (forgery resistance)
- Audit trail for privileged and financial actions
- CORS tightened

## What remains (later stages)
- Full RBAC permissions model
- Device enrollment & authentication
- Offline sync engine
- Configurable multi-route fare engine
- Settlement / reconciliation UI
- MFA, full payment provider abstraction
- Comprehensive automated test suite

## Migration notes
- Existing databases are upgraded in-place via `CREATE TABLE IF NOT EXISTS` + safe `ALTER`s
- Existing `cards.balance` is retained; ledger is dual-written going forward
- Existing unsigned QR tickets remain validatable
- Set `AFCS_HMAC_SECRET` in production (never use the default)
