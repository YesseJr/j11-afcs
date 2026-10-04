<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$body   = json_body();
$action = $_GET['action'] ?? $body['action'] ?? '';

// ── GET /api/cards.php?action=lookup&card_number=xxx ─────────────────────────
if ($action === 'lookup') {
    require_permission('VIEW_CARD');
    $card_number = trim($_GET['card_number'] ?? '');
    if (!$card_number) json_err('Card number required.', 400, 'MISSING_CARD_NUMBER');

    $db   = get_db();
    $stmt = $db->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) AS registered_by_name
        FROM cards c
        LEFT JOIN users u ON u.id = c.registered_by
        WHERE c.card_number = ?
    ");
    $stmt->execute([$card_number]);
    $card = $stmt->fetch();

    if (!$card) {
        json_ok([
            'id'           => null,
            'card_number'  => $card_number,
            'holder_name'  => null,
            'phone'        => null,
            'gender'       => null,
            'dob'          => null,
            'card_type'    => null,
            'balance'      => 0,
            'status'       => 'new',
            'penalty_flag' => 0,
            'registered_by_name' => null,
            'registered_at' => null,
            'recent_transactions' => [],
        ]);
    }

    $txns = $db->prepare("
        SELECT t.*, cs.terminal FROM transactions t
        JOIN cashier_sessions cs ON cs.id = t.session_id
        WHERE t.card_id = ?
        ORDER BY t.created_at DESC LIMIT 10
    ");
    $txns->execute([$card['id']]);
    $card['recent_transactions'] = $txns->fetchAll();

    json_ok($card);
}

// ── GET /api/cards.php?action=next_blank ─────────────────────────────────────
if ($action === 'next_blank') {
    require_auth();
    $db   = get_db();
    $stmt = $db->query("SELECT card_number FROM cards WHERE status = 'new' ORDER BY id ASC LIMIT 1");
    $row  = $stmt->fetch();

    if (!$row) json_err('No unregistered cards left in stock. Ask an admin to add more to inventory.', 409, 'NO_BLANK_STOCK');

    json_ok(['card_number' => $row['card_number']]);
}

// ── GET /api/cards.php?action=blank_stock ────────────────────────────────────
if ($action === 'blank_stock') {
    require_auth();
    $db  = get_db();
    $cnt = $db->query("SELECT COUNT(*) AS n FROM cards WHERE status = 'new'")->fetch();
    json_ok(['available' => (int)$cnt['n']]);
}

// ── POST /api/cards.php?action=admin_add_blank_cards ─────────────────────────
if ($action === 'admin_add_blank_cards') {
    require_permission('MANAGE_CARD_INVENTORY');

    $start_number = trim($body['start_number'] ?? '');
    $quantity     = (int)($body['quantity'] ?? 0);

    if (!ctype_digit($start_number))   json_err('Start number must be numeric (e.g. 994160000026).', 400, 'INVALID_START_NUMBER');
    if ($quantity < 1 || $quantity > 500) json_err('Quantity must be between 1 and 500.', 400, 'INVALID_QUANTITY');

    $db     = get_db();
    $width  = strlen($start_number);
    $start  = (int)$start_number;
    $stmt   = $db->prepare("INSERT IGNORE INTO cards (card_number, status) VALUES (?, 'new')");

    $added = 0; $skipped = 0;
    for ($i = 0; $i < $quantity; $i++) {
        $no = str_pad((string)($start + $i), $width, '0', STR_PAD_LEFT);
        $stmt->execute([$no]);
        if ($stmt->rowCount() > 0) $added++; else $skipped++;
    }

    audit_log('CARD_INVENTORY_ADDED', 'cards', null, null, ['added' => $added, 'skipped' => $skipped, 'start' => $start_number]);

    json_ok(['added' => $added, 'skipped' => $skipped], "$added blank card(s) added to inventory" . ($skipped ? " ($skipped already existed and were skipped)" : ''));
}

// ── GET /api/cards.php?action=admin_inventory ─────────────────────────────────
if ($action === 'admin_inventory') {
    require_permission('MANAGE_CARD_INVENTORY');
    $db = get_db();

    $byStatus = $db->query("SELECT status, COUNT(*) AS n FROM cards GROUP BY status")->fetchAll();
    $byType   = $db->query("SELECT card_type, COUNT(*) AS n FROM cards WHERE status != 'new' GROUP BY card_type")->fetchAll();
    $blanks   = $db->query("SELECT card_number, created_at FROM cards WHERE status = 'new' ORDER BY id ASC")->fetchAll();

    json_ok([
        'by_status' => $byStatus,
        'by_type'   => $byType,
        'blank_cards' => $blanks,
    ]);
}

// ── POST /api/cards.php?action=register ──────────────────────────────────────
if ($action === 'register') {
    $user       = require_permission('CREATE_CARD');
    $session_id = get_active_session_id();

    $card_number = trim($body['card_number'] ?? '');
    $holder_name = strtoupper(trim($body['holder_name'] ?? ''));
    $phone       = trim($body['phone'] ?? '');
    $card_type   = $body['card_type'] ?? 'Adult';
    $gender      = $body['gender'] ?? 'M';
    $dob         = $body['dob'] ?? null;

    if (!$card_number) json_err('Card number required.', 400, 'MISSING_CARD_NUMBER');
    if (!$holder_name) json_err('Holder name required.', 400, 'MISSING_HOLDER_NAME');
    if (!$phone)       json_err('Phone number required.', 400, 'MISSING_PHONE');
    if (!in_array($card_type, ['Adult', 'Staff'])) json_err('Invalid card type — only Adult and Staff cards are issued.', 400, 'INVALID_CARD_TYPE');

    $db = get_db();
    $db->beginTransaction();
    try {
        $exists = $db->prepare("SELECT id, status FROM cards WHERE card_number = ? FOR UPDATE");
        $exists->execute([$card_number]);
        $existing = $exists->fetch();
        if ($existing && $existing['status'] !== 'new') {
            $db->rollBack();
            json_err('Card is already registered.', 409, 'CARD_ALREADY_REGISTERED');
        }

        if ($existing) {
            $db->prepare("
                UPDATE cards SET
                    holder_name   = ?, phone = ?, card_type = ?, gender = ?, dob = ?,
                    status        = 'active',
                    registered_by = ?, registered_at = CURRENT_TIMESTAMP,
                    activated_at  = CURRENT_TIMESTAMP
                WHERE card_number = ?
            ")->execute([$holder_name, $phone, $card_type, $gender, $dob, $user['id'], $card_number]);
            $card_id = (int)$existing['id'];
        } else {
            $db->prepare("
                INSERT INTO cards (card_number, holder_name, phone, card_type, gender, dob,
                                   status, registered_by, registered_at, activated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ")->execute([$card_number, $holder_name, $phone, $card_type, $gender, $dob, $user['id']]);
            $card_id = (int)$db->lastInsertId();
        }

        $db->prepare("
            INSERT INTO transactions (session_id, type, card_id, amount, notes, correlation_id, status, currency)
            VALUES (?, 'card_sale', ?, 0, ?, ?, 'COMPLETED', 'TZS')
        ")->execute([$session_id, $card_id, "Card registered: $card_type", $GLOBALS['AFCS_REQUEST_ID']]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('card register failed: ' . $e->getMessage());
        json_err('Card registration failed.', 500, 'REGISTER_FAILED');
    }

    audit_log('CARD_REGISTERED', 'card', $card_id, null, [
        'card_number' => $card_number,
        'card_type'   => $card_type,
        'holder_name' => $holder_name,
    ]);

    json_ok(['card_id' => $card_id, 'card_number' => $card_number, 'holder_name' => $holder_name], 'Card registered and activated successfully');
}

// ── POST /api/cards.php?action=topup ─────────────────────────────────────────
if ($action === 'topup') {
    $user       = require_permission('TOPUP_CARD');
    $session_id = get_active_session_id();

    $card_number = trim($body['card_number'] ?? '');
    $amount      = (float)($body['amount'] ?? 0);
    $maxTopup    = (float)get_config('max_topup', 500000);

    if (!$card_number)  json_err('Card number required.', 400, 'MISSING_CARD_NUMBER');
    if ($amount <= 0)   json_err('Amount must be greater than 0.', 400, 'INVALID_AMOUNT');
    if ($amount > $maxTopup) json_err("Amount exceeds maximum topup limit ($maxTopup).", 400, 'AMOUNT_EXCEEDS_LIMIT');

    $idemKey = get_idempotency_key($body);
    $scope   = 'topup';

    with_idempotency($scope, $body, function () use ($user, $session_id, $card_number, $amount, $idemKey) {
        $db = get_db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM cards WHERE card_number = ? FOR UPDATE");
            $stmt->execute([$card_number]);
            $card = $stmt->fetch();

            if (!$card) {
                $db->rollBack();
                return [404, ['success' => false, 'error_code' => 'CARD_NOT_FOUND', 'message' => 'Card not found.', 'data' => null]];
            }
            if ($card['status'] !== 'active') {
                $db->rollBack();
                return [400, ['success' => false, 'error_code' => 'CARD_NOT_ACTIVE', 'message' => "Card is {$card['status']} — topup not allowed.", 'data' => null]];
            }
            if ($card['card_type'] === 'Staff') {
                $db->rollBack();
                return [400, ['success' => false, 'error_code' => 'STAFF_NO_TOPUP', 'message' => 'Staff cards cannot be topped up — staff have unlimited service access and do not carry a balance.', 'data' => null]];
            }

            $old_balance = (float)$card['balance'];
            $new_balance = $old_balance + $amount;

            $db->prepare("UPDATE cards SET balance = ? WHERE id = ?")->execute([$new_balance, $card['id']]);

            $db->prepare("
                INSERT INTO transactions (session_id, type, card_id, amount, notes, correlation_id, idempotency_key, status, currency)
                VALUES (?, 'topup', ?, ?, ?, ?, ?, 'COMPLETED', 'TZS')
            ")->execute([
                $session_id, $card['id'], $amount,
                "Topup by {$user['name']}",
                $GLOBALS['AFCS_REQUEST_ID'],
                $idemKey,
            ]);
            $txnId = (int)$db->lastInsertId();

            ledger_append($db, (int)$card['id'], 'CREDIT', $amount, 'TOPUP', $new_balance, [
                'transaction_id'  => $txnId,
                'session_id'      => $session_id,
                'user_id'         => $user['id'],
                'idempotency_key' => $idemKey,
                'reference_type'  => 'transaction',
                'reference_id'    => (string)$txnId,
                'notes'           => "Topup by {$user['name']}",
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('topup failed: ' . $e->getMessage());
            return [500, ['success' => false, 'error_code' => 'TOPUP_FAILED', 'message' => 'Topup failed.', 'data' => null]];
        }

        audit_log('CARD_TOPUP', 'card', $card['id'], ['balance' => $old_balance], [
            'balance' => $new_balance,
            'amount'  => $amount,
        ]);

        return [200, [
            'success' => true,
            'message' => 'Topup successful',
            'data' => [
                'card_number'  => $card_number,
                'holder_name'  => $card['holder_name'],
                'amount_added' => $amount,
                'new_balance'  => $new_balance,
                'old_balance'  => $old_balance,
            ],
        ]];
    });
}

// ── POST /api/cards.php?action=activate ──────────────────────────────────────
if ($action === 'activate') {
    $user       = require_permission('ACTIVATE_CARD');
    $session_id = get_active_session_id();

    $card_number = trim($body['card_number'] ?? '');
    $reason      = trim($body['reason'] ?? 'Manual activation');

    if (!$card_number) json_err('Card number required.', 400, 'MISSING_CARD_NUMBER');

    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM cards WHERE card_number = ?");
    $stmt->execute([$card_number]);
    $card = $stmt->fetch();

    if (!$card)                        json_err('Card not found.', 404, 'CARD_NOT_FOUND');
    if ($card['status'] === 'active')  json_err('Card is already active.', 409, 'CARD_ALREADY_ACTIVE');

    $db->prepare("UPDATE cards SET status = 'active', activated_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$card['id']]);

    $db->prepare("
        INSERT INTO transactions (session_id, type, card_id, notes, correlation_id, status)
        VALUES (?, 'activate', ?, ?, ?, 'COMPLETED')
    ")->execute([$session_id, $card['id'], $reason, $GLOBALS['AFCS_REQUEST_ID']]);

    audit_log('CARD_ACTIVATED', 'card', $card['id'], ['status' => $card['status']], ['status' => 'active'], $reason);

    json_ok(['card_number' => $card_number, 'holder_name' => $card['holder_name']], 'Card activated successfully');
}

// ── POST /api/cards.php?action=deactivate ────────────────────────────────────
if ($action === 'deactivate') {
    $user       = require_permission('DEACTIVATE_CARD');
    $session_id = get_active_session_id();

    $card_number = trim($body['card_number'] ?? '');
    $reason      = trim($body['reason'] ?? 'Manual deactivation');

    if (!$card_number) json_err('Card number required.', 400, 'MISSING_CARD_NUMBER');

    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM cards WHERE card_number = ?");
    $stmt->execute([$card_number]);
    $card = $stmt->fetch();

    if (!$card)                          json_err('Card not found.', 404, 'CARD_NOT_FOUND');
    if ($card['status'] === 'inactive')  json_err('Card is already inactive.', 409, 'CARD_ALREADY_INACTIVE');

    $db->prepare("UPDATE cards SET status = 'inactive', deactivated_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$card['id']]);

    $db->prepare("
        INSERT INTO transactions (session_id, type, card_id, notes, correlation_id, status)
        VALUES (?, 'deactivate', ?, ?, ?, 'COMPLETED')
    ")->execute([$session_id, $card['id'], $reason, $GLOBALS['AFCS_REQUEST_ID']]);

    audit_log('CARD_DEACTIVATED', 'card', $card['id'], ['status' => $card['status']], ['status' => 'inactive'], $reason);

    json_ok(['card_number' => $card_number, 'holder_name' => $card['holder_name']], 'Card deactivated');
}

// ── POST /api/cards.php?action=balance_transfer ──────────────────────────────
if ($action === 'balance_transfer') {
    $user       = require_permission('TRANSFER_BALANCE');
    $session_id = get_active_session_id();

    $from_number = trim($body['from_card'] ?? '');
    $to_number   = trim($body['to_card'] ?? '');
    $amount      = (float)($body['amount'] ?? 0);

    if (!$from_number || !$to_number) json_err('Both card numbers required.', 400, 'MISSING_CARD_NUMBER');
    if ($from_number === $to_number)  json_err('Source and destination cards cannot be the same.', 400, 'SAME_CARD');
    if ($amount <= 0)                 json_err('Amount must be greater than 0.', 400, 'INVALID_AMOUNT');

    $idemKey = get_idempotency_key($body);

    with_idempotency('balance_transfer', $body, function () use ($user, $session_id, $from_number, $to_number, $amount, $idemKey) {
        $db = get_db();
        $db->beginTransaction();
        try {
            // Lock both cards in consistent order to avoid deadlocks
            $nums = [$from_number, $to_number];
            sort($nums);
            $stmt = $db->prepare("SELECT * FROM cards WHERE card_number = ? FOR UPDATE");

            $stmt->execute([$nums[0]]); $c1 = $stmt->fetch();
            $stmt->execute([$nums[1]]); $c2 = $stmt->fetch();

            $fromCard = ($c1 && $c1['card_number'] === $from_number) ? $c1 : $c2;
            $toCard   = ($c1 && $c1['card_number'] === $to_number)   ? $c1 : $c2;

            if (!$fromCard || $fromCard['card_number'] !== $from_number) {
                $db->rollBack();
                return [404, ['success' => false, 'error_code' => 'SOURCE_NOT_FOUND', 'message' => 'Source card not found.', 'data' => null]];
            }
            if (!$toCard || $toCard['card_number'] !== $to_number) {
                $db->rollBack();
                return [404, ['success' => false, 'error_code' => 'DEST_NOT_FOUND', 'message' => 'Destination card not found.', 'data' => null]];
            }
            if ($fromCard['status'] !== 'active') {
                $db->rollBack();
                return [400, ['success' => false, 'error_code' => 'SOURCE_NOT_ACTIVE', 'message' => 'Source card is not active.', 'data' => null]];
            }
            if ($toCard['status'] !== 'active') {
                $db->rollBack();
                return [400, ['success' => false, 'error_code' => 'DEST_NOT_ACTIVE', 'message' => 'Destination card is not active.', 'data' => null]];
            }
            if ((float)$fromCard['balance'] < $amount) {
                $db->rollBack();
                return [400, ['success' => false, 'error_code' => 'INSUFFICIENT_BALANCE', 'message' => 'Insufficient balance on source card.', 'data' => null]];
            }

            $fromNew = (float)$fromCard['balance'] - $amount;
            $toNew   = (float)$toCard['balance'] + $amount;

            $db->prepare("UPDATE cards SET balance = ? WHERE id = ?")->execute([$fromNew, $fromCard['id']]);
            $db->prepare("UPDATE cards SET balance = ? WHERE id = ?")->execute([$toNew, $toCard['id']]);

            $db->prepare("INSERT INTO transactions (session_id, type, card_id, amount, notes, correlation_id, idempotency_key, status, currency) VALUES (?, 'balance_transfer', ?, ?, ?, ?, ?, 'COMPLETED', 'TZS')")
               ->execute([$session_id, $fromCard['id'], -$amount, "Transfer to {$to_number}", $GLOBALS['AFCS_REQUEST_ID'], $idemKey]);
            $txnFrom = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO transactions (session_id, type, card_id, amount, notes, correlation_id, idempotency_key, status, currency) VALUES (?, 'balance_transfer', ?, ?, ?, ?, ?, 'COMPLETED', 'TZS')")
               ->execute([$session_id, $toCard['id'], $amount, "Transfer from {$from_number}", $GLOBALS['AFCS_REQUEST_ID'], $idemKey]);
            $txnTo = (int)$db->lastInsertId();

            ledger_append($db, (int)$fromCard['id'], 'DEBIT', $amount, 'TRANSFER_OUT', $fromNew, [
                'transaction_id' => $txnFrom, 'session_id' => $session_id, 'user_id' => $user['id'],
                'idempotency_key' => $idemKey, 'reference_type' => 'transfer', 'reference_id' => $from_number . '->' . $to_number,
                'notes' => "Transfer to {$to_number}",
            ]);
            ledger_append($db, (int)$toCard['id'], 'CREDIT', $amount, 'TRANSFER_IN', $toNew, [
                'transaction_id' => $txnTo, 'session_id' => $session_id, 'user_id' => $user['id'],
                'idempotency_key' => $idemKey, 'reference_type' => 'transfer', 'reference_id' => $from_number . '->' . $to_number,
                'notes' => "Transfer from {$from_number}",
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('balance_transfer failed: ' . $e->getMessage());
            return [500, ['success' => false, 'error_code' => 'TRANSFER_FAILED', 'message' => 'Balance transfer failed.', 'data' => null]];
        }

        audit_log('BALANCE_TRANSFER', 'card', $fromCard['id'], null, [
            'from' => $from_number, 'to' => $to_number, 'amount' => $amount,
        ]);

        return [200, [
            'success' => true,
            'message' => 'Balance transferred successfully',
            'data' => [
                'from_card'        => $from_number,
                'to_card'          => $to_number,
                'amount'           => $amount,
                'from_new_balance' => $fromNew,
                'to_new_balance'   => $toNew,
            ],
        ]];
    });
}

// ── POST /api/cards.php?action=penalty ───────────────────────────────────────
if ($action === 'penalty') {
    $user       = require_permission('DEACTIVATE_CARD');
    $session_id = get_active_session_id();

    $card_number = trim($body['card_number'] ?? '');
    $flag        = (int)($body['flag'] ?? 1);

    if (!$card_number) json_err('Card number required.', 400, 'MISSING_CARD_NUMBER');

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM cards WHERE card_number = ?");
    $stmt->execute([$card_number]);
    $card = $stmt->fetch();

    if (!$card) json_err('Card not found.', 404, 'CARD_NOT_FOUND');

    $db->prepare("UPDATE cards SET penalty_flag = ? WHERE id = ?")->execute([$flag, $card['id']]);

    $db->prepare("INSERT INTO transactions (session_id, type, card_id, notes, correlation_id, status) VALUES (?, 'penalty', ?, ?, ?, 'COMPLETED')")
       ->execute([$session_id, $card['id'], $flag ? 'Penalty flag set' : 'Penalty flag removed', $GLOBALS['AFCS_REQUEST_ID']]);

    audit_log($flag ? 'CARD_PENALTY_SET' : 'CARD_PENALTY_CLEARED', 'card', $card['id']);

    json_ok(null, $flag ? 'Penalty flag set on card' : 'Penalty flag removed');
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
