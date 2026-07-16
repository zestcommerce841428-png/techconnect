<?php
// GET /api/v1/saved_searches.php — the authenticated key owner's saved searches. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare('SELECT id, label, query_string, created_at FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
api_json(['data' => array_map(fn($s) => [
    'id' => (int) $s['id'], 'label' => $s['label'], 'query' => $s['query_string'], 'created_at' => $s['created_at'],
], $stmt->fetchAll())]);
