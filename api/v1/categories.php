<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$rows = db()->query('SELECT name, slug, icon FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
api_json(['data' => $rows]);
