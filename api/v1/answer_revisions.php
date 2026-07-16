<?php
// GET /api/v1/answer_revisions.php?answer_id=<id> — edit history for an answer.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$answerId = (int) ($_GET['answer_id'] ?? 0);
if ($answerId <= 0) api_json(['error' => 'answer_id is required'], 422);

$exists = db()->prepare('SELECT 1 FROM answers WHERE id = ?');
$exists->execute([$answerId]);
if (!$exists->fetchColumn()) api_json(['error' => 'Not found'], 404);

$stmt = db()->prepare(
    'SELECT r.prior_body, r.created_at, u.username AS editor
     FROM answer_revisions r JOIN users u ON u.id = r.editor_id
     WHERE r.answer_id = ? ORDER BY r.created_at DESC'
);
$stmt->execute([$answerId]);
api_json(['data' => array_map(fn($r) => [
    'prior_body' => $r['prior_body'], 'editor' => $r['editor'], 'created_at' => $r['created_at'],
], $stmt->fetchAll())]);
