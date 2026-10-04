# Terminal Offline Runtime (Security Model)

## Principle

Offline operation is **device-bound and time-limited**, not “any browser when MySQL is off”.

## Issuing authority

1. Device must be **registered** and **ACTIVE** (`api/devices.php?action=register`).
2. While online, device calls `POST api/devices.php?action=offline_grant` with `device_uid` + `auth_token`.
3. Server returns a signed grant: permissions subset, fare snapshot, policies, `expires_at`, HMAC `signature`.
4. Device stores grant in secure local storage (SQLite on native terminals).

## Hard limits

- TTL from `offline_policies.offline_auth_ttl_hours` (capped at 7 days server-side).
- Permissions only what policy allows (e.g. ISSUE_QR, TOPUP_CARD, VALIDATE_QR) — **never** unrestricted refund/admin.
- After expiry, device must re-contact central system.
- Stolen/suspended devices: set status ≠ ACTIVE; no new grants; existing grants expire naturally.

## Sync

Offline mutations → local outbox → `api/sync.php?action=push` (idempotent by `event_id`).

## Browser vs gate

| Client          | Role                                                                            |
| --------------- | ------------------------------------------------------------------------------- |
| Browser POS     | May cache grant for awareness; **must not** sell offline without native runtime |
| Gate / terminal | SQLite (`local_schema.sql`) + verify tickets cryptographically                  |

## Central down, no grant

Login and online APIs fail with **SERVICE_UNAVAILABLE**. UI states that clearly. No silent “demo offline”.
