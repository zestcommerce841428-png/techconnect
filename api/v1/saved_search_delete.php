<?php
// POST /api/v1/saved_search_delete.php — delete a saved search you own. Requires API key. Body: id.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 30, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int) ($input['id'] ?? 0);
if ($id <= 0) api_json(['error' => 'id is required'], 422);

$pdo = db();
$stmt = $pdo->prepare('DELETE FROM saved_searches WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
if ($stmt->rowCount() === 0) api_json(['error' => 'Not found'], 404);

api_json(['data' => ['deleted' => true]]);
