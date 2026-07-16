<?php
// GET /api/v1/group.php?slug=<slug> — single public group.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$stmt = db()->prepare('SELECT id, name, slug, description, is_private, created_at FROM groups_tbl WHERE slug = ?');
$stmt->execute([$slug]);
$g = $stmt->fetch();
if (!$g || $g['is_private']) api_json(['error' => 'Not found'], 404);

$count = db()->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
$count->execute([$g['id']]);

api_json(['data' => [
    'name' => $g['name'], 'slug' => $g['slug'], 'description' => $g['description'],
    'member_count' => (int) $count->fetchColumn(), 'created_at' => $g['created_at'],
]]);
