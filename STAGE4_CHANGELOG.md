# Stage 4 — Admin UI, Anomaly Detection, Expanded Tests

## Admin UI
- **Fare Products** screen — create/deactivate fare products (feeds QR pricing)
- **Devices** screen — register devices, show one-time token, change status
- **Settlement** screen — run reconciliation, create settlement batch, view history
- Admin dashboard grid expanded with the three new modules

## Anomaly detection
- Table `anomaly_events` with severity, score, investigation workflow
- Heuristics: excessive refunds, rapid top-ups, large cash variance, large single refund
- `api/anomaly.php` — scan / list / update_status
- User columns prepared for future MFA (`mfa_enabled`, `mfa_secret`)

## Tests
- `tests/concurrency_sim.php` — idempotency uniqueness, transfer conservation, single-use QR validation under lock
- Existing `tests/stage3_smoke.php` still valid

## Preserved
- All cashier flows and prior stages

## Notes
- Anomalies are recorded for investigation; they do not auto-block operations
- MFA columns are ready; enrollment UI is a later step
