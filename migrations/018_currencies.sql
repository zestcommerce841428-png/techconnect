CREATE TABLE currencies (
    code CHAR(3) PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    symbol VARCHAR(8) NOT NULL,
    rate_to_base DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- rate_to_base = how many units of this currency equal 1 unit of the site's base currency (site_settings.currency).
INSERT INTO currencies (code, name, symbol, rate_to_base) VALUES
    ('USD', 'US Dollar', '$', 1.000000),
    ('EUR', 'Euro', '€', 0.920000),
    ('GBP', 'British Pound', '£', 0.790000),
    ('INR', 'Indian Rupee', '₹', 83.000000),
    ('AUD', 'Australian Dollar', 'A$', 1.520000),
    ('CAD', 'Canadian Dollar', 'C$', 1.360000),
    ('JPY', 'Japanese Yen', '¥', 149.000000)
ON DUPLICATE KEY UPDATE name = VALUES(name);

ALTER TABLE users ADD COLUMN display_currency CHAR(3) DEFAULT NULL AFTER role;
