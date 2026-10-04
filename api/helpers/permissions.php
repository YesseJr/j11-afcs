<?php
// ── Permission-based authorization ────────────────────────────────────────────

/**
 * Load permission codes for a user (from user_roles → role_permissions).
 * Falls back to legacy role column mapping if no user_roles rows exist.
 */
function load_user_permissions(int $userId): array {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    $db = get_db();
    $stmt = $db->prepare("
        SELECT DISTINCT p.code
        FROM user_roles ur
        JOIN role_permissions rp ON rp.role_id = ur.role_id
        JOIN permissions p ON p.id = rp.permission_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$codes) {
        // Fallback from legacy users.role
        $role = $db->prepare("SELECT role FROM users WHERE id = ?");
        $role->execute([$userId]);
        $legacy = $role->fetchColumn();
        $fallback = [
            'admin' => ['*'], // treat as all for backward compat
            'supervisor' => [
                'LOGIN','CHANGE_PASSWORD','START_SESSION','END_SESSION','FORCE_END_SESSION',
                'VIEW_SESSION_REPORT','ISSUE_QR','VALIDATE_QR','CREATE_CARD','ACTIVATE_CARD',
                'DEACTIVATE_CARD','TOPUP_CARD','TRANSFER_BALANCE','VIEW_CARD','REFUND_TRANSACTION',
                'VIEW_FINANCIAL_REPORTS','VIEW_DEVICES',
            ],
            'cashier' => [
                'LOGIN','CHANGE_PASSWORD','START_SESSION','END_SESSION','VIEW_SESSION_REPORT',
                'ISSUE_QR','CREATE_CARD','ACTIVATE_CARD','DEACTIVATE_CARD','TOPUP_CARD',
                'TRANSFER_BALANCE','VIEW_CARD',
            ],
        ];
        $codes = $fallback[$legacy] ?? ['LOGIN','CHANGE_PASSWORD'];
    }

    $cache[$userId] = $codes;
    return $codes;
}

function user_can(string $permission, ?array $user = null): bool {
    $user = $user ?? ($_SESSION['user'] ?? null);
    if (!$user || empty($user['id'])) return false;

    // Super-admin style: legacy admin with * or explicit super_admin role
    $perms = $user['permissions'] ?? null;
    if ($perms === null) {
        $perms = load_user_permissions((int)$user['id']);
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['permissions'] = $perms;
        }
    }
    if (in_array('*', $perms, true)) return true;
    return in_array($permission, $perms, true);
}

function require_permission(string $permission): array {
    $user = require_auth();
    if (!empty($user['must_change_password'])) {
        json_err('Password change required before continuing.', 403, 'PASSWORD_CHANGE_REQUIRED');
    }
    if (!user_can($permission, $user)) {
        audit_log('PERMISSION_DENIED', 'permission', $permission, null, null, "Denied: $permission");
        json_err("Permission denied: $permission", 403, 'PERMISSION_DENIED', ['required' => $permission]);
    }
    return $user;
}

/**
 * Attach permissions list to session user after login.
 */
function attach_permissions_to_session(int $userId): array {
    $perms = load_user_permissions($userId);
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['permissions'] = $perms;
    }
    return $perms;
}
