<?php
// ── MySQL Configuration ───────────────────────────────────────────────────────
// Change these to match your MySQL setup. Prefer environment variables in production.
define('DB_HOST', getenv('AFCS_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('AFCS_DB_PORT') ?: '3307');
define('DB_NAME', getenv('AFCS_DB_NAME') ?: 'afcs');
define('DB_USER', getenv('AFCS_DB_USER') ?: 'root');
define('DB_PASS', getenv('AFCS_DB_PASS') !== false ? getenv('AFCS_DB_PASS') : '');

// Application-level secrets (override via env in production)
define('AFCS_HMAC_SECRET', getenv('AFCS_HMAC_SECRET') ?: 'CHANGE_ME_IN_PRODUCTION_afcs_hmac_v1');
define('AFCS_CORS_ORIGIN', getenv('AFCS_CORS_ORIGIN') ?: ''); // empty = same-origin only (no *)

function get_db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        // Do not leak host/SQLSTATE details to clients in production-style responses
        $detail = (getenv('AFCS_APP_ENV') === 'development')
            ? (' ' . $e->getMessage())
            : '';
        echo json_encode([
            'success'    => false,
            'error_code' => 'SERVICE_UNAVAILABLE',
            'message'    => 'Central fare system is temporarily unavailable. Terminal offline mode requires a previously enrolled device with a valid offline grant.' . $detail,
            'data'       => null,
            'request_id' => $GLOBALS['AFCS_REQUEST_ID'] ?? null,
        ]);
        exit;
    }

    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $db) {

    // ── Core existing tables (preserved) ─────────────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INT             AUTO_INCREMENT PRIMARY KEY,
            username      VARCHAR(50)     UNIQUE NOT NULL,
            password_hash VARCHAR(255)    NOT NULL,
            first_name    VARCHAR(100)    NOT NULL,
            last_name     VARCHAR(100)    NOT NULL,
            role          ENUM('cashier','supervisor','admin') NOT NULL DEFAULT 'cashier',
            terminal      VARCHAR(100)    NOT NULL DEFAULT 'KIGAMBONI TERMINAL',
            active        TINYINT(1)      NOT NULL DEFAULT 1,
            created_at    DATETIME        DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS cards (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            card_number     VARCHAR(30)     UNIQUE NOT NULL,
            holder_name     VARCHAR(150),
            phone           VARCHAR(20),
            gender          CHAR(1),
            dob             DATE,
            card_type       ENUM('Adult','Staff') NOT NULL DEFAULT 'Adult',
            balance         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
            status          ENUM('new','active','inactive') NOT NULL DEFAULT 'new',
            penalty_flag    TINYINT(1)      NOT NULL DEFAULT 0,
            registered_by   INT,
            registered_at   DATETIME,
            activated_at    DATETIME,
            deactivated_at  DATETIME,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Migration: collapse old card types
    try {
        $col = $db->query("SHOW COLUMNS FROM cards LIKE 'card_type'")->fetch();
        if ($col && strpos($col['Type'], "'Regular'") !== false) {
            $db->exec("ALTER TABLE cards MODIFY card_type ENUM('Adult','Staff','Regular','Student','Elderly') NOT NULL DEFAULT 'Adult'");
            $db->exec("UPDATE cards SET card_type='Adult' WHERE card_type IN ('Regular','Student','Elderly')");
            $db->exec("ALTER TABLE cards MODIFY card_type ENUM('Adult','Staff') NOT NULL DEFAULT 'Adult'");
        }
    } catch (PDOException $e) { /* already migrated */ }

    $db->exec("
        CREATE TABLE IF NOT EXISTS cashier_sessions (
            id           INT          AUTO_INCREMENT PRIMARY KEY,
            user_id      INT          NOT NULL,
            terminal     VARCHAR(100) NOT NULL DEFAULT 'KIGAMBONI TERMINAL',
            session_code VARCHAR(10)  DEFAULT NULL,
            started_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            ended_at     DATETIME,
            closed_by    INT          DEFAULT NULL,
            FOREIGN KEY (user_id)   REFERENCES users(id),
            FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $col = $db->query("SHOW COLUMNS FROM cashier_sessions LIKE 'closed_by'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE cashier_sessions ADD COLUMN closed_by INT DEFAULT NULL AFTER ended_at");
            $db->exec("ALTER TABLE cashier_sessions ADD FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL");
        }
    } catch (PDOException $e) { /* already migrated */ }

    $db->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id          INT           AUTO_INCREMENT PRIMARY KEY,
            session_id  INT           NOT NULL,
            type        VARCHAR(30)   NOT NULL,
            card_id     INT,
            amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            quantity    INT           NOT NULL DEFAULT 1,
            route       VARCHAR(100),
            notes       TEXT,
            created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES cashier_sessions(id),
            FOREIGN KEY (card_id)    REFERENCES cards(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS qr_tickets (
            id           INT           AUTO_INCREMENT PRIMARY KEY,
            ticket_code  VARCHAR(60)   UNIQUE NOT NULL,
            session_id   INT           NOT NULL,
            route        VARCHAR(100)  NOT NULL,
            price        DECIMAL(12,2) NOT NULL,
            status       ENUM('valid','used','expired','cancelled') NOT NULL DEFAULT 'valid',
            qr_payload   TEXT,
            issued_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
            expires_at   DATETIME,
            used_at      DATETIME,
            FOREIGN KEY (session_id) REFERENCES cashier_sessions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Stage 1: Foundation tables ───────────────────────────────────────────

    $db->exec("
        CREATE TABLE IF NOT EXISTS organizations (
            id          INT             AUTO_INCREMENT PRIMARY KEY,
            code        VARCHAR(32)     UNIQUE NOT NULL,
            name        VARCHAR(150)    NOT NULL,
            type        ENUM('authority','operator','agency') NOT NULL DEFAULT 'operator',
            currency    CHAR(3)         NOT NULL DEFAULT 'TZS',
            timezone    VARCHAR(64)     NOT NULL DEFAULT 'Africa/Dar_es_Salaam',
            active      TINYINT(1)      NOT NULL DEFAULT 1,
            created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            device_uid      VARCHAR(64)     UNIQUE NOT NULL,
            hardware_serial VARCHAR(100),
            device_type     ENUM('cashier_terminal','pos','gate_validator','inspector_mobile','qr_scanner','nfc_reader','printer','station_server','vehicle_validator','other') NOT NULL DEFAULT 'cashier_terminal',
            name            VARCHAR(100),
            organization_id INT,
            station_code    VARCHAR(50),
            terminal_label  VARCHAR(100),
            status          ENUM('ACTIVE','INACTIVE','SUSPENDED','LOST','MAINTENANCE','RETIRED') NOT NULL DEFAULT 'ACTIVE',
            software_version VARCHAR(32),
            last_heartbeat  DATETIME,
            last_sync_at    DATETIME,
            registered_at   DATETIME        DEFAULT CURRENT_TIMESTAMP,
            config_json     JSON,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS ledger_entries (
            id              BIGINT          AUTO_INCREMENT PRIMARY KEY,
            entry_uid       VARCHAR(36)     UNIQUE NOT NULL,
            organization_id INT,
            card_id         INT,
            wallet_ref      VARCHAR(64),
            entry_type      ENUM('CREDIT','DEBIT') NOT NULL,
            amount          DECIMAL(14,2)   NOT NULL,
            currency        CHAR(3)         NOT NULL DEFAULT 'TZS',
            balance_after   DECIMAL(14,2),
            reason_code     VARCHAR(40)     NOT NULL,
            reference_type  VARCHAR(40),
            reference_id    VARCHAR(64),
            transaction_id  INT,
            session_id      INT,
            device_id       INT,
            user_id         INT,
            correlation_id  VARCHAR(64),
            idempotency_key VARCHAR(64),
            notes           TEXT,
            created_at      DATETIME(3)     DEFAULT CURRENT_TIMESTAMP(3),
            INDEX idx_ledger_card (card_id, created_at),
            INDEX idx_ledger_ref (reference_type, reference_id),
            INDEX idx_ledger_corr (correlation_id),
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS idempotency_keys (
            id              BIGINT          AUTO_INCREMENT PRIMARY KEY,
            idempotency_key VARCHAR(128)    NOT NULL,
            scope           VARCHAR(64)     NOT NULL DEFAULT 'global',
            request_hash    VARCHAR(64),
            response_code   INT,
            response_body   JSON,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            expires_at      DATETIME,
            UNIQUE KEY uq_idem (idempotency_key, scope)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id              BIGINT          AUTO_INCREMENT PRIMARY KEY,
            event_uid       VARCHAR(36)     UNIQUE NOT NULL,
            organization_id INT,
            actor_user_id   INT,
            actor_username  VARCHAR(50),
            actor_role      VARCHAR(30),
            device_id       INT,
            action          VARCHAR(80)     NOT NULL,
            entity_type     VARCHAR(40),
            entity_id       VARCHAR(64),
            previous_state  JSON,
            new_state       JSON,
            reason          TEXT,
            ip_address      VARCHAR(45),
            user_agent      VARCHAR(255),
            request_id      VARCHAR(36),
            correlation_id  VARCHAR(64),
            source          VARCHAR(40)     DEFAULT 'api',
            created_at      DATETIME(3)     DEFAULT CURRENT_TIMESTAMP(3),
            INDEX idx_audit_action (action, created_at),
            INDEX idx_audit_actor (actor_user_id, created_at),
            INDEX idx_audit_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS system_config (
            id          INT             AUTO_INCREMENT PRIMARY KEY,
            organization_id INT,
            config_key  VARCHAR(100)    NOT NULL,
            config_value TEXT,
            value_type  ENUM('string','number','boolean','json') NOT NULL DEFAULT 'string',
            description VARCHAR(255),
            updated_by  INT,
            updated_at  DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_org_key (organization_id, config_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Safe column migrations on users ──────────────────────────────────────
    $userCols = [
        'organization_id'        => "INT DEFAULT NULL AFTER id",
        'must_change_password'   => "TINYINT(1) NOT NULL DEFAULT 0 AFTER active",
        'failed_login_attempts'  => "INT NOT NULL DEFAULT 0 AFTER must_change_password",
        'locked_until'           => "DATETIME DEFAULT NULL AFTER failed_login_attempts",
        'last_login_at'          => "DATETIME DEFAULT NULL AFTER locked_until",
        'password_changed_at'    => "DATETIME DEFAULT NULL AFTER last_login_at",
    ];
    foreach ($userCols as $col => $def) {
        try {
            $exists = $db->query("SHOW COLUMNS FROM users LIKE '$col'")->fetch();
            if (!$exists) {
                $db->exec("ALTER TABLE users ADD COLUMN $col $def");
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    try {
        $db->exec("ALTER TABLE users ADD CONSTRAINT fk_users_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
    } catch (PDOException $e) { /* already exists */ }

    $txnCols = [
        'correlation_id'   => "VARCHAR(64) DEFAULT NULL AFTER notes",
        'idempotency_key'  => "VARCHAR(128) DEFAULT NULL AFTER correlation_id",
        'status'           => "VARCHAR(20) NOT NULL DEFAULT 'COMPLETED' AFTER idempotency_key",
        'currency'         => "CHAR(3) NOT NULL DEFAULT 'TZS' AFTER amount",
    ];
    foreach ($txnCols as $col => $def) {
        try {
            $exists = $db->query("SHOW COLUMNS FROM transactions LIKE '$col'")->fetch();
            if (!$exists) {
                $db->exec("ALTER TABLE transactions ADD COLUMN $col $def");
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    try {
        $exists = $db->query("SHOW COLUMNS FROM qr_tickets LIKE 'signature'")->fetch();
        if (!$exists) {
            $db->exec("ALTER TABLE qr_tickets ADD COLUMN signature VARCHAR(128) DEFAULT NULL AFTER qr_payload");
        }
    } catch (PDOException $e) { /* ignore */ }

    // ── Seed default organization ────────────────────────────────────────────
    $orgCount = (int)$db->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    if ($orgCount === 0) {
        $db->exec("
            INSERT INTO organizations (code, name, type, currency, timezone)
            VALUES ('DART', 'Dar Rapid Transit', 'operator', 'TZS', 'Africa/Dar_es_Salaam')
        ");
    }
    $defaultOrgId = (int)$db->query("SELECT id FROM organizations WHERE code='DART' LIMIT 1")->fetchColumn();

    $cfgCount = (int)$db->query("SELECT COUNT(*) FROM system_config")->fetchColumn();
    if ($cfgCount === 0 && $defaultOrgId) {
        $cfgs = [
            ['student_fare', '200', 'number', 'Flat student QR ticket fare'],
            ['currency', 'TZS', 'string', 'Default currency code'],
            ['currency_symbol', 'Tshs', 'string', 'Display symbol'],
            ['max_topup', '500000', 'number', 'Maximum single top-up amount'],
            ['ticket_validity_hours', '24', 'number', 'QR ticket validity in hours'],
            ['password_min_length', '8', 'number', 'Minimum password length'],
            ['max_failed_logins', '5', 'number', 'Lock account after N failed attempts'],
            ['lockout_minutes', '15', 'number', 'Account lockout duration'],
            ['default_terminal', 'KIGAMBONI TERMINAL', 'string', 'Default terminal label'],
        ];
        $ins = $db->prepare("INSERT INTO system_config (organization_id, config_key, config_value, value_type, description) VALUES (?,?,?,?,?)");
        foreach ($cfgs as $c) {
            $ins->execute([$defaultOrgId, $c[0], $c[1], $c[2], $c[3]]);
        }
    }

    $count = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $hash      = password_hash('1234', PASSWORD_DEFAULT);
        $hashAdmin = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (organization_id, username, password_hash, first_name, last_name, role, terminal, must_change_password)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$defaultOrgId ?: null, 'luogaw',    $hash,      'Wayton', 'Luoga',   'cashier', 'KIGAMBONI TERMINAL']);
        $stmt->execute([$defaultOrgId ?: null, 'mbangalae', $hash,      'Edward', 'Mbangala','cashier', 'KIGAMBONI TERMINAL']);
        $stmt->execute([$defaultOrgId ?: null, 'admin',     $hashAdmin, 'System', 'Admin',   'admin',   'HQ']);
    } else {
        if ($defaultOrgId) {
            $db->exec("UPDATE users SET organization_id = $defaultOrgId WHERE organization_id IS NULL");
        }
    }

    $stmt = $db->prepare("
        INSERT IGNORE INTO cards (card_number, holder_name, phone, card_type, gender, balance, status, registered_at, activated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute(['994160222206', 'JAMES MWANGI',      '0712111001', 'Adult', 'M', 3000.00, 'active']);
    $stmt->execute(['994160232770', 'SABRINA A MEDARD',  '0754222002', 'Adult', 'F', 1500.00, 'active']);
    $stmt->execute(['994160199001', 'JOHN KAMAU',        '0765333003', 'Adult', 'M',  850.00, 'active']);
    $stmt->execute(['994160009988', 'ANN CHELSEA',       '0778444004', 'Adult', 'F',  200.00, 'inactive']);
    $stmt->execute(['994160187650', 'FATUMA OMAR',       '0756234101', 'Adult', 'F',  500.00, 'active']);
    $stmt->execute(['994160187651', 'IBRAHIM HASSAN',    '0712234102', 'Adult', 'M', 1200.00, 'active']);
    $stmt->execute(['994160187652', 'LUCIA MWAMBA',      '0765234103', 'Adult', 'F',  750.00, 'active']);
    $stmt->execute(['994160187653', 'PETER NJOROGE',     '0778234104', 'Staff', 'M', 5000.00, 'active']);
    $stmt->execute(['994160187654', 'AMINA SALEHE',      '0754234105', 'Adult', 'F',  300.00, 'active']);
    $stmt->execute(['994160187655', 'DAVID OCHIENG',     '0712234106', 'Adult', 'M',  950.00, 'active']);

    $blank = $db->prepare("INSERT IGNORE INTO cards (card_number, status) VALUES (?, 'new')");
    $blankNos = [
        '994160000001','994160000002','994160000003','994160000004','994160000005',
        '994160000006','994160000007','994160000008','994160000009','994160000010',
        '994160000011','994160000012','994160000013','994160000014','994160000015',
        '994160000016','994160000017','994160000018','994160000019','994160000020',
        '994160000021','994160000022','994160000023','994160000024','994160000025',
    ];
    foreach ($blankNos as $no) { $blank->execute([$no]); }

    // Stage 2 extensions (RBAC, refunds, sync, fares)
    require_once __DIR__ . '/schema_stage2.php';
    init_schema_stage2($db);
    require_once __DIR__ . '/schema_stage3.php';
    init_schema_stage3($db);
    require_once __DIR__ . '/schema_stage4.php';
    init_schema_stage4($db);
}

/**
 * Read a system_config value with optional default.
 */
function get_config(string $key, $default = null, ?int $orgId = null) {
    static $cache = [];
    $ck = ($orgId ?? 0) . ':' . $key;
    if (array_key_exists($ck, $cache)) return $cache[$ck];

    try {
        $db = get_db();
        if ($orgId) {
            $stmt = $db->prepare("SELECT config_value, value_type FROM system_config WHERE config_key = ? AND organization_id = ? LIMIT 1");
            $stmt->execute([$key, $orgId]);
        } else {
            $stmt = $db->prepare("SELECT config_value, value_type FROM system_config WHERE config_key = ? ORDER BY organization_id IS NULL LIMIT 1");
            $stmt->execute([$key]);
        }
        $row = $stmt->fetch();
        if (!$row) {
            $cache[$ck] = $default;
            return $default;
        }
        $val = $row['config_value'];
        if ($row['value_type'] === 'number') $val = is_numeric($val) ? $val + 0 : $default;
        if ($row['value_type'] === 'boolean') $val = in_array(strtolower((string)$val), ['1','true','yes'], true);
        if ($row['value_type'] === 'json') $val = json_decode($val, true);
        $cache[$ck] = $val;
        return $val;
    } catch (Throwable $e) {
        return $default;
    }
}
