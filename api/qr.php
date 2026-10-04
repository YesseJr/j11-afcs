<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body   = json_body();

const TICKET_LABEL  = 'KIGAMBONI STUDENT TICKET';
const TICKET_FROM   = 'Kigamboni';
const TICKET_TO     = 'All Destinations';

function qr_sign(array $payload): string {
    $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash_hmac('sha256', $canonical, AFCS_HMAC_SECRET);
}

function qr_verify(array $payload, ?string $signature): bool {
    if (!$signature) return false;
    $expected = qr_sign($payload);
    return hash_equals($expected, $signature);
}

// ── POST /api/qr.php?action=sell ─────────────────────────────────────────────
if ($action === 'sell') {
    $user       = require_permission('ISSUE_QR');
    $session_id = get_active_session_id();

    $qty       = max(1, (int)($body['quantity']  ?? 1));
    $cash_in   = (float)($body['cash_received'] ?? 0);
    $category  = strtoupper($body['passenger_category'] ?? 'STUDENT');
    $routeCode = $body['route_code'] ?? null;

    if ($qty > 20) json_err('Maximum 20 tickets per transaction.', 400, 'QTY_TOO_HIGH');

    $fare = resolve_fare([
        'organization_id' => $user['organization_id'] ?? null,
        'media' => 'QR',
        'passenger_category' => $category,
        'route_code' => $routeCode,
    ]);
    if (!$fare) json_err('No fare product configured for this ticket.', 422, 'FARE_NOT_CONFIGURED');

    $price     = (float)$fare['amount'];
    $validityH = (int)($fare['validity_hours'] ?? get_config('ticket_validity_hours', 24));
    $currency  = (string)($fare['currency'] ?? get_config('currency', 'TZS'));
    $fareCode  = $fare['code'] ?? 'UNKNOWN';
    $fareName  = $fare['name'] ?? TICKET_LABEL;

    $idemKey = get_idempotency_key($body);

    with_idempotency('qr_sell', $body, function () use ($user, $session_id, $qty, $price, $cash_in, $validityH, $currency, $idemKey, $fareCode, $fareName, $category) {
        $route     = $fareName;
        $total     = $price * $qty;
        $expires   = date('Y-m-d H:i:s', strtotime("+{$validityH} hours"));
        $db        = get_db();
        $tickets   = [];

        $db->beginTransaction();
        try {
            $sessStmt = $db->prepare("SELECT cs.terminal, CONCAT(u.first_name,' ',u.last_name) AS cashier_name
                                       FROM cashier_sessions cs JOIN users u ON u.id=cs.user_id WHERE cs.id=?");
            $sessStmt->execute([$session_id]);
            $sessInfo = $sessStmt->fetch();

            for ($i = 0; $i < $qty; $i++) {
                $code = 'TKT-' . strtoupper(bin2hex(random_bytes(6))) . '-' . date('ymdHis');

                $payloadArr = [
                    'ticket_code' => $code,
                    'route'       => $route,
                    'from'        => TICKET_FROM,
                    'to'          => TICKET_TO,
                    'price'       => $price,
                    'currency'    => $currency,
                    'type'        => $category,
                    'fare_code'   => $fareCode,
                    'issued_at'   => date('Y-m-d H:i:s'),
                    'expires_at'  => $expires,
                    'terminal'    => $sessInfo['terminal'] ?? 'KIGAMBONI TERMINAL',
                    'cashier'     => $sessInfo['cashier_name'] ?? '',
                    'issuer'      => 'AFCS v4',
                    'v'           => 1,
                ];
                $signature = qr_sign($payloadArr);
                $payloadArr['sig'] = $signature;
                $qr_payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

                $db->prepare("
                    INSERT INTO qr_tickets (ticket_code, session_id, route, price, expires_at, qr_payload, signature)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([$code, $session_id, $route, $price, $expires, $qr_payload, $signature]);

                $tickets[] = [
                    'ticket_code' => $code,
                    'qr_payload'  => $qr_payload,
                    'route'       => $route,
                    'from'        => TICKET_FROM,
                    'to'          => TICKET_TO,
                    'price'       => $price,
                    'expires_at'  => $expires,
                    'ticket_id'   => (int)$db->lastInsertId(),
                ];
            }

            $db->prepare("
                INSERT INTO transactions (session_id, type, amount, quantity, route, notes, correlation_id, idempotency_key, status, currency)
                VALUES (?, 'qr_sale', ?, ?, ?, ?, ?, ?, 'COMPLETED', ?)
            ")->execute([
                $session_id, $total, $qty, $route,
                "QR sale: {$qty} student ticket(s) — {$route}",
                $GLOBALS['AFCS_REQUEST_ID'], $idemKey, $currency,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('qr sell failed: ' . $e->getMessage());
            return [500, ['success' => false, 'error_code' => 'QR_SALE_FAILED', 'message' => 'QR ticket sale failed.', 'data' => null]];
        }

        audit_log('QR_TICKETS_ISSUED', 'qr_ticket', null, null, [
            'quantity' => $qty, 'total' => $total, 'codes' => array_column($tickets, 'ticket_code'),
        ]);

        return [200, [
            'success' => true,
            'message' => "{$qty} QR ticket(s) issued successfully",
            'data' => [
                'tickets'       => $tickets,
                'total'         => $total,
                'cash_received' => $cash_in,
                'change'        => $cash_in > 0 ? max(0, $cash_in - $total) : null,
                'quantity'      => $qty,
                'route'         => $route,
            ],
        ]];
    });
}

// ── GET /api/qr.php?action=validate&code=TKT-xxx ─────────────────────────────
if ($action === 'validate') {
    $code = trim($_GET['code'] ?? '');
    if (!$code) json_err('Ticket code required.', 400, 'MISSING_TICKET_CODE');

    $db = get_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM qr_tickets WHERE ticket_code = ? FOR UPDATE");
        $stmt->execute([$code]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $db->rollBack();
            json_err('Ticket not found.', 404, 'TICKET_NOT_FOUND');
        }
        if ($ticket['status'] === 'used') {
            $db->rollBack();
            json_err('Ticket already used.', 409, 'TICKET_ALREADY_USED');
        }
        if ($ticket['status'] === 'cancelled') {
            $db->rollBack();
            json_err('Ticket cancelled.', 409, 'TICKET_CANCELLED');
        }
        if ($ticket['status'] === 'expired' || strtotime($ticket['expires_at']) < time()) {
            $db->prepare("UPDATE qr_tickets SET status='expired' WHERE id=?")->execute([$ticket['id']]);
            $db->commit();
            json_err('Ticket has expired.', 410, 'TICKET_EXPIRED');
        }

        // Verify signature when present (new tickets); accept legacy unsigned for migration period
        $payload = json_decode($ticket['qr_payload'] ?? '{}', true) ?: [];
        if (!empty($ticket['signature'])) {
            $check = $payload;
            unset($check['sig']);
            if (!qr_verify($check, $ticket['signature'])) {
                $db->rollBack();
                audit_log('QR_VALIDATE_FORGERY', 'qr_ticket', $ticket['id'], null, null, 'Signature mismatch');
                json_err('Ticket signature invalid — possible forgery.', 403, 'TICKET_SIGNATURE_INVALID');
            }
        }

        $db->prepare("UPDATE qr_tickets SET status='used', used_at=NOW() WHERE id=? AND status='valid'")
           ->execute([$ticket['id']]);
        if ($db->query("SELECT ROW_COUNT()")->fetchColumn() == 0) {
            // Concurrent validation won the race
            $db->rollBack();
            json_err('Ticket already used.', 409, 'TICKET_ALREADY_USED');
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('qr validate failed: ' . $e->getMessage());
        json_err('Validation failed.', 500, 'VALIDATE_FAILED');
    }

    audit_log('QR_TICKET_VALIDATED', 'qr_ticket', $ticket['id']);

    json_ok([
        'ticket_code' => $ticket['ticket_code'],
        'route'       => $ticket['route'],
        'from'        => $payload['from'] ?? '',
        'to'          => $payload['to']   ?? '',
        'issued_at'   => $ticket['issued_at'],
        'expires_at'  => $ticket['expires_at'],
        'used_at'     => date('Y-m-d H:i:s'),
    ], 'Ticket valid — gate open ✓');
}

// ── GET /api/qr.php?action=list ──────────────────────────────────────────────
if ($action === 'list') {
    require_auth();
    $sid  = (int)($_GET['session_id'] ?? get_active_session_id());
    $db   = get_db();
    $stmt = $db->prepare("SELECT id, ticket_code, route, price, status, issued_at, expires_at, used_at FROM qr_tickets WHERE session_id=? ORDER BY issued_at DESC");
    $stmt->execute([$sid]);
    json_ok($stmt->fetchAll());
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
