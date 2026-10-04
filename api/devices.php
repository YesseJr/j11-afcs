<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body   = json_body();

// ── POST register ────────────────────────────────────────────────────────────
if ($action === 'register') {
    require_permission('MANAGE_DEVICES');

    $device_uid = trim($body['device_uid'] ?? '');
    $type       = $body['device_type'] ?? 'cashier_terminal';
    $name       = trim($body['name'] ?? '');
    $serial     = trim($body['hardware_serial'] ?? '');
    $terminal   = trim($body['terminal_label'] ?? '');
    $station    = trim($body['station_code'] ?? '');
    $allowedTypes = ['cashier_terminal','pos','gate_validator','inspector_mobile','qr_scanner','nfc_reader','printer','station_server','vehicle_validator','other'];

    if (!$device_uid) json_err('device_uid required.', 400, 'MISSING_DEVICE_UID');
    if (!in_array($type, $allowedTypes, true)) json_err('Invalid device_type.', 400, 'INVALID_DEVICE_TYPE');

    $token = bin2hex(random_bytes(32));
    $hash  = password_hash($token, PASSWORD_DEFAULT);
    $user  = $_SESSION['user'];

    $db = get_db();
    $exists = $db->prepare("SELECT id FROM devices WHERE device_uid = ?");
    $exists->execute([$device_uid]);
    if ($exists->fetch()) {
        json_err('Device already registered.', 409, 'DEVICE_EXISTS');
    }

    $db->prepare("
        INSERT INTO devices (device_uid, hardware_serial, device_type, name, organization_id, station_code, terminal_label, status, auth_token_hash, software_version)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)
    ")->execute([
        $device_uid, $serial ?: null, $type, $name ?: $device_uid,
        $user['organization_id'] ?? null, $station ?: null, $terminal ?: null,
        $hash, $body['software_version'] ?? '1.0.0',
    ]);
    $id = (int)$db->lastInsertId();

    audit_log('DEVICE_REGISTERED', 'device', $id, null, [
        'device_uid' => $device_uid, 'device_type' => $type,
    ]);

    // Token returned once — store securely on device
    json_ok([
        'device_id'   => $id,
        'device_uid'  => $device_uid,
        'auth_token'  => $token,
        'status'      => 'ACTIVE',
    ], 'Device registered. Store auth_token securely — it will not be shown again.');
}

// ── POST heartbeat ───────────────────────────────────────────────────────────
if ($action === 'heartbeat') {
    // Devices may heartbeat with token; optional user session
    $device_uid = trim($body['device_uid'] ?? $_GET['device_uid'] ?? '');
    $token     = $body['auth_token'] ?? $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';

    if (!$device_uid) json_err('device_uid required.', 400, 'MISSING_DEVICE_UID');

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM devices WHERE device_uid = ?");
    $stmt->execute([$device_uid]);
    $dev = $stmt->fetch();
    if (!$dev) json_err('Device not found.', 404, 'DEVICE_NOT_FOUND');
    if ($dev['status'] !== 'ACTIVE') json_err('Device is not active.', 403, 'DEVICE_INACTIVE');

    if ($dev['auth_token_hash'] && $token) {
        if (!password_verify($token, $dev['auth_token_hash'])) {
            json_err('Invalid device token.', 401, 'DEVICE_AUTH_FAILED');
        }
    }

    $db->prepare("UPDATE devices SET last_heartbeat = NOW(), software_version = COALESCE(?, software_version) WHERE id = ?")
       ->execute([$body['software_version'] ?? null, $dev['id']]);

    json_ok([
        'device_id' => (int)$dev['id'],
        'status'    => $dev['status'],
        'server_time' => date('c'),
        'offline_until' => $dev['offline_until'],
    ], 'Heartbeat recorded');
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($action === 'list') {
    require_permission('VIEW_DEVICES');
    $db = get_db();
    $status = $_GET['status'] ?? null;
    if ($status) {
        $stmt = $db->prepare("SELECT id, device_uid, hardware_serial, device_type, name, organization_id, station_code, terminal_label, status, software_version, last_heartbeat, last_sync_at, registered_at FROM devices WHERE status = ? ORDER BY registered_at DESC");
        $stmt->execute([$status]);
    } else {
        $stmt = $db->query("SELECT id, device_uid, hardware_serial, device_type, name, organization_id, station_code, terminal_label, status, software_version, last_heartbeat, last_sync_at, registered_at FROM devices ORDER BY registered_at DESC");
    }
    json_ok($stmt->fetchAll());
}

// ── POST update_status ───────────────────────────────────────────────────────
if ($action === 'update_status') {
    require_permission('MANAGE_DEVICES');
    $id = (int)($body['device_id'] ?? 0);
    $status = $body['status'] ?? '';
    $allowed = ['ACTIVE','INACTIVE','SUSPENDED','LOST','MAINTENANCE','RETIRED'];
    if (!$id) json_err('device_id required.', 400, 'MISSING_DEVICE_ID');
    if (!in_array($status, $allowed, true)) json_err('Invalid status.', 400, 'INVALID_STATUS');

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->execute([$id]);
    $dev = $stmt->fetch();
    if (!$dev) json_err('Device not found.', 404, 'DEVICE_NOT_FOUND');

    $db->prepare("UPDATE devices SET status = ? WHERE id = ?")->execute([$status, $id]);
    audit_log('DEVICE_STATUS_CHANGED', 'device', $id, ['status' => $dev['status']], ['status' => $status]);

    json_ok(['device_id' => $id, 'status' => $status], 'Device status updated');
}

// ── POST rotate_token ────────────────────────────────────────────────────────
if ($action === 'rotate_token') {
    require_permission('MANAGE_DEVICES');
    $id = (int)($body['device_id'] ?? 0);
    if (!$id) json_err('device_id required.', 400, 'MISSING_DEVICE_ID');

    $db = get_db();
    $stmt = $db->prepare("SELECT id FROM devices WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) json_err('Device not found.', 404, 'DEVICE_NOT_FOUND');

    $token = bin2hex(random_bytes(32));
    $hash  = password_hash($token, PASSWORD_DEFAULT);
    $db->prepare("UPDATE devices SET auth_token_hash = ? WHERE id = ?")->execute([$hash, $id]);
    audit_log('DEVICE_TOKEN_ROTATED', 'device', $id);

    json_ok(['device_id' => $id, 'auth_token' => $token], 'Token rotated. Store securely.');
}


// ── POST offline_grant ───────────────────────────────────────────────────────
// Security: only ACTIVE enrolled devices with valid token receive a time-bound
// offline capability bundle (policies + fare snapshot + permissions + expiry).
// Browsers without device enrollment must not pretend they can sell offline.
if ($action === 'offline_grant') {
    $device_uid = trim($body['device_uid'] ?? '');
    $token     = $body['auth_token'] ?? $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
    if (!$device_uid || !$token) {
        json_err('device_uid and auth_token required.', 400, 'MISSING_DEVICE_CREDENTIALS');
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM devices WHERE device_uid = ?");
    $stmt->execute([$device_uid]);
    $dev = $stmt->fetch();
    if (!$dev) json_err('Device not registered.', 404, 'DEVICE_NOT_FOUND');
    if ($dev['status'] !== 'ACTIVE') json_err('Device is not active.', 403, 'DEVICE_INACTIVE');
    if (empty($dev['auth_token_hash']) || !password_verify($token, $dev['auth_token_hash'])) {
        audit_log('DEVICE_OFFLINE_GRANT_DENIED', 'device', $dev['id'], null, null, 'Bad token');
        json_err('Invalid device token.', 401, 'DEVICE_AUTH_FAILED');
    }

    $orgId = $dev['organization_id'] ?? null;
    $dtype = $dev['device_type'] ?: 'cashier_terminal';

    // Policies
    $pstmt = $db->prepare("SELECT policy_key, policy_value, value_type FROM offline_policies WHERE device_type = ? AND (organization_id = ? OR organization_id IS NULL)");
    $pstmt->execute([$dtype, $orgId]);
    $policies = [];
    foreach ($pstmt->fetchAll() as $r) {
        $v = $r['policy_value'];
        if ($r['value_type'] === 'number') $v = $v + 0;
        if ($r['value_type'] === 'boolean') $v = in_array(strtolower((string)$v), ['1','true','yes'], true);
        $policies[$r['policy_key']] = $v;
    }
    $ttlHours = (int)($policies['offline_auth_ttl_hours'] ?? 48);
    if ($ttlHours < 1) $ttlHours = 24;
    if ($ttlHours > 168) $ttlHours = 168; // hard cap 7 days

    $expiresAt = date('c', time() + $ttlHours * 3600);

    // Fare snapshot (active products only)
    $fares = list_fare_products($orgId ? (int)$orgId : null, true);
    $fareSnap = array_map(function ($f) {
        return [
            'code' => $f['code'],
            'name' => $f['name'],
            'amount' => (float)$f['amount'],
            'currency' => $f['currency'],
            'media_types' => $f['media_types'],
            'passenger_category' => $f['passenger_category'],
            'validity_hours' => $f['validity_hours'],
            'priority' => (int)$f['priority'],
        ];
    }, $fares);

    // Explicit offline permissions (never include admin/refund unlimited)
    $allowed = [];
    if (!empty($policies['allow_qr_sale'])) $allowed[] = 'ISSUE_QR';
    if (!empty($policies['allow_topup'])) $allowed[] = 'TOPUP_CARD';
    if (!empty($policies['allow_card_issue'])) $allowed[] = 'CREATE_CARD';
    if (!empty($policies['allow_validate_qr'])) $allowed[] = 'VALIDATE_QR';
    if (!empty($policies['allow_validate_card'])) $allowed[] = 'VIEW_CARD';

    $grant = [
        'v' => 1,
        'grant_id' => afcs_uuid(),
        'device_uid' => $device_uid,
        'device_type' => $dtype,
        'issued_at' => date('c'),
        'expires_at' => $expiresAt,
        'policies' => $policies,
        'permissions' => $allowed,
        'fares' => $fareSnap,
        'max_offline_topup' => $policies['max_offline_topup'] ?? null,
    ];
    $canonical = json_encode($grant, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $grant['signature'] = hash_hmac('sha256', $canonical, AFCS_HMAC_SECRET);

    $db->prepare("UPDATE devices SET offline_until = ?, last_sync_at = NOW() WHERE id = ?")
       ->execute([date('Y-m-d H:i:s', strtotime($expiresAt)), $dev['id']]);

    audit_log('DEVICE_OFFLINE_GRANT', 'device', $dev['id'], null, [
        'expires_at' => $expiresAt,
        'permissions' => $allowed,
    ]);

    json_ok($grant, 'Offline grant issued. Operate only within permissions until expiry.');
}


json_err('Unknown action', 404, 'UNKNOWN_ACTION');
