<?php
// POST /api/v1/answer.php — post an answer to a question. Requires API key (full_access).
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 20, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$slug = trim($input['slug'] ?? '');
$body = trim($input['body'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);
if (mb_strlen($body) < 10) api_json(['error' => 'body must be at least 10 characters'], 422);

$pdo = db();
$q = $pdo->prepare("SELECT id FROM questions WHERE slug = ? AND status != 'draft'");
$q->execute([$slug]);
$questionId = $q->fetchColumn();
if (!$questionId) api_json(['error' => 'Question not found'], 404);

$pdo->prepare('INSERT INTO answers (question_id, user_id, body) VALUES (?, ?, ?)')
    ->execute([$questionId, $user['id'], $body]);
$answerId = (int) $pdo->lastInsertId();
$pdo->prepare('UPDATE questions SET answer_count = answer_count + 1 WHERE id = ?')->execute([$questionId]);

require_once __DIR__ . '/../../includes/badges.php';
check_badges_for_user($user['id']);

api_json(['data' => ['id' => $answerId]], 201);
