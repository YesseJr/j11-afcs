<?php
// Shared bootstrap for every API endpoint.
// Wires DB, helpers, request ID, and CORS.

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/audit.php';
require_once __DIR__ . '/helpers/ledger.php';
require_once __DIR__ . '/helpers/idempotency.php';
require_once __DIR__ . '/helpers/permissions.php';
require_once __DIR__ . '/helpers/fare_engine.php';
require_once __DIR__ . '/helpers/anomaly.php';
require_once __DIR__ . '/helpers/totp.php';

// Request correlation ID (clients may supply X-Request-Id)
$GLOBALS['AFCS_REQUEST_ID'] = $_SERVER['HTTP_X_REQUEST_ID']
    ?? $_SERVER['HTTP_X_CORRELATION_ID']
    ?? afcs_uuid();
header('X-Request-Id: ' . $GLOBALS['AFCS_REQUEST_ID']);

// ── CORS ─────────────────────────────────────────────────────────────────────
// Production: set AFCS_CORS_ORIGIN to a specific origin, or leave empty for
// same-origin only. Never use * in production with credentials.
$corsOrigin = AFCS_CORS_ORIGIN;
if ($corsOrigin === '*') {
    header('Access-Control-Allow-Origin: *');
} elseif ($corsOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $corsOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    // Same-origin friendly: reflect Origin only if it matches host (dev convenience)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        // Allow localhost / 127.0.0.1 variants for local development
        if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }
    }
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Idempotency-Key, X-Idempotency-Key, X-Request-Id, X-Correlation-Id');
header('Access-Control-Expose-Headers: X-Request-Id, X-Idempotent-Replay');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
