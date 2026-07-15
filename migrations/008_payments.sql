-- Phase 4: monetization scaffolding (gateway-agnostic; real charges require live API keys
-- entered via admin/payment_settings.php, never hardcoded).

CREATE TABLE payment_settings (
    gateway ENUM('razorpay','stripe','paypal') NOT NULL PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    mode ENUM('test','live') NOT NULL DEFAULT 'test',
    config_encrypted TEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_type ENUM('job_listing','pro_membership','consultation') NOT NULL,
    item_id INT UNSIGNED DEFAULT NULL,
    gateway ENUM('razorpay','stripe','paypal') NOT NULL,
    gateway_order_id VARCHAR(191) DEFAULT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (item_type, item_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(40) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan VARCHAR(60) NOT NULL DEFAULT 'pro_monthly',
    gateway ENUM('razorpay','stripe','paypal') NOT NULL,
    gateway_subscription_id VARCHAR(191) DEFAULT NULL,
    status ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
    current_period_end DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expert_profiles (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    headline VARCHAR(150) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    hourly_rate_cents INT UNSIGNED DEFAULT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE consultations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expert_id INT UNSIGNED NOT NULL,
    requester_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED DEFAULT NULL,
    message TEXT DEFAULT NULL,
    status ENUM('requested','confirmed','completed','cancelled') NOT NULL DEFAULT 'requested',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expert_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ad_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_key VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    html_content MEDIUMTEXT DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE affiliate_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL,
    target_url VARCHAR(500) NOT NULL,
    slug VARCHAR(40) NOT NULL UNIQUE,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    conversions INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE affiliate_clicks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    affiliate_link_id INT UNSIGNED NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_link_id) REFERENCES affiliate_links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    ADD COLUMN is_pro TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN pro_expires_at DATETIME DEFAULT NULL;

INSERT INTO payment_settings (gateway, enabled, mode) VALUES
    ('razorpay', 0, 'test'), ('stripe', 0, 'test'), ('paypal', 0, 'test');

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('job_listing_price_cents', '2000'),
    ('pro_membership_price_cents', '500'),
    ('currency', 'USD')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
