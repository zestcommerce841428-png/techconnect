<?php
// GET /api/v1/comments.php?parent_type=question|answer&parent_id=<id> — list comments.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$parentType = $_GET['parent_type'] ?? '';
$parentId = (int) ($_GET['parent_id'] ?? 0);
if (!in_array($parentType, ['question', 'answer'], true) || $parentId <= 0) {
    api_json(['error' => 'parent_type (question|answer) and parent_id are required'], 422);
}

$stmt = db()->prepare(
    'SELECT c.body, c.created_at, u.username FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.parent_type = ? AND c.parent_id = ? ORDER BY c.created_at ASC'
);
$stmt->execute([$parentType, $parentId]);

api_json(['data' => array_map(fn($c) => [
    'body' => $c['body'], 'author' => $c['username'], 'created_at' => $c['created_at'],
], $stmt->fetchAll())]);
