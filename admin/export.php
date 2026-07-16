<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$admin = require_role('admin', 'moderator');

$allowed = [
    'users' => ['id', 'username', 'email', 'role', 'reputation', 'created_at'],
    'questions' => ['id', 'title', 'slug', 'vote_score', 'answer_count', 'status', 'created_at'],
    'answers' => ['id', 'question_id', 'user_id', 'vote_score', 'is_accepted', 'created_at'],
    'jobs' => ['id', 'title', 'company', 'location', 'status', 'created_at'],
];

$table = $_GET['table'] ?? '';
if (!isset($allowed[$table])) {
    http_response_code(400);
    exit('Invalid export table.');
}

$columns = $allowed[$table];
$pdo = db();
$stmt = $pdo->query('SELECT ' . implode(', ', $columns) . " FROM {$table} ORDER BY id DESC LIMIT 10000");

audit_log($admin['id'], 'export_csv', $table, null);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $table . '_export_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, $columns);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
}
fclose($out);
