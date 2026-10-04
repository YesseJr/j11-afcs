<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$body   = json_body();

// ── POST /api/auth.php?action=login ──────────────────────────────────────────
if ($action === 'login') {
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (!$username || !$password) {
        json_err('Username and password required.', 400, 'MISSING_CREDENTIALS');
    }

    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $maxFails = (int)get_config('max_failed_logins', 5);
    $lockMins = (int)get_config('lockout_minutes', 15);

    if ($user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        audit_log('LOGIN_BLOCKED', 'user', $user['id'], null, null, 'Account locked');
        json_err('Account temporarily locked due to failed login attempts. Try again later.', 423, 'ACCOUNT_LOCKED');
    }

    if (!$user || !(int)$user['active'] || !password_verify($password, $user['password_hash'])) {
        if ($user) {
            $fails = (int)$user['failed_login_attempts'] + 1;
            $lockUntil = null;
            if ($fails >= $maxFails) {
                $lockUntil = date('Y-m-d H:i:s', time() + $lockMins * 60);
                $fails = 0;
            }
            $db->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?")
               ->execute([$fails, $lockUntil, $user['id']]);
            audit_log('LOGIN_FAILED', 'user', $user['id'], null, ['attempts' => $fails], 'Invalid password');
        } else {
            audit_log('LOGIN_FAILED', 'user', null, null, ['username' => $username], 'Unknown user');
        }
        json_err('Invalid username or password.', 401, 'INVALID_CREDENTIALS');
    }

    // Successful password verification
    $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?")
       ->execute([$user['id']]);

    // MFA challenge — do not fully establish session until TOTP verified
    if (!empty($user['mfa_enabled']) && !empty($user['mfa_secret'])) {
        $_SESSION['mfa_pending_user_id'] = (int)$user['id'];
        audit_log('LOGIN_MFA_REQUIRED', 'user', $user['id']);
        json_ok([
            'mfa_required' => true,
            'username' => $user['username'],
        ], 'MFA code required');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user']    = [
        'id'                    => (int)$user['id'],
        'username'              => $user['username'],
        'name'                  => $user['first_name'] . ' ' . $user['last_name'],
        'role'                  => $user['role'],
        'terminal'              => $user['terminal'],
        'organization_id'       => $user['organization_id'] ?? null,
        'must_change_password'  => (int)($user['must_change_password'] ?? 0),
        'mfa_enabled'           => (int)($user['mfa_enabled'] ?? 0),
    ];
    attach_permissions_to_session((int)$user['id']);
    unset($_SESSION['cashier_session_id']);
    unset($_SESSION['mfa_pending_user_id']);

    audit_log('LOGIN_SUCCESS', 'user', $user['id']);

    $resume_session  = null;
    $blocked_session = null;

    if ($user['role'] !== 'admin') {
        $open = $db->prepare("
            SELECT cs.id, cs.user_id, cs.started_at,
                   CONCAT(u.first_name,' ',u.last_name) AS cashier_name
            FROM cashier_sessions cs
            JOIN users u ON u.id = cs.user_id
            WHERE cs.terminal = ? AND cs.ended_at IS NULL
            LIMIT 1
        ");
        $open->execute([$user['terminal']]);
        $openSession = $open->fetch();

        if ($openSession) {
            if ((int)$openSession['user_id'] === (int)$user['id']) {
                $_SESSION['cashier_session_id'] = $openSession['id'];
                $resume_session = ['session_id' => $openSession['id'], 'started_at' => $openSession['started_at']];
            } else {
                $blocked_session = [
                    'cashier_name' => $openSession['cashier_name'],
                    'started_at'   => $openSession['started_at'],
                ];
            }
        }
    }

    json_ok(array_merge($_SESSION['user'], [
        'resume_session'  => $resume_session,
        'blocked_session' => $blocked_session,
    ]), 'Login successful');
}

// ── POST /api/auth.php?action=start_session ───────────────────────────────────
if ($action === 'start_session') {
    $user = require_permission('START_SESSION');
    $db   = get_db();

    $open = $db->prepare("
        SELECT cs.id, cs.user_id, CONCAT(u.first_name,' ',u.last_name) AS cashier_name
        FROM cashier_sessions cs JOIN users u ON u.id = cs.user_id
        WHERE cs.terminal = ? AND cs.ended_at IS NULL LIMIT 1
    ");
    $open->execute([$user['terminal']]);
    $openSession = $open->fetch();

    if ($openSession && (int)$openSession['user_id'] !== (int)$user['id']) {
        json_err("Terminal has an open session started by {$openSession['cashier_name']}. Ask them to log back in, or contact an admin.", 409, 'TERMINAL_SESSION_BLOCKED');
    }
    if ($openSession) {
        $_SESSION['cashier_session_id'] = $openSession['id'];
        json_ok(['session_id' => $openSession['id'], 'terminal' => $user['terminal']], 'Resumed existing session');
    }

    $stmt = $db->prepare("INSERT INTO cashier_sessions (user_id, terminal) VALUES (?, ?)");
    $stmt->execute([$user['id'], $user['terminal']]);
    $sid = (int)$db->lastInsertId();

    $_SESSION['cashier_session_id'] = $sid;
    audit_log('SESSION_STARTED', 'cashier_session', $sid, null, ['terminal' => $user['terminal']]);

    json_ok([
        'session_id' => $sid,
        'started_at' => date('Y-m-d H:i:s'),
        'terminal'   => $user['terminal'],
    ], 'Session started');
}

// ── POST /api/auth.php?action=end_session ─────────────────────────────────────
if ($action === 'end_session') {
    $user = require_auth();
    $sid  = $_SESSION['cashier_session_id'] ?? null;
    $sessionCode = null;

    if ($sid) {
        $db = get_db();

        $countStmt = $db->prepare("
            SELECT COUNT(*) FROM cashier_sessions
            WHERE user_id = ?
              AND DATE(started_at) = CURDATE()
              AND id <= ?
        ");
        $countStmt->execute([$user['id'], $sid]);
        $sessionNum  = (int)$countStmt->fetchColumn();
        $sessionCode = 'SS' . str_pad($sessionNum, 2, '0', STR_PAD_LEFT);

        $db->prepare("
            UPDATE cashier_sessions
            SET ended_at = NOW(), session_code = ?, closed_by = ?
            WHERE id = ? AND ended_at IS NULL
        ")->execute([$sessionCode, $user['id'], $sid]);

        audit_log('SESSION_ENDED', 'cashier_session', $sid, null, ['session_code' => $sessionCode]);
    }

    unset($_SESSION['cashier_session_id']);

    json_ok([
        'ended_session_id' => $sid,
        'session_code'     => $sessionCode,
    ], 'Session ended');
}

// ── POST /api/auth.php?action=logout ─────────────────────────────────────────
if ($action === 'logout') {
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) audit_log('LOGOUT', 'user', $uid);
    session_destroy();
    json_ok(null, 'Logged out successfully');
}

// ── GET /api/auth.php?action=me ──────────────────────────────────────────────
if ($action === 'me') {
    if (empty($_SESSION['user_id'])) json_err('Not authenticated', 401, 'NOT_AUTHENTICATED');
    json_ok([
        'user'       => $_SESSION['user'],
        'session_id' => $_SESSION['cashier_session_id'] ?? null,
    ]);
}

// ── POST /api/auth.php?action=change_password ────────────────────────────────
if ($action === 'change_password') {
    $user    = require_auth();
    $current = $body['current_password'] ?? '';
    $new_p   = $body['new_password'] ?? '';
    $confirm = $body['confirm_password'] ?? '';

    $minLen = (int)get_config('password_min_length', 8);

    if (!$current || !$new_p)   json_err('All fields required.', 400, 'MISSING_FIELDS');
    if ($new_p !== $confirm)    json_err('New passwords do not match.', 400, 'PASSWORD_MISMATCH');
    if (strlen($new_p) < $minLen) json_err("Password must be at least {$minLen} characters.", 400, 'PASSWORD_TOO_SHORT');
    if ($new_p === $current)    json_err('New password must be different from the current password.', 400, 'PASSWORD_UNCHANGED');
    // Basic strength: reject pure numeric short codes like the old defaults
    if (preg_match('/^\d{1,6}$/', $new_p)) {
        json_err('Password is too weak. Use a longer mix of characters.', 400, 'PASSWORD_WEAK');
    }

    $db   = get_db();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row  = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        json_err('Current password is incorrect.', 400, 'CURRENT_PASSWORD_INVALID');
    }

    $db->prepare("
        UPDATE users
        SET password_hash = ?, must_change_password = 0, password_changed_at = NOW()
        WHERE id = ?
    ")->execute([password_hash($new_p, PASSWORD_DEFAULT), $user['id']]);

    // Refresh session flag
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['must_change_password'] = 0;
    }

    audit_log('PASSWORD_CHANGED', 'user', $user['id']);

    json_ok(null, 'Password changed successfully');
}

// ── GET /api/auth.php?action=admin_open_sessions ─────────────────────────────
if ($action === 'admin_open_sessions') {
    require_permission('FORCE_END_SESSION');
    $db   = get_db();
    $stmt = $db->query("
        SELECT
            cs.id, cs.terminal, cs.started_at,
            cs.user_id, CONCAT(u.first_name,' ',u.last_name) AS cashier_name, u.username,
            (SELECT COUNT(*) FROM transactions t WHERE t.session_id=cs.id AND t.type='qr_sale')                 AS qr_qty,
            (SELECT COALESCE(SUM(amount),0) FROM transactions t WHERE t.session_id=cs.id AND t.type='qr_sale')  AS qr_total,
            (SELECT COUNT(*) FROM transactions t WHERE t.session_id=cs.id AND t.type='topup')                    AS topup_qty,
            (SELECT COALESCE(SUM(amount),0) FROM transactions t WHERE t.session_id=cs.id AND t.type='topup')    AS topup_total,
            (SELECT COUNT(*) FROM transactions t WHERE t.session_id=cs.id AND t.type='card_sale')                AS cards_issued
        FROM cashier_sessions cs
        JOIN users u ON u.id = cs.user_id
        WHERE cs.ended_at IS NULL
        ORDER BY cs.started_at ASC
    ");
    json_ok($stmt->fetchAll());
}

// ── POST /api/auth.php?action=admin_end_session ──────────────────────────────
if ($action === 'admin_end_session') {
    $admin = require_permission('FORCE_END_SESSION');
    $sid   = (int)($body['session_id'] ?? 0);
    if (!$sid) json_err('session_id required.', 400, 'MISSING_SESSION_ID');

    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM cashier_sessions WHERE id = ?");
    $stmt->execute([$sid]);
    $session = $stmt->fetch();

    if (!$session)               json_err('Session not found.', 404, 'SESSION_NOT_FOUND');
    if ($session['ended_at'])    json_err('Session is already closed.', 409, 'SESSION_ALREADY_CLOSED');

    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM cashier_sessions
        WHERE user_id = ? AND DATE(started_at) = DATE(?) AND id <= ?
    ");
    $countStmt->execute([$session['user_id'], $session['started_at'], $sid]);
    $sessionCode = 'SS' . str_pad((int)$countStmt->fetchColumn(), 2, '0', STR_PAD_LEFT);

    $db->prepare("
        UPDATE cashier_sessions SET ended_at = NOW(), session_code = ?, closed_by = ?
        WHERE id = ?
    ")->execute([$sessionCode, $admin['id'], $sid]);

    if (($_SESSION['cashier_session_id'] ?? null) == $sid) {
        unset($_SESSION['cashier_session_id']);
    }

    audit_log('SESSION_FORCE_CLOSED', 'cashier_session', $sid, null, [
        'session_code' => $sessionCode,
        'owner_user_id' => $session['user_id'],
    ], 'Admin force-close');

    json_ok(['session_id' => $sid, 'session_code' => $sessionCode], 'Session closed by admin');
}


// ── POST /api/auth.php?action=mfa_verify ─────────────────────────────────────
if ($action === 'mfa_verify') {
    $code = trim($body['code'] ?? '');
    $pending = (int)($_SESSION['mfa_pending_user_id'] ?? 0);
    if (!$pending) json_err('No MFA challenge pending.', 400, 'NO_MFA_PENDING');
    if (!$code) json_err('MFA code required.', 400, 'MISSING_MFA_CODE');

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND active = 1");
    $stmt->execute([$pending]);
    $user = $stmt->fetch();
    if (!$user || empty($user['mfa_secret'])) {
        unset($_SESSION['mfa_pending_user_id']);
        json_err('MFA not configured.', 400, 'MFA_NOT_CONFIGURED');
    }
    if (!totp_verify($user['mfa_secret'], $code)) {
        audit_log('LOGIN_MFA_FAILED', 'user', $user['id']);
        json_err('Invalid MFA code.', 401, 'MFA_INVALID');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'role' => $user['role'],
        'terminal' => $user['terminal'],
        'organization_id' => $user['organization_id'] ?? null,
        'must_change_password' => (int)($user['must_change_password'] ?? 0),
        'mfa_enabled' => 1,
    ];
    attach_permissions_to_session((int)$user['id']);
    unset($_SESSION['mfa_pending_user_id']);
    unset($_SESSION['cashier_session_id']);
    audit_log('LOGIN_SUCCESS', 'user', $user['id'], null, null, 'MFA verified');

    $resume_session = null;
    $blocked_session = null;
    if ($user['role'] !== 'admin') {
        $open = $db->prepare("
            SELECT cs.id, cs.user_id, cs.started_at, CONCAT(u.first_name,' ',u.last_name) AS cashier_name
            FROM cashier_sessions cs JOIN users u ON u.id = cs.user_id
            WHERE cs.terminal = ? AND cs.ended_at IS NULL LIMIT 1
        ");
        $open->execute([$user['terminal']]);
        $openSession = $open->fetch();
        if ($openSession) {
            if ((int)$openSession['user_id'] === (int)$user['id']) {
                $_SESSION['cashier_session_id'] = $openSession['id'];
                $resume_session = ['session_id' => $openSession['id'], 'started_at' => $openSession['started_at']];
            } else {
                $blocked_session = ['cashier_name' => $openSession['cashier_name'], 'started_at' => $openSession['started_at']];
            }
        }
    }

    json_ok(array_merge($_SESSION['user'], [
        'resume_session' => $resume_session,
        'blocked_session' => $blocked_session,
    ]), 'Login successful');
}

// ── POST /api/auth.php?action=mfa_enroll_begin ───────────────────────────────
if ($action === 'mfa_enroll_begin') {
    $user = require_auth();
    $secret = totp_generate_secret();
    $_SESSION['mfa_enroll_secret'] = $secret;
    $uri = totp_otpauth_uri($secret, $user['username'], 'AFCS');
    json_ok([
        'secret' => $secret,
        'otpauth_uri' => $uri,
        'message' => 'Scan with authenticator app, then confirm with mfa_enroll_confirm',
    ]);
}

// ── POST /api/auth.php?action=mfa_enroll_confirm ─────────────────────────────
if ($action === 'mfa_enroll_confirm') {
    $user = require_auth();
    $code = trim($body['code'] ?? '');
    $secret = $_SESSION['mfa_enroll_secret'] ?? '';
    if (!$secret) json_err('No enrollment in progress. Call mfa_enroll_begin first.', 400, 'NO_ENROLL_PENDING');
    if (!totp_verify($secret, $code)) json_err('Invalid code — enrollment not confirmed.', 400, 'MFA_INVALID');

    $db = get_db();
    $db->prepare("UPDATE users SET mfa_enabled = 1, mfa_secret = ?, mfa_enrolled_at = NOW() WHERE id = ?")
       ->execute([$secret, $user['id']]);
    unset($_SESSION['mfa_enroll_secret']);
    if (isset($_SESSION['user'])) $_SESSION['user']['mfa_enabled'] = 1;
    audit_log('MFA_ENROLLED', 'user', $user['id']);
    json_ok(null, 'MFA enabled successfully');
}

// ── POST /api/auth.php?action=mfa_disable ────────────────────────────────────
if ($action === 'mfa_disable') {
    $user = require_auth();
    $code = trim($body['code'] ?? '');
    $password = $body['password'] ?? '';
    $db = get_db();
    $stmt = $db->prepare("SELECT password_hash, mfa_secret, mfa_enabled FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        json_err('Password incorrect.', 400, 'CURRENT_PASSWORD_INVALID');
    }
    if (!empty($row['mfa_enabled']) && !empty($row['mfa_secret'])) {
        if (!totp_verify($row['mfa_secret'], $code)) {
            json_err('Invalid MFA code.', 401, 'MFA_INVALID');
        }
    }
    $db->prepare("UPDATE users SET mfa_enabled = 0, mfa_secret = NULL, mfa_enrolled_at = NULL WHERE id = ?")
       ->execute([$user['id']]);
    if (isset($_SESSION['user'])) $_SESSION['user']['mfa_enabled'] = 0;
    audit_log('MFA_DISABLED', 'user', $user['id']);
    json_ok(null, 'MFA disabled');
}


json_err('Unknown action', 404, 'UNKNOWN_ACTION');
