<?php
/**
 * Stage 3: transport network, fare rules depth, settlement, offline policies.
 */
function init_schema_stage3(PDO $db): void {

    // ── Transport network (minimal multi-station model) ──────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS stations (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            organization_id INT,
            code            VARCHAR(40)     NOT NULL,
            name            VARCHAR(150)    NOT NULL,
            station_type    ENUM('terminal','station','stop','depot','hub') NOT NULL DEFAULT 'terminal',
            zone_code       VARCHAR(40),
            active          TINYINT(1)      NOT NULL DEFAULT 1,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_org_station (organization_id, code),
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS routes (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            organization_id INT,
            code            VARCHAR(40)     NOT NULL,
            name            VARCHAR(150)    NOT NULL,
            direction       VARCHAR(40),
            active          TINYINT(1)      NOT NULL DEFAULT 1,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_org_route (organization_id, code),
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS route_stops (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            route_id    INT NOT NULL,
            station_id  INT NOT NULL,
            sequence_no INT NOT NULL DEFAULT 0,
            FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
            FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
            UNIQUE KEY uq_route_seq (route_id, sequence_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Settlement batches ───────────────────────────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS settlement_batches (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            batch_uid       VARCHAR(36)     UNIQUE NOT NULL,
            organization_id INT,
            period_start    DATETIME        NOT NULL,
            period_end      DATETIME        NOT NULL,
            status          ENUM('OPEN','CALCULATING','COMPLETED','DISPUTED','CLOSED') NOT NULL DEFAULT 'OPEN',
            total_revenue   DECIMAL(14,2)   NOT NULL DEFAULT 0,
            total_refunds   DECIMAL(14,2)   NOT NULL DEFAULT 0,
            total_net       DECIMAL(14,2)   NOT NULL DEFAULT 0,
            txn_count       INT             NOT NULL DEFAULT 0,
            notes           TEXT,
            created_by      INT,
            closed_by       INT,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            closed_at       DATETIME,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS settlement_lines (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            batch_id        INT             NOT NULL,
            line_type       VARCHAR(40)     NOT NULL,
            reference       VARCHAR(100),
            amount          DECIMAL(14,2)   NOT NULL DEFAULT 0,
            quantity        INT             NOT NULL DEFAULT 0,
            meta_json       JSON,
            FOREIGN KEY (batch_id) REFERENCES settlement_batches(id) ON DELETE CASCADE,
            INDEX idx_batch (batch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Reconciliation runs ──────────────────────────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS reconciliation_runs (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            run_uid         VARCHAR(36)     UNIQUE NOT NULL,
            organization_id INT,
            period_start    DATETIME        NOT NULL,
            period_end      DATETIME        NOT NULL,
            status          ENUM('RUNNING','COMPLETED','FAILED') NOT NULL DEFAULT 'RUNNING',
            cash_expected   DECIMAL(14,2)   DEFAULT 0,
            cash_actual     DECIMAL(14,2)   DEFAULT 0,
            cash_variance   DECIMAL(14,2)   DEFAULT 0,
            card_topups     DECIMAL(14,2)   DEFAULT 0,
            qr_sales        DECIMAL(14,2)   DEFAULT 0,
            refunds_total   DECIMAL(14,2)   DEFAULT 0,
            ledger_net      DECIMAL(14,2)   DEFAULT 0,
            issues_json     JSON,
            created_by      INT,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            completed_at    DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Offline policies (what a device may do offline) ──────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS offline_policies (
            id              INT             AUTO_INCREMENT PRIMARY KEY,
            organization_id INT,
            device_type     VARCHAR(40)     NOT NULL,
            policy_key      VARCHAR(80)     NOT NULL,
            policy_value    TEXT,
            value_type      ENUM('string','number','boolean','json') NOT NULL DEFAULT 'string',
            description     VARCHAR(255),
            UNIQUE KEY uq_org_dev_key (organization_id, device_type, policy_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed stations / routes for default org if empty
    $orgId = (int)$db->query("SELECT id FROM organizations WHERE code='DART' LIMIT 1")->fetchColumn();
    if ($orgId) {
        $sc = (int)$db->query("SELECT COUNT(*) FROM stations")->fetchColumn();
        if ($sc === 0) {
            $db->prepare("INSERT INTO stations (organization_id, code, name, station_type, zone_code) VALUES (?,?,?,?,?)")
               ->execute([$orgId, 'KIGAMBONI', 'Kigamboni Terminal', 'terminal', 'Z1']);
            $db->prepare("INSERT INTO stations (organization_id, code, name, station_type, zone_code) VALUES (?,?,?,?,?)")
               ->execute([$orgId, 'CBD', 'City Centre', 'station', 'Z1']);
            $db->prepare("INSERT INTO stations (organization_id, code, name, station_type, zone_code) VALUES (?,?,?,?,?)")
               ->execute([$orgId, 'MWENGE', 'Mwenge', 'station', 'Z2']);
        }
        $rc = (int)$db->query("SELECT COUNT(*) FROM routes")->fetchColumn();
        if ($rc === 0) {
            $db->prepare("INSERT INTO routes (organization_id, code, name, direction) VALUES (?,?,?,?)")
               ->execute([$orgId, 'KG-ALL', 'Kigamboni — All Destinations', 'OUTBOUND']);
        }

        $pc = (int)$db->query("SELECT COUNT(*) FROM offline_policies")->fetchColumn();
        if ($pc === 0) {
            $policies = [
                ['cashier_terminal', 'allow_topup', 'true', 'boolean', 'Allow card top-ups while offline'],
                ['cashier_terminal', 'allow_qr_sale', 'true', 'boolean', 'Allow QR ticket sales while offline'],
                ['cashier_terminal', 'allow_card_issue', 'true', 'boolean', 'Allow card registration while offline'],
                ['cashier_terminal', 'max_offline_topup', '50000', 'number', 'Max single offline top-up'],
                ['cashier_terminal', 'offline_auth_ttl_hours', '72', 'number', 'Hours until offline auth expires'],
                ['gate_validator', 'allow_validate_qr', 'true', 'boolean', 'Allow offline QR validation'],
                ['gate_validator', 'allow_validate_card', 'true', 'boolean', 'Allow offline card validation'],
                ['gate_validator', 'offline_auth_ttl_hours', '48', 'number', 'Hours until gate offline auth expires'],
                ['inspector_mobile', 'allow_validate_qr', 'true', 'boolean', 'Inspector offline QR check'],
                ['inspector_mobile', 'offline_auth_ttl_hours', '24', 'number', 'Inspector offline TTL'],
            ];
            $ins = $db->prepare("INSERT INTO offline_policies (organization_id, device_type, policy_key, policy_value, value_type, description) VALUES (?,?,?,?,?,?)");
            foreach ($policies as $p) {
                $ins->execute([$orgId, $p[0], $p[1], $p[2], $p[3], $p[4]]);
            }
        }
    }

    // Extra fare product columns if needed
    try {
        $c = $db->query("SHOW COLUMNS FROM fare_products LIKE 'route_code'")->fetch();
        if (!$c) {
            $db->exec("ALTER TABLE fare_products ADD COLUMN route_code VARCHAR(40) DEFAULT NULL AFTER passenger_category");
            $db->exec("ALTER TABLE fare_products ADD COLUMN zone_code VARCHAR(40) DEFAULT NULL AFTER route_code");
        }
    } catch (PDOException $e) { /* ignore */ }
}
