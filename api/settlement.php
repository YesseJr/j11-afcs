<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

$action = $_GET['action'] ?? '';
$body   = json_body();

// ── POST run_reconciliation ──────────────────────────────────────────────────
if ($action === 'run_reconciliation') {
    require_permission('VIEW_RECONCILIATION');

    $start = $body['period_start'] ?? date('Y-m-d 00:00:00');
    $end   = $body['period_end'] ?? date('Y-m-d 23:59:59');
    $orgId = $_SESSION['user']['organization_id'] ?? null;

    $db = get_db();
    $runUid = afcs_uuid();
    $db->prepare("
        INSERT INTO reconciliation_runs (run_uid, organization_id, period_start, period_end, status, created_by)
        VALUES (?, ?, ?, ?, 'RUNNING', ?)
    ")->execute([$runUid, $orgId, $start, $end, $_SESSION['user_id']]);
    $runId = (int)$db->lastInsertId();

    try {
        // QR sales
        $qr = $db->prepare("
            SELECT COUNT(*) AS qty, COALESCE(SUM(amount),0) AS total
            FROM transactions
            WHERE type = 'qr_sale' AND created_at BETWEEN ? AND ?
              AND status IN ('COMPLETED','REVERSED')
        ");
        $qr->execute([$start, $end]);
        $qrRow = $qr->fetch();

        // Top-ups
        $tp = $db->prepare("
            SELECT COUNT(*) AS qty, COALESCE(SUM(amount),0) AS total
            FROM transactions
            WHERE type = 'topup' AND created_at BETWEEN ? AND ?
              AND status IN ('COMPLETED','REVERSED')
        ");
        $tp->execute([$start, $end]);
        $tpRow = $tp->fetch();

        // Refunds
        $rf = $db->prepare("
            SELECT COUNT(*) AS qty, COALESCE(SUM(amount),0) AS total
            FROM refunds
            WHERE status = 'COMPLETED' AND created_at BETWEEN ? AND ?
        ");
        $rf->execute([$start, $end]);
        $rfRow = $rf->fetch();

        // Session cash variance sum
        $cash = $db->prepare("
            SELECT
                COALESCE(SUM(sc.expected_cash),0) AS expected,
                COALESCE(SUM(sc.actual_cash),0) AS actual,
                COALESCE(SUM(sc.variance),0) AS variance
            FROM session_cash sc
            JOIN cashier_sessions cs ON cs.id = sc.session_id
            WHERE cs.started_at BETWEEN ? AND ?
        ");
        $cash->execute([$start, $end]);
        $cashRow = $cash->fetch();

        // Ledger net (credits - debits) in period
        $led = $db->prepare("
            SELECT COALESCE(SUM(CASE WHEN entry_type='CREDIT' THEN amount ELSE -amount END),0) AS net
            FROM ledger_entries
            WHERE created_at BETWEEN ? AND ?
        ");
        $led->execute([$start, $end]);
        $ledgerNet = (float)$led->fetchColumn();

        // Issues detection
        $issues = [];

        // Duplicate ticket codes (should be impossible)
        $dup = $db->query("SELECT ticket_code, COUNT(*) c FROM qr_tickets GROUP BY ticket_code HAVING c > 1")->fetchAll();
        if ($dup) $issues[] = ['type' => 'DUPLICATE_TICKETS', 'items' => $dup];

        // Sessions with large cash variance
        $var = $db->prepare("
            SELECT sc.session_id, sc.variance, cs.terminal
            FROM session_cash sc
            JOIN cashier_sessions cs ON cs.id = sc.session_id
            WHERE ABS(sc.variance) > 1000 AND cs.started_at BETWEEN ? AND ?
        ");
        $var->execute([$start, $end]);
        $vars = $var->fetchAll();
        if ($vars) $issues[] = ['type' => 'CASH_VARIANCE', 'items' => $vars];

        // Cards where cached balance != ledger reconstruction (sample check)
        $mismatch = [];
        $cards = $db->query("SELECT id, balance FROM cards WHERE status = 'active' LIMIT 200")->fetchAll();
        foreach ($cards as $c) {
            $recon = ledger_reconstruct_balance($db, (int)$c['id']);
            // Only flag if ledger has entries and differs
            $hasLed = $db->prepare("SELECT COUNT(*) FROM ledger_entries WHERE card_id = ?");
            $hasLed->execute([$c['id']]);
            if ((int)$hasLed->fetchColumn() > 0 && abs($recon - (float)$c['balance']) > 0.01) {
                $mismatch[] = ['card_id' => $c['id'], 'cached' => (float)$c['balance'], 'ledger' => $recon];
            }
        }
        if ($mismatch) $issues[] = ['type' => 'BALANCE_MISMATCH', 'items' => array_slice($mismatch, 0, 50)];

        $db->prepare("
            UPDATE reconciliation_runs SET
                status = 'COMPLETED',
                cash_expected = ?, cash_actual = ?, cash_variance = ?,
                card_topups = ?, qr_sales = ?, refunds_total = ?, ledger_net = ?,
                issues_json = ?, completed_at = NOW()
            WHERE id = ?
        ")->execute([
            (float)$cashRow['expected'],
            (float)$cashRow['actual'],
            (float)$cashRow['variance'],
            (float)$tpRow['total'],
            (float)$qrRow['total'],
            (float)$rfRow['total'],
            $ledgerNet,
            json_encode($issues),
            $runId,
        ]);

        audit_log('RECONCILIATION_RUN', 'reconciliation_run', $runId, null, [
            'period_start' => $start, 'period_end' => $end, 'issues' => count($issues),
        ]);

        json_ok([
            'run_id' => $runId,
            'run_uid' => $runUid,
            'period_start' => $start,
            'period_end' => $end,
            'qr_sales' => ['qty' => (int)$qrRow['qty'], 'total' => (float)$qrRow['total']],
            'topups' => ['qty' => (int)$tpRow['qty'], 'total' => (float)$tpRow['total']],
            'refunds' => ['qty' => (int)$rfRow['qty'], 'total' => (float)$rfRow['total']],
            'cash' => [
                'expected' => (float)$cashRow['expected'],
                'actual' => (float)$cashRow['actual'],
                'variance' => (float)$cashRow['variance'],
            ],
            'ledger_net' => $ledgerNet,
            'issues' => $issues,
            'net_revenue' => (float)$qrRow['total'] + (float)$tpRow['total'] - (float)$rfRow['total'],
        ], 'Reconciliation completed');
    } catch (Throwable $e) {
        $db->prepare("UPDATE reconciliation_runs SET status='FAILED', issues_json=? WHERE id=?")
           ->execute([json_encode(['error' => $e->getMessage()]), $runId]);
        error_log('reconciliation failed: ' . $e->getMessage());
        json_err('Reconciliation failed.', 500, 'RECON_FAILED');
    }
}

// ── POST create_settlement ───────────────────────────────────────────────────
if ($action === 'create_settlement') {
    require_permission('VIEW_RECONCILIATION');

    $start = $body['period_start'] ?? date('Y-m-d 00:00:00', strtotime('-1 day'));
    $end   = $body['period_end'] ?? date('Y-m-d 23:59:59', strtotime('-1 day'));
    $orgId = $_SESSION['user']['organization_id'] ?? null;
    $db = get_db();
    $uid = afcs_uuid();

    $db->beginTransaction();
    try {
        $db->prepare("
            INSERT INTO settlement_batches (batch_uid, organization_id, period_start, period_end, status, created_by)
            VALUES (?, ?, ?, ?, 'CALCULATING', ?)
        ")->execute([$uid, $orgId, $start, $end, $_SESSION['user_id']]);
        $batchId = (int)$db->lastInsertId();

        $lines = [];
        // QR revenue
        $qr = $db->prepare("SELECT COUNT(*) q, COALESCE(SUM(amount),0) t FROM transactions WHERE type='qr_sale' AND status='COMPLETED' AND created_at BETWEEN ? AND ?");
        $qr->execute([$start, $end]);
        $qrR = $qr->fetch();
        $lines[] = ['QR_SALES', 'qr_sale', (float)$qrR['t'], (int)$qrR['q']];

        $tp = $db->prepare("SELECT COUNT(*) q, COALESCE(SUM(amount),0) t FROM transactions WHERE type='topup' AND status='COMPLETED' AND created_at BETWEEN ? AND ?");
        $tp->execute([$start, $end]);
        $tpR = $tp->fetch();
        $lines[] = ['CARD_TOPUPS', 'topup', (float)$tpR['t'], (int)$tpR['q']];

        $rf = $db->prepare("SELECT COUNT(*) q, COALESCE(SUM(amount),0) t FROM refunds WHERE status='COMPLETED' AND created_at BETWEEN ? AND ?");
        $rf->execute([$start, $end]);
        $rfR = $rf->fetch();
        $lines[] = ['REFUNDS', 'refund', -(float)$rfR['t'], (int)$rfR['q']];

        $ins = $db->prepare("INSERT INTO settlement_lines (batch_id, line_type, reference, amount, quantity) VALUES (?,?,?,?,?)");
        $totalRev = 0; $totalRef = 0; $txnCount = 0;
        foreach ($lines as $L) {
            $ins->execute([$batchId, $L[0], $L[1], $L[2], $L[3]]);
            if ($L[2] >= 0) $totalRev += $L[2]; else $totalRef += abs($L[2]);
            $txnCount += $L[3];
        }
        $net = $totalRev - $totalRef;

        $db->prepare("
            UPDATE settlement_batches SET status='COMPLETED', total_revenue=?, total_refunds=?, total_net=?, txn_count=?
            WHERE id=?
        ")->execute([$totalRev, $totalRef, $net, $txnCount, $batchId]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('settlement failed: ' . $e->getMessage());
        json_err('Settlement failed.', 500, 'SETTLEMENT_FAILED');
    }

    audit_log('SETTLEMENT_CREATED', 'settlement_batch', $batchId, null, [
        'period_start' => $start, 'period_end' => $end, 'net' => $net,
    ]);

    json_ok([
        'batch_id' => $batchId,
        'batch_uid' => $uid,
        'total_revenue' => $totalRev,
        'total_refunds' => $totalRef,
        'total_net' => $net,
        'txn_count' => $txnCount,
        'lines' => $lines,
    ], 'Settlement batch created');
}

// ── GET list settlements ─────────────────────────────────────────────────────
if ($action === 'list_settlements') {
    require_permission('VIEW_RECONCILIATION');
    $db = get_db();
    $rows = $db->query("SELECT * FROM settlement_batches ORDER BY created_at DESC LIMIT 50")->fetchAll();
    json_ok($rows);
}

// ── GET settlement detail ────────────────────────────────────────────────────
if ($action === 'settlement_detail') {
    require_permission('VIEW_RECONCILIATION');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('id required.', 400, 'MISSING_ID');
    $db = get_db();
    $batch = $db->prepare("SELECT * FROM settlement_batches WHERE id = ?");
    $batch->execute([$id]);
    $b = $batch->fetch();
    if (!$b) json_err('Not found.', 404, 'NOT_FOUND');
    $lines = $db->prepare("SELECT * FROM settlement_lines WHERE batch_id = ?");
    $lines->execute([$id]);
    $b['lines'] = $lines->fetchAll();
    json_ok($b);
}

// ── GET list reconciliation runs ─────────────────────────────────────────────
if ($action === 'list_reconciliations') {
    require_permission('VIEW_RECONCILIATION');
    $db = get_db();
    json_ok($db->query("SELECT id, run_uid, period_start, period_end, status, cash_expected, cash_actual, cash_variance, card_topups, qr_sales, refunds_total, ledger_net, created_at, completed_at FROM reconciliation_runs ORDER BY created_at DESC LIMIT 50")->fetchAll());
}

json_err('Unknown action', 404, 'UNKNOWN_ACTION');
