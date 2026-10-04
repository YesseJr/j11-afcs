<?php
/**
 * Liveness / readiness for load balancers and ops monitoring.
 * Does not require authentication.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/response.php';

$action = $_GET['action'] ?? 'live';
$GLOBALS['AFCS_REQUEST_ID'] = $GLOBALS['AFCS_REQUEST_ID'] ?? bin2hex(random_bytes(8));

if ($action === 'live' || $action === 'liveness') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'service' => 'afcs-api',
        'time' => date('c'),
    ]);
    exit;
}

if ($action === 'ready' || $action === 'readiness') {
    $checks = [];
    $ok = true;
    try {
        $db = get_db();
        $db->query('SELECT 1');
        $checks['database'] = 'ok';
        $checks['organizations'] = (int)$db->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
    } catch (Throwable $e) {
        $ok = false;
        $checks['database'] = 'fail: ' . $e->getMessage();
    }
    http_response_code($ok ? 200 : 503);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $ok ? 'ready' : 'not_ready',
        'checks' => $checks,
        'time' => date('c'),
    ]);
    exit;
}

if ($action === 'info') {
    header('Content-Type: application/json');
    echo json_encode([
        'service' => 'AFCS',
        'version' => '4.5.0',
        'php' => PHP_VERSION,
        'time' => date('c'),
        'features' => [
            'ledger', 'idempotency', 'signed_qr', 'rbac', 'devices',
            'refunds', 'settlement', 'fare_engine', 'mfa', 'anomaly', 'sync',
        ],
    ]);
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['status' => 'unknown_action']);
