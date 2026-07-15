<?php
// POST /api/v1/roadmap_vote.php — vote for a roadmap item (toggles on repeat). Requires API key. Body: id.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 60, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$itemId = (int) ($input['id'] ?? 0);
if ($itemId <= 0) api_json(['error' => 'id is required'], 422);

$pdo = db();
$item = $pdo->prepare('SELECT id FROM roadmap_items WHERE id = ?');
$item->execute([$itemId]);
if (!$item->fetchColumn()) api_json(['error' => 'Not found'], 404);

$existing = $pdo->prepare('SELECT id FROM roadmap_votes WHERE item_id = ? AND user_id = ?');
$existing->execute([$itemId, $user['id']]);
if ($existing->fetchColumn()) {
    $pdo->prepare('DELETE FROM roadmap_votes WHERE item_id = ? AND user_id = ?')->execute([$itemId, $user['id']]);
    $pdo->prepare('UPDATE roadmap_items SET vote_count = GREATEST(0, vote_count - 1) WHERE id = ?')->execute([$itemId]);
    api_json(['data' => ['voted' => false]]);
}
$pdo->prepare('INSERT INTO roadmap_votes (item_id, user_id) VALUES (?, ?)')->execute([$itemId, $user['id']]);
$pdo->prepare('UPDATE roadmap_items SET vote_count = vote_count + 1 WHERE id = ?')->execute([$itemId]);
api_json(['data' => ['voted' => true]]);
