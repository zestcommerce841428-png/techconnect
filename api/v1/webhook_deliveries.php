<?php
// GET /api/v1/webhook_deliveries.php?webhook_id=<id> — delivery log for one webhook. Requires an admin API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if ($user['role'] !== 'admin') api_json(['error' => 'Admin access required'], 403);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$webhookId = (int) ($_GET['webhook_id'] ?? 0);
if ($webhookId <= 0) api_json(['error' => 'webhook_id is required'], 422);

$stmt = db()->prepare(
    'SELECT event, response_code, success, created_at FROM webhook_deliveries
     WHERE webhook_id = ? ORDER BY created_at DESC LIMIT 100'
);
$stmt->execute([$webhookId]);
api_json(['data' => array_map(fn($d) => [
    'event' => $d['event'], 'response_code' => $d['response_code'] !== null ? (int) $d['response_code'] : null,
    'success' => (bool) $d['success'], 'created_at' => $d['created_at'],
], $stmt->fetchAll())]);
