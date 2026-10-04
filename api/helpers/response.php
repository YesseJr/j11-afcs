<?php
// ── JSON response helpers ─────────────────────────────────────────────────────
// Consistent envelope: { success, error_code?, message, data, request_id }

function json_ok($data = null, string $msg = 'OK', int $http = 200) {
    http_response_code($http);
    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'message'    => $msg,
        'data'       => $data,
        'request_id' => $GLOBALS['AFCS_REQUEST_ID'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $msg, int $code = 400, string $errorCode = 'BAD_REQUEST', $data = null) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success'    => false,
        'error_code' => $errorCode,
        'message'    => $msg,
        'data'       => $data,
        'request_id' => $GLOBALS['AFCS_REQUEST_ID'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_body() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

/** Generate a UUID v4-like request / event id */
function afcs_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
