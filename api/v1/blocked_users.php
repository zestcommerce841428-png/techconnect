<?php
// GET /api/v1/blocked_users.php — users the authenticated key owner has blocked. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare(
    'SELECT u.username, b.created_at FROM user_blocks b JOIN users u ON u.id = b.blocked_id
     WHERE b.blocker_id = ? ORDER BY b.created_at DESC'
);
$stmt->execute([$user['id']]);
api_json(['data' => array_map(fn($b) => [
    'username' => $b['username'], 'blocked_at' => $b['created_at'],
], $stmt->fetchAll())]);
