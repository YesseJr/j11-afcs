<?php
// ── Idempotency for financial / state-changing APIs ───────────────────────────

/**
 * Resolve idempotency key from header or body.
 */
function get_idempotency_key(?array $body = null): ?string {
    $key = $_SERVER['HTTP_IDEMPOTENCY_KEY']
        ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY']
        ?? ($body['idempotency_key'] ?? null);
    if ($key === null || $key === '') return null;
    $key = trim((string)$key);
    if (strlen($key) > 128) $key = substr($key, 0, 128);
    return $key;
}

/**
 * If a completed response for this key exists, return it (and exit via json).
 * Otherwise return null so the caller proceeds.
 */
function idempotency_begin(string $key, string $scope, ?array $requestBody = null): ?array {
    $db = get_db();
    $hash = $requestBody !== null ? hash('sha256', json_encode($requestBody)) : null;

    $stmt = $db->prepare("SELECT * FROM idempotency_keys WHERE idempotency_key = ? AND scope = ? LIMIT 1");
    $stmt->execute([$key, $scope]);
    $row = $stmt->fetch();

    if ($row) {
        // Replay stored response
        if ($row['response_body'] !== null) {
            $stored = json_decode($row['response_body'], true);
            if (is_array($stored)) {
                http_response_code((int)($row['response_code'] ?: 200));
                header('Content-Type: application/json');
                header('X-Idempotent-Replay: true');
                // Ensure request_id is present
                if (!isset($stored['request_id'])) {
                    $stored['request_id'] = $GLOBALS['AFCS_REQUEST_ID'] ?? null;
                }
                echo json_encode($stored, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        // Key exists but no body yet — concurrent in-flight; treat as conflict
        json_err('A request with this idempotency key is already in progress.', 409, 'IDEMPOTENCY_IN_PROGRESS');
    }

    // Reserve the key
    try {
        $db->prepare("
            INSERT INTO idempotency_keys (idempotency_key, scope, request_hash, expires_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))
        ")->execute([$key, $scope, $hash]);
    } catch (PDOException $e) {
        // Race: another request inserted first — re-read
        $stmt->execute([$key, $scope]);
        $row = $stmt->fetch();
        if ($row && $row['response_body']) {
            $stored = json_decode($row['response_body'], true);
            http_response_code((int)($row['response_code'] ?: 200));
            header('Content-Type: application/json');
            header('X-Idempotent-Replay: true');
            echo json_encode($stored, JSON_UNESCAPED_UNICODE);
            exit;
        }
        json_err('Idempotency key conflict.', 409, 'IDEMPOTENCY_CONFLICT');
    }
    return null;
}

/**
 * Store the successful (or failed) response for future replays.
 */
function idempotency_commit(string $key, string $scope, int $httpCode, array $responseBody): void {
    try {
        $db = get_db();
        $db->prepare("
            UPDATE idempotency_keys
            SET response_code = ?, response_body = ?
            WHERE idempotency_key = ? AND scope = ?
        ")->execute([$httpCode, json_encode($responseBody, JSON_UNESCAPED_UNICODE), $key, $scope]);
    } catch (Throwable $e) {
        error_log('AFCS idempotency_commit failed: ' . $e->getMessage());
    }
}

/**
 * Helper: run a mutating action under idempotency if a key is present.
 * $fn must return [httpCode, responseArray] where responseArray is the full
 * envelope that would have been json_encoded (success/message/data/...).
 */
function with_idempotency(string $scope, ?array $body, callable $fn) {
    $key = get_idempotency_key($body);
    if ($key) {
        idempotency_begin($key, $scope, $body);
    }

    $result = $fn(); // [httpCode, envelope]
    $http = $result[0];
    $envelope = $result[1];
    if (!isset($envelope['request_id'])) {
        $envelope['request_id'] = $GLOBALS['AFCS_REQUEST_ID'] ?? null;
    }

    if ($key) {
        idempotency_commit($key, $scope, $http, $envelope);
    }

    http_response_code($http);
    header('Content-Type: application/json');
    echo json_encode($envelope, JSON_UNESCAPED_UNICODE);
    exit;
}
