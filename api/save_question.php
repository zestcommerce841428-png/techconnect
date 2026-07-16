<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid session, please refresh.']);
    exit;
}

$questionId = (int) ($data['question_id'] ?? 0);
if ($questionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM saved_questions WHERE user_id = ? AND question_id = ?');
$stmt->execute([$user['id'], $questionId]);

if ($stmt->fetchColumn()) {
    $pdo->prepare('DELETE FROM saved_questions WHERE user_id = ? AND question_id = ?')->execute([$user['id'], $questionId]);
    echo json_encode(['saved' => false]);
} else {
    $pdo->prepare('INSERT INTO saved_questions (user_id, question_id) VALUES (?, ?)')->execute([$user['id'], $questionId]);
    echo json_encode(['saved' => true]);
}
