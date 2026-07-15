-- TechConnect v2 Phase 5: 2FA, audit log, spam review queue
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER role;
ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret;

CREATE TABLE audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) DEFAULT NULL,
    target_id INT UNSIGNED DEFAULT NULL,
    details VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE spam_flags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('question','answer','comment') NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    score INT NOT NULL DEFAULT 0,
    status ENUM('pending','cleared','removed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
