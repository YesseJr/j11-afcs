<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body   = json_body();

if ($action === 'stations') {
    require_auth();
    $db = get_db();
    $orgId = $_SESSION['user']['organization_id'] ?? null;
    if ($orgId) {
        $stmt = $db->prepare("SELECT * FROM stations WHERE organization_id = ? AND active = 1 ORDER BY name");
        $stmt->execute([$orgId]);
    } else {
        $stmt = $db->query("SELECT * FROM stations WHERE active = 1 ORDER BY name");
    }
    json_ok($stmt->fetchAll());
}

if ($action === 'routes') {
    require_auth();
    $db = get_db();
    $orgId = $_SESSION['user']['organization_id'] ?? null;
    if ($orgId) {
        $stmt = $db->prepare("SELECT * FROM routes WHERE organization_id = ? AND active = 1 ORDER BY name");
        $stmt->execute([$orgId]);
    } else {
        $stmt = $db->query("SELECT * FROM routes WHERE active = 1 ORDER BY name");
    }
    json_ok($stmt->fetchAll());
}

if ($action === 'save_station') {
    require_permission('CONFIGURE_SYSTEM');
    $code = trim($body['code'] ?? '');
    $name = trim($body['name'] ?? '');
    $type = $body['station_type'] ?? 'terminal';
    $zone = $body['zone_code'] ?? null;
    if (!$code || !$name) json_err('code and name required.', 400, 'MISSING_FIELDS');
    $orgId = $_SESSION['user']['organization_id'] ?? null;
    $db = get_db();
    $db->prepare("INSERT INTO stations (organization_id, code, name, station_type, zone_code) VALUES (?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE name=VALUES(name), station_type=VALUES(station_type), zone_code=VALUES(zone_code), active=1")
       ->execute([$orgId, $code, $name, $type, $zone]);
    audit_log('STATION_SAVED', 'station', $code, null, $body);
    json_ok(null, 'Station saved');
}

if ($action === 'save_route') {
    require_permission('CONFIGURE_SYSTEM');
    $code = trim($body['code'] ?? '');
    $name = trim($body['name'] ?? '');
    $dir = $body['direction'] ?? null;
    if (!$code || !$name) json_err('code and name required.', 400, 'MISSING_FIELDS');
    $orgId = $_SESSION['user']['organization_id'] ?? null;
    $db = get_db();
    $db->prepare("INSERT INTO routes (organization_id, code, name, direction) VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE name=VALUES(name), direction=VALUES(direction), active=1")
       ->execute([$orgId, $code, $name, $dir]);
    audit_log('ROUTE_SAVED', 'route', $code, null, $body);
    json_ok(null, 'Route saved');
}

if ($action === 'offline_policies') {
    require_auth();
    $deviceType = $_GET['device_type'] ?? 'cashier_terminal';
    $db = get_db();
    $orgId = $_SESSION['user']['organization_id'] ?? null;
    $stmt = $db->prepare("SELECT policy_key, policy_value, value_type, description FROM offline_policies WHERE device_type = ? AND (organization_id = ? OR organization_id IS NULL)");
    $stmt->execute([$deviceType, $orgId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $v = $r['policy_value'];
        if ($r['value_type'] === 'number') $v = $v + 0;
        if ($r['value_type'] === 'boolean') $v = in_array(strtolower((string)$v), ['1','true','yes'], true);
        if ($r['value_type'] === 'json') $v = json_decode($v, true);
        $out[$r['policy_key']] = $v;
    }
    json_ok(['device_type' => $deviceType, 'policies' => $out, 'raw' => $rows]);
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
