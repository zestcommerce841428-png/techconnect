<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to vote.']);
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
$value = (int) ($data['value'] ?? 0);

if (!in_array($type, ['question', 'answer'], true) || !in_array($value, [1, -1], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$table = $type === 'question' ? 'questions' : 'answers';
$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT value FROM votes WHERE user_id = ? AND votable_type = ? AND votable_id = ?');
    $stmt->execute([$user['id'], $type, $id]);
    $existing = $stmt->fetchColumn();

    if ($existing === false) {
        $pdo->prepare('INSERT INTO votes (user_id, votable_type, votable_id, value) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $type, $id, $value]);
        $delta = $value;
    } elseif ((int) $existing === $value) {
        $pdo->prepare('DELETE FROM votes WHERE user_id = ? AND votable_type = ? AND votable_id = ?')
            ->execute([$user['id'], $type, $id]);
        $delta = -$value;
    } else {
        $pdo->prepare('UPDATE votes SET value = ? WHERE user_id = ? AND votable_type = ? AND votable_id = ?')
            ->execute([$value, $user['id'], $type, $id]);
        $delta = $value * 2;
    }

    $pdo->prepare("UPDATE {$table} SET vote_score = vote_score + ? WHERE id = ?")->execute([$delta, $id]);
    $score = $pdo->prepare("SELECT vote_score FROM {$table} WHERE id = ?");
    $score->execute([$id]);
    $newScore = (int) $score->fetchColumn();

    $pdo->commit();
    echo json_encode(['score' => $newScore]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Vote failed.']);
}
