<?php
// GET /api/v1/drafts.php — the authenticated key owner's draft questions. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare("SELECT id, title, body, created_at FROM questions WHERE user_id = ? AND status = 'draft' ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
api_json(['data' => array_map(fn($d) => [
    'id' => (int) $d['id'], 'title' => $d['title'], 'body' => $d['body'], 'created_at' => $d['created_at'],
], $stmt->fetchAll())]);
