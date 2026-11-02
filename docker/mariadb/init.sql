-- Klearcom schema (manual migrations per Klearcom practice)
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)
);

CREATE TABLE IF NOT EXISTS discovery_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    country_code VARCHAR(5) NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    menu_depth INT DEFAULT 0,
    nodes_discovered INT DEFAULT 0,
    languages JSON NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS discovery_nodes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discovery_job_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    prompt_text TEXT,
    dtmf_option VARCHAR(10) NULL,
    node_type ENUM('menu', 'prompt', 'transfer', 'hangup') DEFAULT 'menu',
    depth INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (discovery_job_id) REFERENCES discovery_jobs(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_discovery_nodes_job ON discovery_nodes(discovery_job_id);

CREATE TABLE IF NOT EXISTS connect_monitors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    toll_free_number VARCHAR(50) NOT NULL,
    country_code VARCHAR(5) NOT NULL,
    carrier VARCHAR(100) NULL,
    status ENUM('active', 'paused', 'alert') DEFAULT 'active',
    reachability_pct DECIMAL(5,2) DEFAULT 100.00,
    last_checked_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS connect_check_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connect_monitor_id BIGINT UNSIGNED NOT NULL,
    reachable TINYINT(1) NOT NULL,
    latency_ms INT NULL,
    carrier_route VARCHAR(255) NULL,
    failure_reason VARCHAR(255) NULL,
    checked_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (connect_monitor_id) REFERENCES connect_monitors(id) ON DELETE CASCADE
);

INSERT INTO users (name, email, password, created_at, updated_at) VALUES
('Ops Admin', 'admin@klearcom.local', '$2y$12$placeholderHashReplaceBeforeDeployment', NOW(), NOW());

INSERT INTO discovery_jobs (name, phone_number, country_code, status, menu_depth, nodes_discovered, languages, started_at, completed_at, created_at, updated_at) VALUES
('Bank IVR Discovery - US', '+18005551234', 'US', 'completed', 4, 12, '["en"]', NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 1 HOUR, NOW(), NOW()),
('Healthcare IVR - UK', '+448001234567', 'GB', 'running', 2, 5, '["en"]', NOW() - INTERVAL 30 MINUTE, NULL, NOW(), NOW()),
('Retail Multi-lang IVR', '+33123456789', 'FR', 'pending', 0, 0, '["fr","en"]', NULL, NULL, NOW(), NOW());

INSERT INTO discovery_nodes (discovery_job_id, parent_id, prompt_text, dtmf_option, node_type, depth, created_at, updated_at) VALUES
(1, NULL, 'Welcome to Acme Bank. Press 1 for accounts, 2 for loans, 3 for support.', NULL, 'menu', 0, NOW(), NOW()),
(1, 1, 'Accounts menu. Press 1 for balance, 2 for transactions.', '1', 'menu', 1, NOW(), NOW()),
(1, 2, 'Your balance is being retrieved. Please hold.', '1', 'prompt', 2, NOW(), NOW()),
(1, 1, 'Transferring to loans department.', '2', 'transfer', 1, NOW(), NOW());

INSERT INTO connect_monitors (name, toll_free_number, country_code, carrier, status, reachability_pct, last_checked_at, created_at, updated_at) VALUES
('US Sales TFN', '18005559999', 'US', 'Verizon', 'active', 99.80, NOW() - INTERVAL 5 MINUTE, NOW(), NOW()),
('India Support Line', '180018001800', 'IN', 'Airtel', 'alert', 72.50, NOW() - INTERVAL 10 MINUTE, NOW(), NOW()),
('Brazil Customer Care', '08001234567', 'BR', 'Vivo', 'active', 98.20, NOW() - INTERVAL 15 MINUTE, NOW(), NOW());

INSERT INTO connect_check_results (connect_monitor_id, reachable, latency_ms, carrier_route, failure_reason, checked_at, created_at, updated_at) VALUES
(1, 1, 245, 'US-East -> Verizon SIP', NULL, NOW() - INTERVAL 5 MINUTE, NOW(), NOW()),
(2, 0, NULL, 'Mumbai -> Airtel', 'Carrier routing failure', NOW() - INTERVAL 10 MINUTE, NOW(), NOW()),
(3, 1, 380, 'Sao Paulo -> Vivo', NULL, NOW() - INTERVAL 15 MINUTE, NOW(), NOW());
