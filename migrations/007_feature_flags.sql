INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('max_upload_mb', '5'),
    ('allow_registrations', '1'),
    ('maintenance_mode', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
