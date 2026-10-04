<?php
function init_schema_stage4(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS anomaly_events (
            id              BIGINT          AUTO_INCREMENT PRIMARY KEY,
            event_uid       VARCHAR(36)     UNIQUE NOT NULL,
            organization_id INT,
            anomaly_type    VARCHAR(64)     NOT NULL,
            severity        ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
            score           DECIMAL(5,2)    NOT NULL DEFAULT 0,
            entity_type     VARCHAR(40),
            entity_id       VARCHAR(64),
            actor_user_id   INT,
            device_id       INT,
            evidence_json   JSON,
            status          ENUM('OPEN','INVESTIGATING','RESOLVED','DISMISSED') NOT NULL DEFAULT 'OPEN',
            reviewer_id     INT,
            review_notes    TEXT,
            created_at      DATETIME(3)     DEFAULT CURRENT_TIMESTAMP(3),
            resolved_at     DATETIME(3),
            INDEX idx_anom_status (status, created_at),
            INDEX idx_anom_type (anomaly_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // MFA / security flags on users
    foreach ([
        'mfa_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
        'mfa_secret'  => "VARCHAR(64) DEFAULT NULL",
        'mfa_enrolled_at' => "DATETIME DEFAULT NULL",
    ] as $col => $def) {
        try {
            $c = $db->query("SHOW COLUMNS FROM users LIKE '$col'")->fetch();
            if (!$c) $db->exec("ALTER TABLE users ADD COLUMN $col $def");
        } catch (PDOException $e) { /* ignore */ }
    }
}
