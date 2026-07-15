<?php
// GET /api/v1/audit_log.php — recent admin action log. Requires an admin (not moderator) API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if ($user['role'] !== 'admin') api_json(['error' => 'Admin access required'], 403);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    'SELECT a.action, a.target_type, a.target_id, a.details, a.created_at, u.username AS admin
     FROM audit_log a JOIN users u ON u.id = a.admin_id ORDER BY a.created_at DESC LIMIT 100'
);
api_json(['data' => array_map(fn($a) => [
    'action' => $a['action'], 'target_type' => $a['target_type'], 'target_id' => $a['target_id'] !== null ? (int) $a['target_id'] : null,
    'details' => $a['details'], 'admin' => $a['admin'], 'created_at' => $a['created_at'],
], $stmt->fetchAll())]);
