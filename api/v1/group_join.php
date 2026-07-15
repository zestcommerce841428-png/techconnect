<?php
// POST /api/v1/group_join.php — join a public group. Requires API key. Body: slug.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 30, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$slug = trim($input['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$group = db()->prepare('SELECT id FROM groups_tbl WHERE slug = ? AND is_private = 0');
$group->execute([$slug]);
$groupId = $group->fetchColumn();
if (!$groupId) api_json(['error' => 'Not found'], 404);

db()->prepare('INSERT IGNORE INTO group_members (group_id, user_id, role) VALUES (?, ?, \'member\')')
    ->execute([$groupId, $user['id']]);

api_json(['data' => ['joined' => true]]);
