<?php
// POST /api/v1/unblock_user.php — unblock a user. Requires API key. Body: username.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 30, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$username = trim($input['username'] ?? '');
if ($username === '') api_json(['error' => 'username is required'], 422);

$pdo = db();
$target = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$target->execute([$username]);
$targetId = $target->fetchColumn();
if (!$targetId) api_json(['error' => 'Not found'], 404);

$pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?')->execute([$user['id'], $targetId]);

api_json(['data' => ['blocked' => false]]);
