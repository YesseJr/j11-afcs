<?php
/**
 * Stage 2 schema: RBAC, refunds, sync outbox, session cash, fare products.
 * Called from init_schema() — fully idempotent.
 */
function init_schema_stage2(PDO $db): void {

    // ── Roles & permissions (RBAC) ───────────────────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id          INT             AUTO_INCREMENT PRIMARY KEY,
            code        VARCHAR(64)     UNIQUE NOT NULL,
            description VARCHAR(255),
            category    VARCHAR(40)     NOT NULL DEFAULT 'general'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id          INT             AUTO_INCREMENT PRIMARY KEY,
            code        VARCHAR(40)     UNIQUE NOT NULL,
            name        VARCHAR(100)    NOT NULL,
            description VARCHAR(255),
            is_system   TINYINT(1)      NOT NULL DEFAULT 1,
            created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id       INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_roles (
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed permissions
    $perms = [
        ['LOGIN', 'Authenticate to the system', 'auth'],
        ['CHANGE_PASSWORD', 'Change own password', 'auth'],
        ['START_SESSION', 'Open cashier session', 'session'],
        ['END_SESSION', 'Close own cashier session', 'session'],
        ['FORCE_END_SESSION', 'Force-close any session', 'session'],
        ['VIEW_SESSION_REPORT', 'View session reports', 'session'],
        ['ISSUE_QR', 'Issue QR tickets', 'tickets'],
        ['VALIDATE_QR', 'Validate QR tickets', 'tickets'],
        ['CANCEL_QR', 'Cancel issued QR tickets', 'tickets'],
        ['CREATE_CARD', 'Register / issue smart cards', 'cards'],
        ['ACTIVATE_CARD', 'Activate cards', 'cards'],
        ['DEACTIVATE_CARD', 'Deactivate / block cards', 'cards'],
        ['TOPUP_CARD', 'Top up card balance', 'cards'],
        ['TRANSFER_BALANCE', 'Transfer balance between cards', 'cards'],
        ['VIEW_CARD', 'Lookup card details', 'cards'],
        ['MANAGE_CARD_INVENTORY', 'Add blank cards to inventory', 'cards'],
        ['REFUND_TRANSACTION', 'Issue refunds', 'finance'],
        ['REVERSE_TRANSACTION', 'Full reversal of a transaction', 'finance'],
        ['VIEW_FINANCIAL_REPORTS', 'View financial reports', 'finance'],
        ['MANAGE_FARES', 'Configure fare products and rules', 'fares'],
        ['MANAGE_DEVICES', 'Register and manage devices', 'devices'],
        ['VIEW_DEVICES', 'View device list and health', 'devices'],
        ['VIEW_AUDIT_LOGS', 'View audit log', 'admin'],
        ['MANAGE_USERS', 'Create and manage users', 'admin'],
        ['MANAGE_ROLES', 'Manage roles and permissions', 'admin'],
        ['CONFIGURE_SYSTEM', 'Change system configuration', 'admin'],
        ['VIEW_RECONCILIATION', 'View reconciliation reports', 'finance'],
        ['ADJUST_BALANCE', 'Administrative balance adjustment', 'finance'],
    ];
    $pIns = $db->prepare("INSERT IGNORE INTO permissions (code, description, category) VALUES (?,?,?)");
    foreach ($perms as $p) $pIns->execute($p);

    // Seed roles
    $roles = [
        ['super_admin', 'Super Administrator', 'Full platform control'],
        ['admin', 'Operator Administrator', 'Operator-level administration'],
        ['supervisor', 'Supervisor', 'Oversee cashiers and approve exceptions'],
        ['cashier', 'Cashier', 'Front-line ticket and card operations'],
        ['inspector', 'Inspector', 'Validate media and view limited info'],
        ['finance', 'Finance Officer', 'Refunds, reports, reconciliation'],
        ['auditor', 'Auditor', 'Read-only audit and financial views'],
        ['device_admin', 'Device Administrator', 'Device enrollment and health'],
        ['readonly', 'Read-only User', 'View dashboards and reports only'],
    ];
    $rIns = $db->prepare("INSERT IGNORE INTO roles (code, name, description) VALUES (?,?,?)");
    foreach ($roles as $r) $rIns->execute($r);

    // Map role → permissions
    $rolePermMap = [
        'super_admin' => array_column($perms, 0), // all
        'admin' => [
            'LOGIN','CHANGE_PASSWORD','START_SESSION','END_SESSION','FORCE_END_SESSION',
            'VIEW_SESSION_REPORT','ISSUE_QR','VALIDATE_QR','CANCEL_QR',
            'CREATE_CARD','ACTIVATE_CARD','DEACTIVATE_CARD','TOPUP_CARD','TRANSFER_BALANCE',
            'VIEW_CARD','MANAGE_CARD_INVENTORY','REFUND_TRANSACTION','REVERSE_TRANSACTION',
            'VIEW_FINANCIAL_REPORTS','MANAGE_FARES','MANAGE_DEVICES','VIEW_DEVICES',
            'VIEW_AUDIT_LOGS','MANAGE_USERS','CONFIGURE_SYSTEM','VIEW_RECONCILIATION','ADJUST_BALANCE',
        ],
        'supervisor' => [
            'LOGIN','CHANGE_PASSWORD','START_SESSION','END_SESSION','FORCE_END_SESSION',
            'VIEW_SESSION_REPORT','ISSUE_QR','VALIDATE_QR','CREATE_CARD','ACTIVATE_CARD',
            'DEACTIVATE_CARD','TOPUP_CARD','TRANSFER_BALANCE','VIEW_CARD','REFUND_TRANSACTION',
            'VIEW_FINANCIAL_REPORTS','VIEW_DEVICES',
        ],
        'cashier' => [
            'LOGIN','CHANGE_PASSWORD','START_SESSION','END_SESSION','VIEW_SESSION_REPORT',
            'ISSUE_QR','CREATE_CARD','ACTIVATE_CARD','DEACTIVATE_CARD','TOPUP_CARD',
            'TRANSFER_BALANCE','VIEW_CARD',
        ],
        'inspector' => [
            'LOGIN','CHANGE_PASSWORD','VALIDATE_QR','VIEW_CARD',
        ],
        'finance' => [
            'LOGIN','CHANGE_PASSWORD','VIEW_SESSION_REPORT','VIEW_CARD',
            'REFUND_TRANSACTION','REVERSE_TRANSACTION','VIEW_FINANCIAL_REPORTS',
            'VIEW_RECONCILIATION','VIEW_AUDIT_LOGS','ADJUST_BALANCE',
        ],
        'auditor' => [
            'LOGIN','CHANGE_PASSWORD','VIEW_SESSION_REPORT','VIEW_CARD',
            'VIEW_FINANCIAL_REPORTS','VIEW_AUDIT_LOGS','VIEW_RECONCILIATION','VIEW_DEVICES',
        ],
        'device_admin' => [
            'LOGIN','CHANGE_PASSWORD','MANAGE_DEVICES','VIEW_DEVICES','VIEW_AUDIT_LOGS',
        ],
        'readonly' => [
            'LOGIN','CHANGE_PASSWORD','VIEW_SESSION_REPORT','VIEW_CARD',
            'VIEW_FINANCIAL_REPORTS','VIEW_DEVICES',
        ],
    ];

    $rpIns = $db->prepare("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT r.id, p.id FROM roles r, permissions p
        WHERE r.code = ? AND p.code = ?
    ");
    foreach ($rolePermMap as $roleCode => $permCodes) {
        foreach ($permCodes as $pc) {
            $rpIns->execute([$roleCode, $pc]);
        }
    }

    // Assign roles to existing users based on legacy role column
    $mapLegacy = [
        'admin' => 'admin',
        'supervisor' => 'supervisor',
        'cashier' => 'cashier',
    ];
    foreach ($mapLegacy as $legacy => $roleCode) {
        $db->exec("
            INSERT IGNORE INTO user_roles (user_id, role_id)
            SELECT u.id, r.id FROM users u, roles r
            WHERE u.role = '$legacy' AND r.code = '$roleCode'
        ");
    }

    // ── Refunds / reversals (immutable corrective events) ────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS refunds (
            id                  INT             AUTO_INCREMENT PRIMARY KEY,
            refund_uid          VARCHAR(36)     UNIQUE NOT NULL,
            original_txn_id     INT             NOT NULL,
            session_id          INT,
            card_id             INT,
            amount              DECIMAL(12,2)   NOT NULL,
            currency            CHAR(3)         NOT NULL DEFAULT 'TZS',
            refund_type         ENUM('FULL','PARTIAL','REVERSAL','ADJUSTMENT') NOT NULL DEFAULT 'FULL',
            reason_code         VARCHAR(40)     NOT NULL,
            reason_text         TEXT,
            status              ENUM('PENDING','COMPLETED','FAILED','CANCELLED') NOT NULL DEFAULT 'COMPLETED',
            requested_by        INT,
            approved_by         INT,
            organization_id     INT,
            correlation_id      VARCHAR(64),
            idempotency_key     VARCHAR(128),
            created_at          DATETIME        DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (original_txn_id) REFERENCES transactions(id),
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL,
            FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_refund_txn (original_txn_id),
            INDEX idx_refund_card (card_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Cash session floats (opening / closing) ──────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS session_cash (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            session_id      INT             NOT NULL UNIQUE,
            opening_float   DECIMAL(12,2)   NOT NULL DEFAULT 0,
            cash_received   DECIMAL(12,2)   NOT NULL DEFAULT 0,
            cash_paid_out   DECIMAL(12,2)   NOT NULL DEFAULT 0,
            expected_cash   DECIMAL(12,2),
            actual_cash     DECIMAL(12,2),
            variance        DECIMAL(12,2),
            closed_by       INT,
            supervisor_id   INT,
            notes           TEXT,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES cashier_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Offline sync outbox / inbox ──────────────────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS sync_outbox (
            id              BIGINT          AUTO_INCREMENT PRIMARY KEY,
            event_id        VARCHAR(36)     UNIQUE NOT NULL,
            device_id       INT,
            device_uid      VARCHAR(64),
            sequence_number BIGINT,
            event_type      VARCHAR(64)     NOT NULL,
            payload         JSON            NOT NULL,
            local_timestamp DATETIME(3),
            server_timestamp DATETIME(3),
            sync_status     ENUM('PENDING','SYNCED','FAILED','DEAD') NOT NULL DEFAULT 'PENDING',
            retry_count     INT             NOT NULL DEFAULT 0,
            last_error      TEXT,
            correlation_id  VARCHAR(64),
            idempotency_key VARCHAR(128),
            created_at      DATETIME(3)     DEFAULT CURRENT_TIMESTAMP(3),
            synced_at       DATETIME(3),
            INDEX idx_outbox_status (sync_status, created_at),
            INDEX idx_outbox_device (device_uid, sequence_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS sync_inbox (
            id              BIGINT          AUTO_INCREMENT PRIMARY KEY,
            event_id        VARCHAR(36)     UNIQUE NOT NULL,
            device_uid      VARCHAR(64)     NOT NULL,
            sequence_number BIGINT,
            event_type      VARCHAR(64)     NOT NULL,
            payload         JSON            NOT NULL,
            local_timestamp DATETIME(3),
            received_at     DATETIME(3)     DEFAULT CURRENT_TIMESTAMP(3),
            process_status  ENUM('RECEIVED','PROCESSED','REJECTED','DUPLICATE') NOT NULL DEFAULT 'RECEIVED',
            process_error   TEXT,
            processed_at    DATETIME(3),
            INDEX idx_inbox_device (device_uid, sequence_number),
            INDEX idx_inbox_status (process_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Fare products (config-driven foundation) ─────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS fare_products (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            organization_id INT,
            code            VARCHAR(40)     NOT NULL,
            name            VARCHAR(120)    NOT NULL,
            product_type    ENUM('FLAT','ZONE','DISTANCE','ROUTE','TIME','PASS') NOT NULL DEFAULT 'FLAT',
            media_types     VARCHAR(100)    NOT NULL DEFAULT 'QR,CARD',
            passenger_category VARCHAR(40)  NOT NULL DEFAULT 'STUDENT',
            amount          DECIMAL(12,2),
            currency        CHAR(3)         NOT NULL DEFAULT 'TZS',
            validity_hours  INT,
            priority        INT             NOT NULL DEFAULT 100,
            effective_from  DATE,
            effective_to    DATE,
            active          TINYINT(1)      NOT NULL DEFAULT 1,
            rules_json      JSON,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_org_code (organization_id, code),
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed default student QR product if empty
    $fpCount = (int)$db->query("SELECT COUNT(*) FROM fare_products")->fetchColumn();
    if ($fpCount === 0) {
        $orgId = (int)$db->query("SELECT id FROM organizations WHERE code='DART' LIMIT 1")->fetchColumn();
        $fare = get_config('student_fare', 200);
        $db->prepare("
            INSERT INTO fare_products (organization_id, code, name, product_type, media_types, passenger_category, amount, currency, validity_hours, effective_from)
            VALUES (?, 'STUDENT_FLAT', 'Kigamboni Student Ticket', 'FLAT', 'QR', 'STUDENT', ?, 'TZS', 24, CURDATE())
        ")->execute([$orgId ?: null, $fare]);
    }

    // Device auth token column if missing
    try {
        $c = $db->query("SHOW COLUMNS FROM devices LIKE 'auth_token_hash'")->fetch();
        if (!$c) {
            $db->exec("ALTER TABLE devices ADD COLUMN auth_token_hash VARCHAR(255) DEFAULT NULL AFTER status");
            $db->exec("ALTER TABLE devices ADD COLUMN offline_until DATETIME DEFAULT NULL AFTER last_sync_at");
        }
    } catch (PDOException $e) { /* ignore */ }
}
