<?php
// GET /api/v1/answers.php?slug=<question-slug> — list answers for a question.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$pdo = db();
$q = $pdo->prepare('SELECT id FROM questions WHERE slug = ?');
$q->execute([$slug]);
$questionId = $q->fetchColumn();
if (!$questionId) api_json(['error' => 'Not found'], 404);

$stmt = $pdo->prepare(
    'SELECT a.id, a.body, a.vote_score, a.is_accepted, a.created_at, u.username
     FROM answers a JOIN users u ON u.id = a.user_id
     WHERE a.question_id = ? ORDER BY a.is_accepted DESC, a.vote_score DESC'
);
$stmt->execute([$questionId]);

api_json(['data' => array_map(fn($a) => [
    'id' => (int) $a['id'],
    'body' => $a['body'],
    'author' => $a['username'],
    'votes' => (int) $a['vote_score'],
    'accepted' => (bool) $a['is_accepted'],
    'created_at' => $a['created_at'],
], $stmt->fetchAll())]);
