<?php
// GET /api/v1/collection.php?username=<username>&slug=<slug> — a public collection and its questions.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$username = trim($_GET['username'] ?? '');
$slug = trim($_GET['slug'] ?? '');
if ($username === '' || $slug === '') api_json(['error' => 'username and slug are required'], 422);

$stmt = db()->prepare(
    'SELECT c.id, c.name, c.description FROM collections c JOIN users u ON u.id = c.user_id
     WHERE u.username = ? AND c.slug = ? AND c.is_public = 1'
);
$stmt->execute([$username, $slug]);
$c = $stmt->fetch();
if (!$c) api_json(['error' => 'Not found'], 404);

$items = db()->prepare(
    'SELECT q.title, q.slug FROM collection_items ci JOIN questions q ON q.id = ci.question_id
     WHERE ci.collection_id = ? ORDER BY ci.added_at DESC'
);
$items->execute([$c['id']]);

api_json(['data' => [
    'name' => $c['name'], 'description' => $c['description'],
    'questions' => array_map(fn($q) => ['title' => $q['title'], 'url' => SITE_URL . '/q/' . $q['slug']], $items->fetchAll()),
]]);
