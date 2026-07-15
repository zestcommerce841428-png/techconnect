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

$type = $data['type'] ?? '';
$id = (int) ($data['id'] ?? 0);
if (!in_array($type, ['user', 'tag', 'question'], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
if ($type === 'user' && $id === $user['id']) {
    http_response_code(400);
    echo json_encode(['error' => "You can't follow yourself."]);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM follows WHERE follower_id = ? AND followable_type = ? AND followable_id = ?');
$stmt->execute([$user['id'], $type, $id]);

if ($stmt->fetchColumn()) {
    $pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND followable_type = ? AND followable_id = ?')
        ->execute([$user['id'], $type, $id]);
    echo json_encode(['following' => false]);
} else {
    $pdo->prepare('INSERT INTO follows (follower_id, followable_type, followable_id) VALUES (?, ?, ?)')
        ->execute([$user['id'], $type, $id]);
    echo json_encode(['following' => true]);
}
