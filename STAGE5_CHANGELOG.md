# Stage 5 — MFA, Health, Offline SQLite Design, Deployment Hardening

## MFA (TOTP)
- Pure-PHP TOTP (`helpers/totp.php`) — no Composer dependency
- `mfa_enroll_begin` / `mfa_enroll_confirm` / `mfa_disable` / `mfa_verify`
- Login challenges MFA when enabled; UI supports second step
- Secrets stored on user row; enrollment requires live code confirmation

## Observability
- `api/health.php?action=live` — liveness
- `api/health.php?action=ready` — DB readiness
- `api/health.php?action=info` — version/feature flags

## Offline
- `offline/local_schema.sql` — SQLite schema for native terminals
- `offline/README.md` — trust rules and sync contract
- Browser outbox remains available via `offline.js`

## Deployment
- `.env.example` for DB + HMAC + CORS
- `ARCHITECTURE.md` platform overview and go-live checklist

## Preserved
- All prior stages and cashier/admin workflows
