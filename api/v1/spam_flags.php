<?php
// GET /api/v1/spam_flags.php — pending automated spam flags. Requires an admin/moderator API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!in_array($user['role'], ['admin', 'moderator'], true)) api_json(['error' => 'Moderator access required'], 403);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    "SELECT id, target_type, target_id, reason, score, created_at FROM spam_flags
     WHERE status = 'pending' ORDER BY score DESC, created_at DESC LIMIT 100"
);
api_json(['data' => array_map(fn($f) => [
    'id' => (int) $f['id'], 'target_type' => $f['target_type'], 'target_id' => (int) $f['target_id'],
    'reason' => $f['reason'], 'score' => (int) $f['score'], 'created_at' => $f['created_at'],
], $stmt->fetchAll())]);
