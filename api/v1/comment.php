<?php
// POST /api/v1/comment.php — add a comment to a question or answer. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 30, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$parentType = $input['parent_type'] ?? '';
$parentId = (int) ($input['parent_id'] ?? 0);
$body = trim($input['body'] ?? '');

if (!in_array($parentType, ['question', 'answer'], true) || $parentId <= 0) {
    api_json(['error' => 'parent_type (question|answer) and parent_id are required'], 422);
}
if ($body === '' || mb_strlen($body) > 600) api_json(['error' => 'body must be 1-600 characters'], 422);

$pdo = db();
$table = $parentType === 'question' ? 'questions' : 'answers';
$exists = $pdo->prepare("SELECT 1 FROM $table WHERE id = ?");
$exists->execute([$parentId]);
if (!$exists->fetchColumn()) api_json(['error' => 'Parent not found'], 404);

$pdo->prepare('INSERT INTO comments (parent_type, parent_id, user_id, body) VALUES (?, ?, ?, ?)')
    ->execute([$parentType, $parentId, $user['id'], $body]);

api_json(['data' => ['id' => (int) $pdo->lastInsertId()]], 201);
