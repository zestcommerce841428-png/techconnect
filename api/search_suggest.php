<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!rate_limit('search_suggest', 90, 60)) {
    http_response_code(429);
    echo json_encode(['results' => [], 'error' => 'Rate limit exceeded']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . addcslashes($q, '%_\\') . '%';

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT title, slug, answer_count
     FROM questions
     WHERE group_id IS NULL AND status != 'draft' AND merged_into_id IS NULL AND title LIKE ?
     ORDER BY view_count DESC
     LIMIT 6"
);
$stmt->execute([$like]);
echo json_encode(['results' => $stmt->fetchAll()]);
