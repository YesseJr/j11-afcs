<?php
// ── Financial ledger helpers (dual-write with cards.balance) ──────────────────

/**
 * Append an immutable ledger entry. Caller must be inside a DB transaction
 * when used together with balance updates.
 */
function ledger_append(
    PDO $db,
    int $cardId,
    string $entryType,          // CREDIT | DEBIT
    float $amount,
    string $reasonCode,
    float $balanceAfter,
    array $opts = []
): string {
    $uid = afcs_uuid();
    $stmt = $db->prepare("
        INSERT INTO ledger_entries (
            entry_uid, organization_id, card_id, entry_type, amount, currency,
            balance_after, reason_code, reference_type, reference_id,
            transaction_id, session_id, user_id, correlation_id, idempotency_key, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $uid,
        $opts['organization_id'] ?? ($_SESSION['user']['organization_id'] ?? null),
        $cardId,
        $entryType,
        abs($amount),
        $opts['currency'] ?? 'TZS',
        $balanceAfter,
        $reasonCode,
        $opts['reference_type'] ?? null,
        $opts['reference_id'] ?? null,
        $opts['transaction_id'] ?? null,
        $opts['session_id'] ?? null,
        $opts['user_id'] ?? ($_SESSION['user_id'] ?? null),
        $opts['correlation_id'] ?? ($GLOBALS['AFCS_REQUEST_ID'] ?? null),
        $opts['idempotency_key'] ?? null,
        $opts['notes'] ?? null,
    ]);
    return $uid;
}

/**
 * Reconstruct balance from ledger (authoritative check).
 */
function ledger_reconstruct_balance(PDO $db, int $cardId): float {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(
            CASE WHEN entry_type = 'CREDIT' THEN amount ELSE -amount END
        ), 0) AS bal
        FROM ledger_entries WHERE card_id = ?
    ");
    $stmt->execute([$cardId]);
    return (float)$stmt->fetchColumn();
}
