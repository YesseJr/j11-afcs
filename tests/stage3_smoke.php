<?php
/**
 * Stage 3 smoke checks — run: php tests/stage3_smoke.php
 * Requires MySQL with AFCS schema reachable via config.
 */
error_reporting(E_ALL);
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/response.php';
require_once __DIR__ . '/../api/helpers/ledger.php';
require_once __DIR__ . '/../api/helpers/fare_engine.php';

$failed = 0;
function check($label, $cond) {
    global $failed;
    if ($cond) echo "[PASS] $label\n";
    else { echo "[FAIL] $label\n"; $failed++; }
}

try {
    $db = get_db();
    check('DB connected', $db instanceof PDO);
    check('organizations table', (int)$db->query("SELECT COUNT(*) FROM organizations")->fetchColumn() >= 1);
    check('permissions seeded', (int)$db->query("SELECT COUNT(*) FROM permissions")->fetchColumn() >= 10);
    check('roles seeded', (int)$db->query("SELECT COUNT(*) FROM roles")->fetchColumn() >= 5);
    check('fare_products seeded', (int)$db->query("SELECT COUNT(*) FROM fare_products")->fetchColumn() >= 1);
    check('stations seeded', (int)$db->query("SELECT COUNT(*) FROM stations")->fetchColumn() >= 1);
    check('offline_policies seeded', (int)$db->query("SELECT COUNT(*) FROM offline_policies")->fetchColumn() >= 1);

    $fare = resolve_fare(['media' => 'QR', 'passenger_category' => 'STUDENT']);
    check('resolve_fare student QR', $fare && (float)$fare['amount'] > 0);

    check('idempotency_keys table', (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='idempotency_keys'")->fetchColumn() === 1);
    check('ledger_entries table', (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ledger_entries'")->fetchColumn() === 1);
    check('sync_outbox table', (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='sync_outbox'")->fetchColumn() === 1);
    check('refunds table', (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='refunds'")->fetchColumn() === 1);
    check('settlement_batches table', (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='settlement_batches'")->fetchColumn() === 1);

    echo $failed ? "\n$failed check(s) failed\n" : "\nAll checks passed\n";
    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    echo "[FAIL] Exception: " . $e->getMessage() . "\n";
    exit(1);
}
