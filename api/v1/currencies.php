<?php
// GET /api/v1/currencies.php — supported currencies and exchange rates.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query('SELECT code, name, symbol, rate_to_base FROM currencies WHERE is_enabled = 1 ORDER BY code');
api_json(['data' => array_map(fn($c) => [
    'code' => $c['code'], 'name' => $c['name'], 'symbol' => $c['symbol'],
    'rate_to_base' => (float) $c['rate_to_base'],
], $stmt->fetchAll())]);
