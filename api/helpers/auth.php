<?php
// ── Auth / session guards ─────────────────────────────────────────────────────

function require_auth() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        json_err('Not authenticated', 401, 'NOT_AUTHENTICATED');
    }
    return $_SESSION['user'] ?? [];
}

function get_active_session_id() {
    $sid = $_SESSION['cashier_session_id'] ?? 0;
    if (!$sid) json_err('No active cashier session.', 403, 'NO_ACTIVE_SESSION');
    return (int)$sid;
}

function require_admin() {
    $user = require_auth();
    if (($user['role'] ?? '') !== 'admin') {
        json_err('Admin access required for this action.', 403, 'ADMIN_REQUIRED');
    }
    return $user;
}

/**
 * Optional: require that the user is not forced to change password
 * before accessing operational endpoints.
 */
function require_password_ok() {
    $user = require_auth();
    if (!empty($user['must_change_password'])) {
        json_err('Password change required before continuing.', 403, 'PASSWORD_CHANGE_REQUIRED');
    }
    return $user;
}
