-- Brute-force protection + security monitoring.
-- Failed logins were only written to the file log, which cannot be queried, so
-- lockout, detection and the security dashboard were all impossible.
CREATE TABLE IF NOT EXISTS failed_logins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    identity VARCHAR(255) NOT NULL,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    reason ENUM('bad_password', 'unknown_user', 'bad_2fa', 'bad_otp', 'locked') NOT NULL DEFAULT 'bad_password',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fl_identity_time (identity, created_at),
    KEY idx_fl_ip_time (ip_address, created_at),
    KEY idx_fl_user (user_id),
    CONSTRAINT fk_fl_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temporary account lock applied after repeated failures.
ALTER TABLE users
    ADD COLUMN locked_until DATETIME DEFAULT NULL,
    ADD INDEX idx_users_locked (locked_until);
