ALTER TABLE questions ADD COLUMN bounty_expires_at DATETIME DEFAULT NULL AFTER bounty_points;
