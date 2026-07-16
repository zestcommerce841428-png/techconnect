ALTER TABLE email_verifications ADD COLUMN new_email VARCHAR(255) DEFAULT NULL AFTER token;
