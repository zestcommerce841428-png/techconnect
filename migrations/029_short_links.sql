-- Short link sharing: puchonow.in/s/<code> -> any internal path.
CREATE TABLE IF NOT EXISTS short_links (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(12) NOT NULL,
    target_path VARCHAR(500) NOT NULL,
    created_by INT UNSIGNED NULL,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    last_clicked_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_short_code (code),
    KEY idx_short_target (target_path),
    CONSTRAINT fk_short_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
