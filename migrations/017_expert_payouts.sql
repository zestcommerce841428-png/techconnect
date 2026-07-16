ALTER TABLE consultations ADD COLUMN quoted_amount_cents INT UNSIGNED DEFAULT NULL AFTER message;
ALTER TABLE consultations ADD COLUMN scheduled_at DATETIME DEFAULT NULL AFTER quoted_amount_cents;

CREATE TABLE expert_payouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expert_id INT UNSIGNED NOT NULL,
    consultation_id INT UNSIGNED NOT NULL,
    gross_amount_cents INT UNSIGNED NOT NULL,
    platform_fee_cents INT UNSIGNED NOT NULL,
    net_amount_cents INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    payout_reference VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_consultation_payout (consultation_id),
    FOREIGN KEY (expert_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO site_settings (setting_key, setting_value) VALUES ('expert_platform_fee_pct', '15')
    ON DUPLICATE KEY UPDATE setting_key = setting_key;
