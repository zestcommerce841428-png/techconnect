<?php
// GET /api/v1/notifications.php — the authenticated key owner's notifications. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare('SELECT type, data, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['id']]);
api_json(['data' => array_map(fn($n) => [
    'type' => $n['type'], 'data' => json_decode($n['data'], true), 'is_read' => (bool) $n['is_read'],
    'created_at' => $n['created_at'],
], $stmt->fetchAll())]);
