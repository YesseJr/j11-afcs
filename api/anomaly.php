<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body = json_body();

if ($action === 'scan') {
    require_permission('VIEW_AUDIT_LOGS');
    $hours = min(168, max(1, (int)($body['hours'] ?? $_GET['hours'] ?? 24)));
    $found = anomaly_scan_recent($hours);
    json_ok(['found' => $found, 'count' => count($found)], 'Scan complete');
}

if ($action === 'list') {
    require_permission('VIEW_AUDIT_LOGS');
    $status = $_GET['status'] ?? 'OPEN';
    $db = get_db();
    if ($status === 'ALL') {
        $rows = $db->query("SELECT * FROM anomaly_events ORDER BY created_at DESC LIMIT 100")->fetchAll();
    } else {
        $stmt = $db->prepare("SELECT * FROM anomaly_events WHERE status = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$status]);
        $rows = $stmt->fetchAll();
    }
    json_ok($rows);
}

if ($action === 'update_status') {
    require_permission('VIEW_AUDIT_LOGS');
    $id = (int)($body['id'] ?? 0);
    $status = $body['status'] ?? '';
    $notes = $body['review_notes'] ?? null;
    if (!$id || !in_array($status, ['OPEN','INVESTIGATING','RESOLVED','DISMISSED'], true)) {
        json_err('Invalid id or status.', 400, 'INVALID_INPUT');
    }
    $db = get_db();
    $db->prepare("
        UPDATE anomaly_events SET status = ?, reviewer_id = ?, review_notes = ?,
        resolved_at = CASE WHEN ? IN ('RESOLVED','DISMISSED') THEN NOW(3) ELSE resolved_at END
        WHERE id = ?
    ")->execute([$status, $_SESSION['user_id'], $notes, $status, $id]);
    audit_log('ANOMALY_REVIEWED', 'anomaly_event', $id, null, ['status' => $status]);
    json_ok(null, 'Updated');
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
