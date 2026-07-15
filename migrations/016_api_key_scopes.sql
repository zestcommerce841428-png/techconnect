ALTER TABLE api_keys ADD COLUMN scope ENUM('read_only','full_access') NOT NULL DEFAULT 'full_access' AFTER label;
