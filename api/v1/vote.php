<?php
// POST /api/v1/vote.php — vote on a question or answer. Requires API key. value: 1 or -1.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 60, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$votableType = $input['votable_type'] ?? '';
$votableId = (int) ($input['votable_id'] ?? 0);
$value = (int) ($input['value'] ?? 0);

if (!in_array($votableType, ['question', 'answer'], true) || $votableId <= 0) {
    api_json(['error' => 'votable_type (question|answer) and votable_id are required'], 422);
}
if (!in_array($value, [1, -1], true)) api_json(['error' => 'value must be 1 or -1'], 422);

$pdo = db();
$table = $votableType === 'question' ? 'questions' : 'answers';
$row = $pdo->prepare("SELECT user_id FROM $table WHERE id = ?");
$row->execute([$votableId]);
$ownerId = $row->fetchColumn();
if ($ownerId === false) api_json(['error' => 'Not found'], 404);
if ((int) $ownerId === $user['id']) api_json(['error' => 'You cannot vote on your own post'], 403);

$pdo->beginTransaction();
try {
    $existing = $pdo->prepare('SELECT id, value FROM votes WHERE user_id = ? AND votable_type = ? AND votable_id = ?');
    $existing->execute([$user['id'], $votableType, $votableId]);
    $current = $existing->fetch();
    $delta = 0;
    if ($current) {
        if ((int) $current['value'] === $value) {
            $pdo->prepare('DELETE FROM votes WHERE id = ?')->execute([$current['id']]);
            $delta = -$value;
        } else {
            $pdo->prepare('UPDATE votes SET value = ? WHERE id = ?')->execute([$value, $current['id']]);
            $delta = $value * 2;
        }
    } else {
        $pdo->prepare('INSERT INTO votes (user_id, votable_type, votable_id, value) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $votableType, $votableId, $value]);
        $delta = $value;
    }
    $pdo->prepare("UPDATE $table SET vote_score = vote_score + ? WHERE id = ?")->execute([$delta, $votableId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    api_json(['error' => 'Could not record vote'], 500);
}

$score = $pdo->query("SELECT vote_score FROM $table WHERE id = $votableId")->fetchColumn();
api_json(['data' => ['vote_score' => (int) $score]]);
