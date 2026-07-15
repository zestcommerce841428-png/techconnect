<?php
// GET /api/v1/reports.php — open user reports queue. Requires an admin/moderator API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!in_array($user['role'], ['admin', 'moderator'], true)) api_json(['error' => 'Moderator access required'], 403);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    "SELECT r.id, r.target_type, r.target_id, r.reason, r.status, r.created_at, u.username AS reporter
     FROM reports r JOIN users u ON u.id = r.reporter_id
     WHERE r.status = 'open' ORDER BY r.created_at DESC LIMIT 100"
);
api_json(['data' => array_map(fn($r) => [
    'id' => (int) $r['id'], 'target_type' => $r['target_type'], 'target_id' => (int) $r['target_id'],
    'reason' => $r['reason'], 'reporter' => $r['reporter'], 'created_at' => $r['created_at'],
], $stmt->fetchAll())]);
