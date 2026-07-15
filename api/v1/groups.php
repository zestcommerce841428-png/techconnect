<?php
// GET /api/v1/groups.php — list public groups.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    "SELECT g.name, g.slug, g.description, COUNT(gm.user_id) AS member_count
     FROM groups_tbl g LEFT JOIN group_members gm ON gm.group_id = g.id
     WHERE g.is_private = 0 GROUP BY g.id ORDER BY member_count DESC LIMIT 100"
);
api_json(['data' => array_map(fn($g) => [
    'name' => $g['name'], 'slug' => $g['slug'], 'description' => $g['description'],
    'member_count' => (int) $g['member_count'],
], $stmt->fetchAll())]);
