<?php
// GET /api/v1/collections.php?username=<username> — public collections for a user.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$username = trim($_GET['username'] ?? '');
if ($username === '') api_json(['error' => 'username is required'], 422);

$stmt = db()->prepare(
    'SELECT c.name, c.slug, c.description, COUNT(ci.id) AS item_count FROM collections c
     JOIN users u ON u.id = c.user_id LEFT JOIN collection_items ci ON ci.collection_id = c.id
     WHERE u.username = ? AND c.is_public = 1 GROUP BY c.id ORDER BY c.created_at DESC'
);
$stmt->execute([$username]);
api_json(['data' => array_map(fn($c) => [
    'name' => $c['name'], 'slug' => $c['slug'], 'description' => $c['description'],
    'item_count' => (int) $c['item_count'],
], $stmt->fetchAll())]);
