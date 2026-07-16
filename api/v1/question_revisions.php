<?php
// GET /api/v1/question_revisions.php?slug=<slug> — edit history for a question.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$q = db()->prepare('SELECT id FROM questions WHERE slug = ?');
$q->execute([$slug]);
$questionId = $q->fetchColumn();
if (!$questionId) api_json(['error' => 'Not found'], 404);

$stmt = db()->prepare(
    'SELECT r.prior_title, r.prior_body, r.created_at, u.username AS editor
     FROM question_revisions r JOIN users u ON u.id = r.editor_id
     WHERE r.question_id = ? ORDER BY r.created_at DESC'
);
$stmt->execute([$questionId]);
api_json(['data' => array_map(fn($r) => [
    'prior_title' => $r['prior_title'], 'prior_body' => $r['prior_body'],
    'editor' => $r['editor'], 'created_at' => $r['created_at'],
], $stmt->fetchAll())]);
