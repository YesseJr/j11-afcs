<?php
// ── Immutable audit logging ───────────────────────────────────────────────────

function audit_log(
    string $action,
    ?string $entityType = null,
    $entityId = null,
    $previousState = null,
    $newState = null,
    ?string $reason = null,
    ?string $correlationId = null
): void {
    try {
        $db = get_db();
        $user = $_SESSION['user'] ?? [];
        $stmt = $db->prepare("
            INSERT INTO audit_logs (
                event_uid, organization_id, actor_user_id, actor_username, actor_role,
                action, entity_type, entity_id, previous_state, new_state, reason,
                ip_address, user_agent, request_id, correlation_id, source
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'api')
        ");
        $stmt->execute([
            afcs_uuid(),
            $user['organization_id'] ?? null,
            $user['id'] ?? ($_SESSION['user_id'] ?? null),
            $user['username'] ?? null,
            $user['role'] ?? null,
            $action,
            $entityType,
            $entityId !== null ? (string)$entityId : null,
            $previousState !== null ? json_encode($previousState) : null,
            $newState !== null ? json_encode($newState) : null,
            $reason,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            $GLOBALS['AFCS_REQUEST_ID'] ?? null,
            $correlationId,
        ]);
    } catch (Throwable $e) {
        // Audit failure must never break the business operation
        error_log('AFCS audit_log failed: ' . $e->getMessage());
    }
}
