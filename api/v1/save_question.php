<?php
// POST /api/v1/save_question.php — bookmark/unbookmark a question (toggle). Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 60, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$slug = trim($input['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$pdo = db();
$q = $pdo->prepare('SELECT id FROM questions WHERE slug = ?');
$q->execute([$slug]);
$questionId = $q->fetchColumn();
if (!$questionId) api_json(['error' => 'Not found'], 404);

$existing = $pdo->prepare('SELECT id FROM saved_questions WHERE user_id = ? AND question_id = ?');
$existing->execute([$user['id'], $questionId]);
if ($existing->fetchColumn()) {
    $pdo->prepare('DELETE FROM saved_questions WHERE user_id = ? AND question_id = ?')->execute([$user['id'], $questionId]);
    api_json(['data' => ['saved' => false]]);
}
$pdo->prepare('INSERT INTO saved_questions (user_id, question_id) VALUES (?, ?)')->execute([$user['id'], $questionId]);
api_json(['data' => ['saved' => true]]);
