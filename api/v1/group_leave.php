<?php
// POST /api/v1/group_leave.php — leave a group. Requires API key. Body: slug.
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

$group = db()->prepare('SELECT id, created_by FROM groups_tbl WHERE slug = ?');
$group->execute([$slug]);
$g = $group->fetch();
if (!$g) api_json(['error' => 'Not found'], 404);
if ((int) $g['created_by'] === $user['id']) api_json(['error' => 'The group owner cannot leave their own group'], 403);

db()->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$g['id'], $user['id']]);

api_json(['data' => ['joined' => false]]);
