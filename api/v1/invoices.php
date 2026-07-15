<?php
// GET /api/v1/invoices.php — the authenticated key owner's own invoices. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare(
    'SELECT i.invoice_number, i.created_at, o.amount_cents, o.currency, o.item_type
     FROM invoices i JOIN orders o ON o.id = i.order_id
     WHERE o.user_id = ? ORDER BY i.created_at DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
api_json(['data' => array_map(fn($i) => [
    'invoice_number' => $i['invoice_number'], 'item_type' => $i['item_type'],
    'amount_cents' => (int) $i['amount_cents'], 'currency' => $i['currency'], 'created_at' => $i['created_at'],
], $stmt->fetchAll())]);
