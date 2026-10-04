<?php
/**
 * Simulated concurrency checks for financial invariants.
 * Run: php tests/concurrency_sim.php
 * Uses sequential FOR UPDATE style verification of double-apply protection via idempotency.
 */
error_reporting(E_ALL);
require_once __DIR__ . '/../api/bootstrap.php';

$failed = 0;
function check($label, $cond) {
    global $failed;
    echo ($cond ? "[PASS] " : "[FAIL] ") . $label . "\n";
    if (!$cond) $failed++;
}

try {
    $db = get_db();

    // Ensure a test card exists
    $db->prepare("INSERT IGNORE INTO cards (card_number, holder_name, card_type, balance, status, registered_at, activated_at)
                  VALUES ('999999999901', 'CONCURRENCY TEST', 'Adult', 1000.00, 'active', NOW(), NOW())")->execute();
    $card = $db->query("SELECT * FROM cards WHERE card_number='999999999901'")->fetch();
    check('test card present', (bool)$card);

    // Simulate two identical top-up attempts with same idempotency key using helper path logic
    $key = 'test-idem-' . bin2hex(random_bytes(8));
    $scope = 'topup_test';

    // First insert key
    $db->prepare("INSERT INTO idempotency_keys (idempotency_key, scope, request_hash, response_code, response_body, expires_at)
                  VALUES (?, ?, ?, 200, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))")
       ->execute([$key, $scope, hash('sha256', 'x'), json_encode(['success'=>true,'data'=>['once'=>true]])]);

    // Second insert should fail unique constraint
    $dup = false;
    try {
        $db->prepare("INSERT INTO idempotency_keys (idempotency_key, scope) VALUES (?, ?)")->execute([$key, $scope]);
    } catch (PDOException $e) {
        $dup = true;
    }
    check('idempotency unique prevents double insert', $dup);

    // Atomic transfer invariant: debit+credit in one transaction
    $db->beginTransaction();
    $c1 = $db->query("SELECT id, balance FROM cards WHERE card_number='999999999901' FOR UPDATE")->fetch();
    $db->prepare("INSERT IGNORE INTO cards (card_number, holder_name, card_type, balance, status, registered_at, activated_at)
                  VALUES ('999999999902', 'CONCURRENCY DEST', 'Adult', 0, 'active', NOW(), NOW())")->execute();
    $c2 = $db->query("SELECT id, balance FROM cards WHERE card_number='999999999902' FOR UPDATE")->fetch();
    $amt = 100.0;
    $db->prepare("UPDATE cards SET balance = balance - ? WHERE id = ?")->execute([$amt, $c1['id']]);
    $db->prepare("UPDATE cards SET balance = balance + ? WHERE id = ?")->execute([$amt, $c2['id']]);
    $db->commit();
    $b1 = (float)$db->query("SELECT balance FROM cards WHERE id={$c1['id']}")->fetchColumn();
    $b2 = (float)$db->query("SELECT balance FROM cards WHERE id={$c2['id']}")->fetchColumn();
    check('transfer conserves value', abs(($b1 + $b2) - ((float)$c1['balance'] + (float)$c2['balance'])) < 0.01);

    // QR validate double-spend simulation: insert ticket then two validates under lock
    $code = 'TKT-TEST-' . bin2hex(random_bytes(4));
    // need a session
    $sid = (int)$db->query("SELECT id FROM cashier_sessions ORDER BY id DESC LIMIT 1")->fetchColumn();
    if (!$sid) {
        $uid = (int)$db->query("SELECT id FROM users LIMIT 1")->fetchColumn();
        $db->prepare("INSERT INTO cashier_sessions (user_id, terminal) VALUES (?, 'TEST')")->execute([$uid]);
        $sid = (int)$db->lastInsertId();
    }
    $db->prepare("INSERT INTO qr_tickets (ticket_code, session_id, route, price, expires_at, status) VALUES (?,?,?,200,DATE_ADD(NOW(),INTERVAL 1 DAY),'valid')")
       ->execute([$code, $sid, 'TEST']);
    $tid = (int)$db->lastInsertId();

    $successes = 0;
    for ($i = 0; $i < 2; $i++) {
        $db->beginTransaction();
        $t = $db->prepare("SELECT * FROM qr_tickets WHERE id = ? FOR UPDATE");
        $t->execute([$tid]);
        $row = $t->fetch();
        if ($row && $row['status'] === 'valid') {
            $db->prepare("UPDATE qr_tickets SET status='used', used_at=NOW() WHERE id=? AND status='valid'")->execute([$tid]);
            if ($db->query("SELECT ROW_COUNT()")->fetchColumn() > 0) $successes++;
        }
        $db->commit();
    }
    check('double validate only succeeds once', $successes === 1);

    echo $failed ? "\n$failed failed\n" : "\nAll concurrency simulations passed\n";
    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    exit(1);
}
