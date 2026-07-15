-- Social login (OAuth), Google Analytics, captcha, live chat, WhatsApp, Telegram.

CREATE TABLE oauth_settings (
    provider ENUM('google','facebook','github','microsoft','linkedin','twitter','zoho','telegram') NOT NULL PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    config_encrypted TEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE oauth_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider VARCHAR(20) NOT NULL,
    provider_user_id VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_account (provider, provider_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO oauth_settings (provider, enabled) VALUES
    ('google', 0), ('facebook', 0), ('github', 0), ('microsoft', 0),
    ('linkedin', 0), ('twitter', 0), ('zoho', 0), ('telegram', 0);

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('ga_measurement_id', ''),
    ('captcha_provider', 'none'),
    ('recaptcha_site_key', ''),
    ('recaptcha_secret_key', ''),
    ('turnstile_site_key', ''),
    ('turnstile_secret_key', ''),
    ('tawk_widget_id', ''),
    ('whatsapp_number', ''),
    ('whatsapp_default_message', 'Hi! I have a question about the site.'),
    ('telegram_bot_token', ''),
    ('telegram_notify_chat_id', ''),
    ('telegram_channel_url', ''),
    ('telegram_group_url', ''),
    ('telegram_login_bot_username', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
