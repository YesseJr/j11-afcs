<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

function build_session_report($db, $session_id) {
    $sess = $db->prepare("
        SELECT cs.*, cs.session_code,
               CONCAT(u.first_name,' ',u.last_name) AS cashier_name,
               u.username
        FROM cashier_sessions cs
        JOIN users u ON u.id = cs.user_id
        WHERE cs.id = ?
    ");
    $sess->execute([$session_id]);
    $session = $sess->fetch();
    if (!$session) return null;

    $qr = $db->prepare("
        SELECT COUNT(*) AS count, COALESCE(SUM(amount),0) AS total
        FROM transactions WHERE session_id=? AND type='qr_sale'
    ");
    $qr->execute([$session_id]); $qr_stats = $qr->fetch();

    $tp = $db->prepare("
        SELECT COUNT(*) AS count, COALESCE(SUM(amount),0) AS total
        FROM transactions WHERE session_id=? AND type='topup'
    ");
    $tp->execute([$session_id]); $topup_stats = $tp->fetch();

    $cr = $db->prepare("
        SELECT COUNT(*) AS count FROM transactions WHERE session_id=? AND type='card_sale'
    ");
    $cr->execute([$session_id]); $card_stats = $cr->fetch();

    $total_txns  = (int)$qr_stats['count'] + (int)$topup_stats['count'];
    $grand_total = (float)$qr_stats['total'] + (float)$topup_stats['total'];

    // Recent transactions for the detailed list
    $txns = $db->prepare("
        SELECT t.type, t.amount, t.quantity, t.route, t.notes, t.created_at,
               c.card_number, c.holder_name
        FROM transactions t
        LEFT JOIN cards c ON c.id = t.card_id
        WHERE t.session_id = ?
        ORDER BY t.created_at ASC
    ");
    $txns->execute([$session_id]);

    return [
        'session'        => $session,
        'qr_sales'       => $qr_stats,
        'topups'         => $topup_stats,
        'card_sales'     => $card_stats,
        'total_txns'     => $total_txns,
        'grand_total'    => $grand_total,
        'printed_at'     => date('Y-m-d H:i:s'),
        'transactions'   => $txns->fetchAll(),
    ];
}

$action = $_GET['action'] ?? '';
$body   = json_body();

// ── GET ?action=session ───────────────────────────────────────────────────────
if ($action === 'session') {
    $user       = require_auth();
    $session_id = get_active_session_id();
    $db         = get_db();
    $data       = build_session_report($db, $session_id);
    if (!$data) json_err('Session not found.', 404, 'SESSION_NOT_FOUND');
    json_ok($data);
}

// ── GET ?action=session_by_id&session_id=X ───────────────────────────────────
if ($action === 'session_by_id') {
    require_auth();
    $session_id = (int)($_GET['session_id'] ?? 0);
    if (!$session_id) json_err('session_id required.', 400, 'MISSING_SESSION_ID');
    $db   = get_db();
    $data = build_session_report($db, $session_id);
    if (!$data) json_err('Session not found.', 404, 'SESSION_NOT_FOUND');
    json_ok($data);
}

// ── GET ?action=all_sessions ──────────────────────────────────────────────────
if ($action === 'all_sessions') {
    $user = require_auth();
    $db   = get_db();
    $stmt = $db->prepare("
        SELECT
            cs.id, cs.terminal, cs.session_code, cs.started_at, cs.ended_at,
            CONCAT(u.first_name,' ',u.last_name) AS cashier_name,
            (SELECT COUNT(*) FROM transactions t WHERE t.session_id=cs.id AND t.type='qr_sale')                AS qr_qty,
            (SELECT COALESCE(SUM(amount),0) FROM transactions t WHERE t.session_id=cs.id AND t.type='qr_sale') AS qr_total,
            (SELECT COUNT(*) FROM transactions t WHERE t.session_id=cs.id AND t.type='topup')                  AS topup_qty,
            (SELECT COALESCE(SUM(amount),0) FROM transactions t WHERE t.session_id=cs.id AND t.type='topup')   AS topup_total,
            (SELECT COUNT(*) FROM transactions t WHERE t.session_id=cs.id AND t.type='card_sale')              AS cards_issued
        FROM cashier_sessions cs
        JOIN users u ON u.id = cs.user_id
        WHERE cs.user_id = ?
        ORDER BY cs.started_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    json_ok($stmt->fetchAll());
}

// ── GET ?action=login_log ─────────────────────────────────────────────────────
if ($action === 'login_log') {
    require_auth();
    $db   = get_db();
    $stmt = $db->query("
        SELECT cs.id, cs.session_code, cs.started_at AS login_time,
               cs.ended_at AS logout_time, cs.terminal,
               u.first_name, u.last_name, u.username
        FROM cashier_sessions cs
        JOIN users u ON u.id = cs.user_id
        ORDER BY cs.started_at DESC
        LIMIT 100
    ");
    json_ok($stmt->fetchAll());
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
