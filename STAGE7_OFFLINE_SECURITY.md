# Stage 7 — Security-first offline path

## A — Honest unavailable UX
- DB failures return `SERVICE_UNAVAILABLE` without leaking SQLSTATE (unless `AFCS_APP_ENV=development`)
- API client normalizes network/DB errors
- Login probes `health.php?action=ready` and disables login when central is down
- Explains enrolled-device offline grant requirement

## B — Enrolled-device offline grant
- `POST api/devices.php?action=offline_grant`
- Requires ACTIVE device + valid token
- Time-bound grant: policies, fare snapshot, limited permissions, HMAC signature
- TTL capped at 7 days
- Browser helpers store/validate expiry; do not enable unrestricted offline login

## Not claimed
Full offline ticket sales in the browser with MySQL stopped and no device enrollment.
