<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!rate_limit('related_questions', 90, 60)) {
    http_response_code(429);
    echo json_encode(['results' => [], 'error' => 'Rate limit exceeded']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 8) {
    echo json_encode(['results' => []]);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT title, slug, answer_count
     FROM questions
     WHERE group_id IS NULL AND status != 'draft' AND merged_into_id IS NULL AND MATCH(title, body) AGAINST (? IN NATURAL LANGUAGE MODE)
     ORDER BY answer_count DESC
     LIMIT 5"
);
$stmt->execute([$q]);
echo json_encode(['results' => $stmt->fetchAll()]);
