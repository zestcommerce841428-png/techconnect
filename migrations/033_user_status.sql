-- Real account status. The previous "ban" prefixed the password hash, which only
-- blocked password login: passkey, OAuth, Telegram and OTP sign-in all bypassed
-- it, existing sessions stayed alive, and there was no way to undo it.
ALTER TABLE users
    ADD COLUMN status ENUM('active', 'suspended', 'banned') NOT NULL DEFAULT 'active',
    ADD COLUMN status_reason VARCHAR(255) DEFAULT NULL,
    ADD COLUMN status_until DATETIME DEFAULT NULL COMMENT 'suspension expiry; NULL = permanent/none',
    ADD COLUMN status_by INT UNSIGNED DEFAULT NULL,
    ADD COLUMN status_at DATETIME DEFAULT NULL,
    ADD INDEX idx_users_status (status);

-- Repair accounts banned by the old hash-prefix hack: mark them properly banned
-- and restore the hash so unbanning is possible at all.
UPDATE users
SET status = 'banned',
    status_reason = 'Migrated from legacy ban',
    status_at = NOW(),
    password_hash = SUBSTRING(password_hash, 9)
WHERE password_hash LIKE '!banned:%';

-- Free-form moderator notes on an account (kept out of the users row so they are
-- append-only history, not a single overwritable field).
CREATE TABLE IF NOT EXISTS user_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_un_user (user_id, created_at),
    CONSTRAINT fk_un_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_un_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
