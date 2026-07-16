<?php
// GET /api/v1/site_info.php — public, non-sensitive site settings for building third-party clients.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

api_json(['data' => [
    'name' => setting('site_name', SITE_NAME),
    'tagline' => setting('tagline', ''),
    'url' => SITE_URL,
    'base_currency' => setting('currency', 'USD'),
    'newsletter_enabled' => setting('newsletter_enabled', '1') === '1',
]]);
