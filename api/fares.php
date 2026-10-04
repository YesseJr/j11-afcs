<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body   = json_body();

// ── GET resolve ──────────────────────────────────────────────────────────────
if ($action === 'resolve') {
    require_auth();
    $fare = resolve_fare([
        'organization_id' => $_SESSION['user']['organization_id'] ?? null,
        'media' => $_GET['media'] ?? 'QR',
        'passenger_category' => $_GET['passenger_category'] ?? 'STUDENT',
        'route_code' => $_GET['route_code'] ?? null,
        'zone_code' => $_GET['zone_code'] ?? null,
    ]);
    if (!$fare) json_err('No matching fare product.', 404, 'FARE_NOT_FOUND');
    json_ok($fare);
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($action === 'list') {
    require_auth();
    $orgId = $_SESSION['user']['organization_id'] ?? null;
    $activeOnly = ($_GET['all'] ?? '') !== '1';
    json_ok(list_fare_products($orgId ? (int)$orgId : null, $activeOnly));
}

// ── POST create / update ─────────────────────────────────────────────────────
if ($action === 'save') {
    require_permission('MANAGE_FARES');
    $id = (int)($body['id'] ?? 0);
    $code = trim($body['code'] ?? '');
    $name = trim($body['name'] ?? '');
    $amount = isset($body['amount']) ? (float)$body['amount'] : null;
    $type = $body['product_type'] ?? 'FLAT';
    $media = $body['media_types'] ?? 'QR';
    $category = $body['passenger_category'] ?? 'ADULT';
    $currency = $body['currency'] ?? 'TZS';
    $validity = isset($body['validity_hours']) ? (int)$body['validity_hours'] : null;
    $priority = (int)($body['priority'] ?? 100);
    $route = $body['route_code'] ?? null;
    $zone = $body['zone_code'] ?? null;
    $from = $body['effective_from'] ?? null;
    $to = $body['effective_to'] ?? null;
    $active = isset($body['active']) ? (int)(bool)$body['active'] : 1;

    if (!$code || !$name) json_err('code and name required.', 400, 'MISSING_FIELDS');
    if ($amount === null || $amount < 0) json_err('Valid amount required.', 400, 'INVALID_AMOUNT');

    $db = get_db();
    $orgId = $_SESSION['user']['organization_id'] ?? null;

    if ($id) {
        $db->prepare("
            UPDATE fare_products SET code=?, name=?, product_type=?, media_types=?, passenger_category=?,
                amount=?, currency=?, validity_hours=?, priority=?, route_code=?, zone_code=?,
                effective_from=?, effective_to=?, active=?
            WHERE id=?
        ")->execute([$code, $name, $type, $media, $category, $amount, $currency, $validity, $priority, $route, $zone, $from, $to, $active, $id]);
        audit_log('FARE_UPDATED', 'fare_product', $id, null, $body);
        json_ok(['id' => $id], 'Fare product updated');
    }

    $db->prepare("
        INSERT INTO fare_products (organization_id, code, name, product_type, media_types, passenger_category, amount, currency, validity_hours, priority, route_code, zone_code, effective_from, effective_to, active)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([$orgId, $code, $name, $type, $media, $category, $amount, $currency, $validity, $priority, $route, $zone, $from, $to, $active]);
    $newId = (int)$db->lastInsertId();
    audit_log('FARE_CREATED', 'fare_product', $newId, null, $body);
    json_ok(['id' => $newId], 'Fare product created');
}

// ── POST deactivate ──────────────────────────────────────────────────────────
if ($action === 'deactivate') {
    require_permission('MANAGE_FARES');
    $id = (int)($body['id'] ?? 0);
    if (!$id) json_err('id required.', 400, 'MISSING_ID');
    get_db()->prepare("UPDATE fare_products SET active = 0 WHERE id = ?")->execute([$id]);
    audit_log('FARE_DEACTIVATED', 'fare_product', $id);
    json_ok(null, 'Fare product deactivated');
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
