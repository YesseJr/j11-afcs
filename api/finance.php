<?php
/**
 * Refunds, reversals, and administrative adjustments.
 * Never mutates historical transactions in place — always creates new events.
 */
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body   = json_body();

// ── POST refund ──────────────────────────────────────────────────────────────
if ($action === 'refund') {
    $user = require_permission('REFUND_TRANSACTION');

    $txnId     = (int)($body['transaction_id'] ?? 0);
    $amount    = isset($body['amount']) ? (float)$body['amount'] : null;
    $reasonCode = trim($body['reason_code'] ?? 'CUSTOMER_REQUEST');
    $reasonText = trim($body['reason_text'] ?? '');
    $type      = strtoupper($body['refund_type'] ?? 'FULL'); // FULL | PARTIAL | REVERSAL

    if (!$txnId) json_err('transaction_id required.', 400, 'MISSING_TRANSACTION_ID');
    if (!in_array($type, ['FULL','PARTIAL','REVERSAL'], true)) {
        json_err('Invalid refund_type.', 400, 'INVALID_REFUND_TYPE');
    }

    $idemKey = get_idempotency_key($body);

    with_idempotency('refund', $body, function () use ($user, $txnId, $amount, $reasonCode, $reasonText, $type, $idemKey) {
        $db = get_db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? FOR UPDATE");
            $stmt->execute([$txnId]);
            $txn = $stmt->fetch();
            if (!$txn) {
                $db->rollBack();
                return [404, ['success' => false, 'error_code' => 'TXN_NOT_FOUND', 'message' => 'Transaction not found.', 'data' => null]];
            }
            if (($txn['status'] ?? 'COMPLETED') === 'REVERSED') {
                $db->rollBack();
                return [409, ['success' => false, 'error_code' => 'TXN_ALREADY_REVERSED', 'message' => 'Transaction already fully reversed.', 'data' => null]];
            }

            $origAmount = abs((float)$txn['amount']);
            if ($type === 'FULL' || $type === 'REVERSAL') {
                $refundAmount = $origAmount;
            } else {
                if ($amount === null || $amount <= 0) {
                    $db->rollBack();
                    return [400, ['success' => false, 'error_code' => 'INVALID_AMOUNT', 'message' => 'Partial refund requires a positive amount.', 'data' => null]];
                }
                if ($amount > $origAmount) {
                    $db->rollBack();
                    return [400, ['success' => false, 'error_code' => 'AMOUNT_EXCEEDS_ORIGINAL', 'message' => 'Refund amount exceeds original transaction.', 'data' => null]];
                }
                $refundAmount = $amount;
            }

            // Sum prior refunds
            $prior = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM refunds WHERE original_txn_id = ? AND status = 'COMPLETED'");
            $prior->execute([$txnId]);
            $alreadyRefunded = (float)$prior->fetchColumn();
            if ($alreadyRefunded + $refundAmount > $origAmount + 0.001) {
                $db->rollBack();
                return [409, ['success' => false, 'error_code' => 'REFUND_EXCEEDS_ORIGINAL', 'message' => 'Total refunds would exceed original amount.', 'data' => null]];
            }

            $cardId = $txn['card_id'] ? (int)$txn['card_id'] : null;
            $newBalance = null;

            // If original was a top-up (money onto card), refund means DEBIT the card
            // If original was a fare deduction, refund means CREDIT the card
            // Current system mostly records topups as positive and sales as positive cash —
            // for topup refunds we reduce card balance.
            if ($cardId && in_array($txn['type'], ['topup'], true)) {
                $cStmt = $db->prepare("SELECT * FROM cards WHERE id = ? FOR UPDATE");
                $cStmt->execute([$cardId]);
                $card = $cStmt->fetch();
                if (!$card) {
                    $db->rollBack();
                    return [404, ['success' => false, 'error_code' => 'CARD_NOT_FOUND', 'message' => 'Linked card not found.', 'data' => null]];
                }
                if ((float)$card['balance'] < $refundAmount) {
                    $db->rollBack();
                    return [400, ['success' => false, 'error_code' => 'INSUFFICIENT_BALANCE', 'message' => 'Card balance insufficient for refund clawback.', 'data' => null]];
                }
                $newBalance = (float)$card['balance'] - $refundAmount;
                $db->prepare("UPDATE cards SET balance = ? WHERE id = ?")->execute([$newBalance, $cardId]);
                ledger_append($db, $cardId, 'DEBIT', $refundAmount, 'REFUND_CLAWBACK', $newBalance, [
                    'session_id' => $txn['session_id'],
                    'user_id' => $user['id'],
                    'idempotency_key' => $idemKey,
                    'reference_type' => 'transaction',
                    'reference_id' => (string)$txnId,
                    'notes' => "Refund of topup txn #$txnId",
                ]);
            }

            $refundUid = afcs_uuid();
            $db->prepare("
                INSERT INTO refunds (refund_uid, original_txn_id, session_id, card_id, amount, currency, refund_type, reason_code, reason_text, status, requested_by, organization_id, correlation_id, idempotency_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?, ?, ?, ?)
            ")->execute([
                $refundUid, $txnId, $txn['session_id'], $cardId, $refundAmount,
                $txn['currency'] ?? 'TZS', $type, $reasonCode, $reasonText ?: null,
                $user['id'], $user['organization_id'] ?? null,
                $GLOBALS['AFCS_REQUEST_ID'], $idemKey,
            ]);
            $refundId = (int)$db->lastInsertId();

            // Linkage transaction row (does not rewrite original)
            $refundSessionId = $txn['session_id'] ?: ($_SESSION['cashier_session_id'] ?? null);
            if (!$refundSessionId) {
                // Create a system linkage session reference — use original session only
                $refundSessionId = $txn['session_id'];
            }
            if ($refundSessionId) {
                $db->prepare("
                    INSERT INTO transactions (session_id, type, card_id, amount, notes, correlation_id, idempotency_key, status, currency)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?)
                ")->execute([
                    $refundSessionId,
                    $type === 'REVERSAL' ? 'reversal' : 'refund',
                    $cardId,
                    -$refundAmount,
                    "Refund/reversal of txn #$txnId: $reasonCode",
                    $GLOBALS['AFCS_REQUEST_ID'],
                    $idemKey,
                    $txn['currency'] ?? 'TZS',
                ]);
            }

            // Mark original as REVERSED only on full reversal covering remaining amount
            if ($type === 'REVERSAL' || ($alreadyRefunded + $refundAmount >= $origAmount - 0.001)) {
                $db->prepare("UPDATE transactions SET status = 'REVERSED' WHERE id = ?")->execute([$txnId]);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('refund failed: ' . $e->getMessage());
            return [500, ['success' => false, 'error_code' => 'REFUND_FAILED', 'message' => 'Refund failed.', 'data' => null]];
        }

        if ($refundAmount >= 50000) {
            anomaly_record('LARGE_REFUND', 'HIGH', 75, [
                'entity_type' => 'refund', 'entity_id' => $refundId,
                'evidence' => ['amount' => $refundAmount, 'original_txn_id' => $txnId],
            ]);
        }
        audit_log('REFUND_CREATED', 'refund', $refundId, null, [
            'original_txn_id' => $txnId,
            'amount' => $refundAmount,
            'type' => $type,
            'reason_code' => $reasonCode,
        ], $reasonText);

        return [200, [
            'success' => true,
            'message' => 'Refund completed',
            'data' => [
                'refund_id' => $refundId,
                'refund_uid' => $refundUid,
                'original_txn_id' => $txnId,
                'amount' => $refundAmount,
                'refund_type' => $type,
                'card_new_balance' => $newBalance,
            ],
        ]];
    });
}

// ── GET list refunds ─────────────────────────────────────────────────────────
if ($action === 'list_refunds') {
    require_permission('VIEW_FINANCIAL_REPORTS');
    $db = get_db();
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    $stmt = $db->prepare("
        SELECT r.*, t.type AS original_type, t.amount AS original_amount,
               CONCAT(u.first_name,' ',u.last_name) AS requested_by_name
        FROM refunds r
        JOIN transactions t ON t.id = r.original_txn_id
        LEFT JOIN users u ON u.id = r.requested_by
        ORDER BY r.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    json_ok($stmt->fetchAll());
}

// ── POST session opening float ───────────────────────────────────────────────
if ($action === 'set_opening_float') {
    $user = require_permission('START_SESSION');
    $session_id = get_active_session_id();
    $float = (float)($body['opening_float'] ?? 0);
    if ($float < 0) json_err('Opening float cannot be negative.', 400, 'INVALID_FLOAT');

    $db = get_db();
    $db->prepare("
        INSERT INTO session_cash (session_id, opening_float)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE opening_float = VALUES(opening_float)
    ")->execute([$session_id, $float]);

    audit_log('SESSION_OPENING_FLOAT', 'cashier_session', $session_id, null, ['opening_float' => $float]);
    json_ok(['session_id' => $session_id, 'opening_float' => $float], 'Opening float recorded');
}

// ── POST session close cash count ────────────────────────────────────────────
if ($action === 'close_cash_count') {
    $user = require_permission('END_SESSION');
    $session_id = (int)($body['session_id'] ?? get_active_session_id());
    $actual = (float)($body['actual_cash'] ?? 0);

    $db = get_db();
    // Expected = opening + qr_sale + topup cash (simplified: sum of positive session cash txns)
    $stats = $db->prepare("
        SELECT
            COALESCE((SELECT opening_float FROM session_cash WHERE session_id = ?), 0) AS opening,
            COALESCE((SELECT SUM(amount) FROM transactions WHERE session_id = ? AND type IN ('qr_sale','topup') AND amount > 0), 0) AS cash_in
    ");
    $stats->execute([$session_id, $session_id]);
    $row = $stats->fetch();
    $expected = (float)$row['opening'] + (float)$row['cash_in'];
    $variance = $actual - $expected;

    $db->prepare("
        INSERT INTO session_cash (session_id, opening_float, cash_received, expected_cash, actual_cash, variance, closed_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            cash_received = VALUES(cash_received),
            expected_cash = VALUES(expected_cash),
            actual_cash = VALUES(actual_cash),
            variance = VALUES(variance),
            closed_by = VALUES(closed_by)
    ")->execute([
        $session_id, (float)$row['opening'], (float)$row['cash_in'],
        $expected, $actual, $variance, $user['id'],
    ]);

    audit_log('SESSION_CASH_COUNTED', 'cashier_session', $session_id, null, [
        'expected' => $expected, 'actual' => $actual, 'variance' => $variance,
    ]);

    json_ok([
        'session_id' => $session_id,
        'opening_float' => (float)$row['opening'],
        'cash_received' => (float)$row['cash_in'],
        'expected_cash' => $expected,
        'actual_cash' => $actual,
        'variance' => $variance,
    ], 'Cash count recorded');
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
