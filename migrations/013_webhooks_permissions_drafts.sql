-- Outgoing webhooks, granular moderator permissions, question drafts,
-- scheduled blog publishing, suggested edits.

CREATE TABLE webhooks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(64) NOT NULL,
    events SET('question.created','answer.created','user.registered','order.paid') NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webhook_deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT UNSIGNED NOT NULL,
    event VARCHAR(40) NOT NULL,
    response_code SMALLINT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_permissions (
    user_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(40) NOT NULL,
    granted_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE questions MODIFY COLUMN status ENUM('draft','open','answered','closed') NOT NULL DEFAULT 'open';
ALTER TABLE blog_posts ADD COLUMN publish_at DATETIME DEFAULT NULL;

CREATE TABLE suggested_edits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('question','answer') NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    proposer_id INT UNSIGNED NOT NULL,
    proposed_title VARCHAR(200) DEFAULT NULL,
    proposed_body MEDIUMTEXT NOT NULL,
    edit_summary VARCHAR(300) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (proposer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
