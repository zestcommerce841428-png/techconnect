-- Question closing + bounties, reputation ledger, announcements,
-- email verification, polls. (Comments already exist from v1 schema.)

ALTER TABLE questions
    ADD COLUMN closed_reason VARCHAR(255) DEFAULT NULL,
    ADD COLUMN closed_by INT UNSIGNED DEFAULT NULL,
    ADD COLUMN bounty_points INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE reputation_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(60) NOT NULL,
    ref_type VARCHAR(20) DEFAULT NULL,
    ref_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(300) NOT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    style ENUM('info','success','warning') NOT NULL DEFAULT 'info',
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users ADD COLUMN email_verified_at DATETIME DEFAULT NULL;

CREATE TABLE email_verifications (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE poll_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    label VARCHAR(150) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE poll_votes (
    option_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (question_id, user_id),
    FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
