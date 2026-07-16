-- Feedback / bug report system
CREATE TABLE IF NOT EXISTS feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    email VARCHAR(255) NULL,
    type ENUM('bug', 'feature', 'ui', 'performance', 'other') NOT NULL DEFAULT 'other',
    severity ENUM('low', 'medium', 'high', 'critical') NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    page_url VARCHAR(500) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('new', 'in_review', 'planned', 'resolved', 'dismissed') NOT NULL DEFAULT 'new',
    admin_note TEXT NULL,
    handled_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_status (status),
    KEY idx_feedback_type (type),
    KEY idx_feedback_user (user_id),
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_feedback_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
