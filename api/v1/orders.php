<?php
// GET /api/v1/orders.php — the authenticated key owner's own orders. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare(
    'SELECT id, item_type, item_id, gateway, amount_cents, currency, status, created_at, paid_at
     FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
api_json(['data' => array_map(fn($o) => [
    'id' => (int) $o['id'], 'item_type' => $o['item_type'], 'item_id' => $o['item_id'] !== null ? (int) $o['item_id'] : null,
    'gateway' => $o['gateway'], 'amount_cents' => (int) $o['amount_cents'], 'currency' => $o['currency'],
    'status' => $o['status'], 'created_at' => $o['created_at'], 'paid_at' => $o['paid_at'],
], $stmt->fetchAll())]);
