<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$pdo = db();
$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare(
    'SELECT q.id, q.title, q.slug, q.body, q.vote_score, q.answer_count, q.view_count, q.status, q.created_at, u.username
     FROM questions q JOIN users u ON u.id = q.user_id WHERE q.slug = ? AND q.group_id IS NULL'
);
$stmt->execute([$slug]);
$question = $stmt->fetch();
if (!$question) api_json(['error' => 'Not found'], 404);

$answers = $pdo->prepare(
    'SELECT a.body, a.vote_score, a.is_accepted, a.created_at, u.username
     FROM answers a JOIN users u ON u.id = a.user_id WHERE a.question_id = ? ORDER BY a.is_accepted DESC, a.vote_score DESC'
);
$answers->execute([$question['id']]);

api_json([
    'data' => [
        'id' => (int) $question['id'],
        'title' => $question['title'],
        'body' => $question['body'],
        'url' => SITE_URL . '/q/' . $question['slug'],
        'author' => $question['username'],
        'votes' => (int) $question['vote_score'],
        'views' => (int) $question['view_count'],
        'status' => $question['status'],
        'created_at' => $question['created_at'],
        'answers' => array_map(fn($a) => [
            'body' => $a['body'], 'author' => $a['username'], 'votes' => (int) $a['vote_score'],
            'accepted' => (bool) $a['is_accepted'], 'created_at' => $a['created_at'],
        ], $answers->fetchAll()),
    ],
]);
