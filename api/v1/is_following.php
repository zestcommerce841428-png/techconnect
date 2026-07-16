<?php
// GET /api/v1/is_following.php?followable_type=<user|tag|question>&followable=<identifier>
// Whether the authenticated key owner follows the given target. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 120, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$type = $_GET['followable_type'] ?? '';
$identifier = trim($_GET['followable'] ?? '');
if (!in_array($type, ['user', 'tag', 'question'], true) || $identifier === '') {
    api_json(['error' => 'followable_type (user|tag|question) and followable are required'], 422);
}

$pdo = db();
if ($type === 'user') {
    $row = $pdo->prepare('SELECT id FROM users WHERE username = ?');
} elseif ($type === 'tag') {
    $identifier = strtolower($identifier);
    $row = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
} else {
    $row = $pdo->prepare('SELECT id FROM questions WHERE slug = ?');
}
$row->execute([$identifier]);
$targetId = $row->fetchColumn();
if (!$targetId) api_json(['error' => 'Not found'], 404);

$check = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followable_type = ? AND followable_id = ?');
$check->execute([$user['id'], $type, $targetId]);

api_json(['data' => ['following' => (bool) $check->fetchColumn()]]);
