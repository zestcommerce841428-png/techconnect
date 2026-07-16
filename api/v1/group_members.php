<?php
// GET /api/v1/group_members.php?slug=<group-slug> — members of a public group.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$group = db()->prepare('SELECT id FROM groups_tbl WHERE slug = ? AND is_private = 0');
$group->execute([$slug]);
$groupId = $group->fetchColumn();
if (!$groupId) api_json(['error' => 'Not found'], 404);

$stmt = db()->prepare(
    'SELECT u.username, gm.role, gm.joined_at FROM group_members gm
     JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ? ORDER BY gm.joined_at ASC'
);
$stmt->execute([$groupId]);
api_json(['data' => array_map(fn($m) => [
    'username' => $m['username'], 'role' => $m['role'], 'joined_at' => $m['joined_at'],
], $stmt->fetchAll())]);
