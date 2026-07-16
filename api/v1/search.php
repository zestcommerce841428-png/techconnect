<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$q = trim($_GET['q'] ?? '');
if ($q === '') api_json(['error' => 'q parameter required'], 422);

$stmt = db()->prepare(
    "SELECT title, slug, answer_count, vote_score FROM questions
     WHERE group_id IS NULL AND status != 'draft' AND merged_into_id IS NULL AND MATCH(title, body) AGAINST (? IN NATURAL LANGUAGE MODE)
     LIMIT 20"
);
$stmt->execute([$q]);
api_json(['data' => array_map(fn($r) => [
    'title' => $r['title'], 'url' => SITE_URL . '/q/' . $r['slug'],
    'answers' => (int) $r['answer_count'], 'votes' => (int) $r['vote_score'],
], $stmt->fetchAll())]);
