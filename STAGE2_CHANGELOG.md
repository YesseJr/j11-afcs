# Stage 2 — RBAC, Devices, Refunds, Sync Foundation, Fare Products

## What changed

### RBAC
- Tables: `permissions`, `roles`, `role_permissions`, `user_roles`
- Seeded roles: super_admin, admin, supervisor, cashier, inspector, finance, auditor, device_admin, readonly
- Seeded permission codes (CREATE_CARD, TOPUP_CARD, REFUND_TRANSACTION, MANAGE_DEVICES, …)
- Existing users mapped from legacy `users.role` into `user_roles`
- `require_permission($code)` enforces server-side authorization
- Login response includes `permissions` array for the client
- Legacy admin continues to work via full permission set

### Device management (`api/devices.php`)
- Register device → returns one-time `auth_token`
- Heartbeat with device token
- List devices, update status, rotate token
- Device table extended with `auth_token_hash`, `offline_until`

### Refunds & reversals (`api/finance.php`)
- `refund` action: FULL / PARTIAL / REVERSAL
- Never rewrites original transaction; creates `refunds` row + linkage txn
- Top-up refunds claw back card balance via ledger DEBIT
- Idempotent
- Prior refund sum checked so total cannot exceed original
- Session cash: opening float + close cash count with expected/actual/variance

### Sync foundation (`api/sync.php`)
- `sync_outbox` / `sync_inbox` tables
- `push` — device uploads offline events (dedup by event_id)
- `pull` — device downloads pending server events
- `ack` — mark outbox events synced
- `enqueue` — admin enqueues config/fare pushes
- `status` — queue health for ops

### Fare products
- `fare_products` table with versioned/effective dating
- Default STUDENT_FLAT product seeded from system_config

### Permission gates on existing endpoints
- Card register/topup/activate/deactivate/transfer/lookup
- QR sell
- Session start / admin force-close
- Inventory management

## Preserved
- All cashier and admin UI flows
- Session resume semantics
- Existing transaction history
- Stage 1 ledger dual-write, idempotency, signed QR, audit

## Security notes
- Device tokens shown once at registration — store offline on device
- Permission denials are audited
- Refunds require REFUND_TRANSACTION (finance/admin/supervisor)

## What remains
- Offline local SQLite + client outbox processor
- Full configurable multi-route fare engine UI
- Settlement batch jobs
- MFA, richer fraud signals
- Automated test suite
- Frontend screens for devices / refunds / reconciliation
