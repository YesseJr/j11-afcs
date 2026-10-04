<?php
/** Lightweight anomaly detection foundation — records signals, does not auto-block. */

function anomaly_record(string $type, string $severity, float $score, array $opts = []): ?string {
    try {
        $db = get_db();
        $uid = afcs_uuid();
        $db->prepare("
            INSERT INTO anomaly_events (
                event_uid, organization_id, anomaly_type, severity, score,
                entity_type, entity_id, actor_user_id, device_id, evidence_json, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'OPEN')
        ")->execute([
            $uid,
            $opts['organization_id'] ?? ($_SESSION['user']['organization_id'] ?? null),
            $type,
            $severity,
            $score,
            $opts['entity_type'] ?? null,
            isset($opts['entity_id']) ? (string)$opts['entity_id'] : null,
            $opts['actor_user_id'] ?? ($_SESSION['user_id'] ?? null),
            $opts['device_id'] ?? null,
            isset($opts['evidence']) ? json_encode($opts['evidence']) : null,
        ]);
        return $uid;
    } catch (Throwable $e) {
        error_log('anomaly_record failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Scan recent activity for simple heuristic signals.
 */
function anomaly_scan_recent(int $hours = 24): array {
    $db = get_db();
    $since = date('Y-m-d H:i:s', time() - $hours * 3600);
    $found = [];

    // Excessive refunds by same user
    $stmt = $db->prepare("
        SELECT requested_by, COUNT(*) c, SUM(amount) s
        FROM refunds WHERE created_at >= ? AND status='COMPLETED'
        GROUP BY requested_by HAVING c >= 5
    ");
    $stmt->execute([$since]);
    foreach ($stmt->fetchAll() as $r) {
        $uid = anomaly_record('EXCESSIVE_REFUNDS', 'HIGH', min(99, 40 + (int)$r['c'] * 5), [
            'entity_type' => 'user', 'entity_id' => $r['requested_by'],
            'actor_user_id' => $r['requested_by'],
            'evidence' => ['count' => (int)$r['c'], 'total' => (float)$r['s'], 'window_hours' => $hours],
        ]);
        $found[] = ['type' => 'EXCESSIVE_REFUNDS', 'uid' => $uid, 'user_id' => $r['requested_by']];
    }

    // Rapid repeated top-ups same card
    $stmt = $db->prepare("
        SELECT card_id, COUNT(*) c, SUM(amount) s
        FROM transactions
        WHERE type='topup' AND created_at >= ? AND status='COMPLETED' AND card_id IS NOT NULL
        GROUP BY card_id HAVING c >= 8
    ");
    $stmt->execute([$since]);
    foreach ($stmt->fetchAll() as $r) {
        $uid = anomaly_record('RAPID_TOPUPS', 'MEDIUM', min(90, 30 + (int)$r['c'] * 4), [
            'entity_type' => 'card', 'entity_id' => $r['card_id'],
            'evidence' => ['count' => (int)$r['c'], 'total' => (float)$r['s']],
        ]);
        $found[] = ['type' => 'RAPID_TOPUPS', 'uid' => $uid, 'card_id' => $r['card_id']];
    }

    // Large cash variance sessions
    $stmt = $db->prepare("
        SELECT session_id, variance FROM session_cash
        WHERE ABS(variance) >= 5000 AND updated_at >= ?
    ");
    $stmt->execute([$since]);
    foreach ($stmt->fetchAll() as $r) {
        $uid = anomaly_record('CASH_VARIANCE_LARGE', 'HIGH', 70, [
            'entity_type' => 'cashier_session', 'entity_id' => $r['session_id'],
            'evidence' => ['variance' => (float)$r['variance']],
        ]);
        $found[] = ['type' => 'CASH_VARIANCE_LARGE', 'uid' => $uid, 'session_id' => $r['session_id']];
    }

    return $found;
}
