<?php
// POST /api/v1/unfollow.php — unfollow a user or tag. Body: followable_type (user|tag), followable.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 60, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$type = $input['followable_type'] ?? '';
$identifier = trim($input['followable'] ?? '');
if (!in_array($type, ['user', 'tag'], true) || $identifier === '') {
    api_json(['error' => 'followable_type (user|tag) and followable are required'], 422);
}

$pdo = db();
if ($type === 'user') {
    $row = $pdo->prepare('SELECT id FROM users WHERE username = ?');
} else {
    $identifier = strtolower($identifier);
    $row = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
}
$row->execute([$identifier]);
$targetId = $row->fetchColumn();
if (!$targetId) api_json(['error' => 'Not found'], 404);

$pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND followable_type = ? AND followable_id = ?')
    ->execute([$user['id'], $type, $targetId]);

api_json(['data' => ['following' => false]]);
