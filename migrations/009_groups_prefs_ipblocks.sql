-- Groups, notification preferences, IP blocking.

CREATE TABLE groups_tbl (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_members (
    group_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('member','moderator','owner') NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES groups_tbl(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE questions ADD COLUMN group_id INT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT fk_questions_group FOREIGN KEY (group_id) REFERENCES groups_tbl(id) ON DELETE SET NULL,
    ADD INDEX idx_questions_group (group_id);

CREATE TABLE notification_prefs (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    email_on_answer TINYINT(1) NOT NULL DEFAULT 1,
    email_on_mention TINYINT(1) NOT NULL DEFAULT 1,
    email_on_message TINYINT(1) NOT NULL DEFAULT 1,
    email_tag_digest TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ip_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255) DEFAULT NULL,
    blocked_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
