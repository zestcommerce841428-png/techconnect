<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$pdo = db();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["q.group_id IS NULL AND q.status != 'draft' AND q.merged_into_id IS NULL"];
$params = [];
if (!empty($_GET['tag'])) {
    $where[] = 'q.id IN (SELECT question_id FROM question_tags qt JOIN tags t ON t.id = qt.tag_id WHERE t.slug = ?)';
    $params[] = $_GET['tag'];
}
if (!empty($_GET['category'])) {
    $where[] = 'c.slug = ?';
    $params[] = $_GET['category'];
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT q.id, q.title, q.slug, q.vote_score, q.answer_count, q.view_count, q.status, q.created_at,
            u.username, c.slug AS category_slug
     FROM questions q JOIN users u ON u.id = q.user_id LEFT JOIN categories c ON c.id = q.category_id
     $whereSql ORDER BY q.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

api_json([
    'data' => array_map(fn($q) => [
        'id' => (int) $q['id'],
        'title' => $q['title'],
        'url' => SITE_URL . '/q/' . $q['slug'],
        'author' => $q['username'],
        'category' => $q['category_slug'],
        'votes' => (int) $q['vote_score'],
        'answers' => (int) $q['answer_count'],
        'views' => (int) $q['view_count'],
        'status' => $q['status'],
        'created_at' => $q['created_at'],
    ], $rows),
    'page' => $page,
]);
