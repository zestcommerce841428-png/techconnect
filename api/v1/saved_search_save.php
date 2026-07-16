<?php
// POST /api/v1/saved_search_save.php — save a search. Requires API key. Body: label, query.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 30, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$label = trim($input['label'] ?? '');
$query = trim($input['query'] ?? '');
if ($label === '' || mb_strlen($label) > 120) api_json(['error' => 'label must be 1-120 characters'], 422);
if ($query === '' || mb_strlen($query) > 500) api_json(['error' => 'query must be 1-500 characters'], 422);

$pdo = db();
$pdo->prepare('INSERT INTO saved_searches (user_id, label, query_string) VALUES (?, ?, ?)')
    ->execute([$user['id'], $label, $query]);

api_json(['data' => ['id' => (int) $pdo->lastInsertId()]], 201);
