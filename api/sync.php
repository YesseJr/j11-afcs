<?php
/**
 * Synchronization endpoints — foundation for offline-first terminals.
 * Devices push inbox events; server acknowledges; outbox delivers server→device events.
 */
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body   = json_body();

function resolve_device_from_request(array $body): ?array {
    $uid = trim($body['device_uid'] ?? $_SERVER['HTTP_X_DEVICE_UID'] ?? '');
    $token = $body['auth_token'] ?? $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
    if (!$uid) return null;
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM devices WHERE device_uid = ?");
    $stmt->execute([$uid]);
    $dev = $stmt->fetch();
    if (!$dev || $dev['status'] !== 'ACTIVE') return null;
    if ($dev['auth_token_hash'] && $token && !password_verify($token, $dev['auth_token_hash'])) {
        return null;
    }
    return $dev;
}

// ── POST push (device → server inbox) ────────────────────────────────────────
if ($action === 'push') {
    $dev = resolve_device_from_request($body);
    // Also allow authenticated admin/cashier for testing without device token
    if (!$dev) {
        require_auth();
    }

    $events = $body['events'] ?? [];
    if (!is_array($events) || !$events) {
        json_err('events array required.', 400, 'MISSING_EVENTS');
    }
    if (count($events) > 500) {
        json_err('Maximum 500 events per push.', 400, 'TOO_MANY_EVENTS');
    }

    $db = get_db();
    $accepted = [];
    $duplicates = [];
    $rejected = [];

    foreach ($events as $ev) {
        $eventId = $ev['event_id'] ?? null;
        $type    = $ev['event_type'] ?? null;
        $payload = $ev['payload'] ?? null;
        if (!$eventId || !$type || $payload === null) {
            $rejected[] = ['event_id' => $eventId, 'reason' => 'missing fields'];
            continue;
        }

        try {
            $db->prepare("
                INSERT INTO sync_inbox (event_id, device_uid, sequence_number, event_type, payload, local_timestamp, process_status)
                VALUES (?, ?, ?, ?, ?, ?, 'RECEIVED')
            ")->execute([
                $eventId,
                $dev['device_uid'] ?? ($body['device_uid'] ?? 'unknown'),
                $ev['sequence_number'] ?? null,
                $type,
                is_string($payload) ? $payload : json_encode($payload),
                $ev['local_timestamp'] ?? null,
            ]);
            $accepted[] = $eventId;
        } catch (PDOException $e) {
            // Duplicate event_id
            if (str_contains($e->getMessage(), 'Duplicate') || (int)$e->getCode() === 23000) {
                $db->prepare("UPDATE sync_inbox SET process_status = 'DUPLICATE' WHERE event_id = ? AND process_status = 'RECEIVED'")
                   ->execute([$eventId]);
                $duplicates[] = $eventId;
            } else {
                $rejected[] = ['event_id' => $eventId, 'reason' => 'db error'];
            }
        }
    }

    if ($dev) {
        $db->prepare("UPDATE devices SET last_sync_at = NOW() WHERE id = ?")->execute([$dev['id']]);
    }

    json_ok([
        'accepted' => $accepted,
        'duplicates' => $duplicates,
        'rejected' => $rejected,
        'server_time' => date('c'),
    ], 'Push processed');
}

// ── GET pull (server outbox → device) ────────────────────────────────────────
if ($action === 'pull') {
    $dev = resolve_device_from_request($body + $_GET);
    if (!$dev) {
        // Allow session auth for admin testing
        require_permission('VIEW_DEVICES');
        $deviceUid = $_GET['device_uid'] ?? '';
        if (!$deviceUid) json_err('device_uid required.', 400, 'MISSING_DEVICE_UID');
    } else {
        $deviceUid = $dev['device_uid'];
    }

    $db = get_db();
    $since = (int)($_GET['after_sequence'] ?? 0);
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));

    $stmt = $db->prepare("
        SELECT event_id, sequence_number, event_type, payload, local_timestamp, server_timestamp, created_at
        FROM sync_outbox
        WHERE (device_uid = ? OR device_uid IS NULL)
          AND sync_status = 'PENDING'
          AND (sequence_number IS NULL OR sequence_number > ?)
        ORDER BY id ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, $deviceUid);
    $stmt->bindValue(2, $since, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    json_ok([
        'events' => $rows,
        'server_time' => date('c'),
    ]);
}

// ── POST ack ─────────────────────────────────────────────────────────────────
if ($action === 'ack') {
    $dev = resolve_device_from_request($body);
    if (!$dev) require_auth();

    $eventIds = $body['event_ids'] ?? [];
    if (!$eventIds || !is_array($eventIds)) {
        json_err('event_ids array required.', 400, 'MISSING_EVENT_IDS');
    }

    $db = get_db();
    $stmt = $db->prepare("UPDATE sync_outbox SET sync_status = 'SYNCED', synced_at = NOW(3) WHERE event_id = ?");
    $n = 0;
    foreach ($eventIds as $eid) {
        $stmt->execute([$eid]);
        $n += $stmt->rowCount();
    }

    json_ok(['acked' => $n], 'Acknowledgements recorded');
}

// ── POST enqueue (server-side: put event in outbox for devices) ───────────────
if ($action === 'enqueue') {
    require_permission('MANAGE_DEVICES');
    $eventType = $body['event_type'] ?? '';
    $payload   = $body['payload'] ?? null;
    $deviceUid = $body['device_uid'] ?? null; // null = broadcast

    if (!$eventType || $payload === null) {
        json_err('event_type and payload required.', 400, 'MISSING_FIELDS');
    }

    $db = get_db();
    $eventId = afcs_uuid();
    $db->prepare("
        INSERT INTO sync_outbox (event_id, device_uid, event_type, payload, server_timestamp, sync_status, correlation_id)
        VALUES (?, ?, ?, ?, NOW(3), 'PENDING', ?)
    ")->execute([
        $eventId, $deviceUid, $eventType,
        is_string($payload) ? $payload : json_encode($payload),
        $GLOBALS['AFCS_REQUEST_ID'],
    ]);

    json_ok(['event_id' => $eventId], 'Event enqueued');
}

// ── GET status (ops) ─────────────────────────────────────────────────────────
if ($action === 'status') {
    require_permission('VIEW_DEVICES');
    $db = get_db();
    $outbox = $db->query("SELECT sync_status, COUNT(*) AS n FROM sync_outbox GROUP BY sync_status")->fetchAll();
    $inbox  = $db->query("SELECT process_status, COUNT(*) AS n FROM sync_inbox GROUP BY process_status")->fetchAll();
    json_ok(['outbox' => $outbox, 'inbox' => $inbox]);
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
