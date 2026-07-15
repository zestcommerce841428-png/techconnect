<?php
// GET /api/v1/webhooks.php — list configured webhooks (secret excluded). Requires an admin API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if ($user['role'] !== 'admin') api_json(['error' => 'Admin access required'], 403);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query('SELECT id, label, url, events, enabled, created_at FROM webhooks ORDER BY created_at DESC');
api_json(['data' => array_map(fn($w) => [
    'id' => (int) $w['id'], 'label' => $w['label'], 'url' => $w['url'],
    'events' => explode(',', $w['events']), 'enabled' => (bool) $w['enabled'], 'created_at' => $w['created_at'],
], $stmt->fetchAll())]);
