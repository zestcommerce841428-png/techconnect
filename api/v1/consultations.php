<?php
// GET /api/v1/consultations.php — consultations where the key owner is the requester or the expert. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare(
    'SELECT c.id, c.status, c.message, c.created_at, e.username AS expert, r.username AS requester
     FROM consultations c
     JOIN users e ON e.id = c.expert_id JOIN users r ON r.id = c.requester_id
     WHERE c.expert_id = ? OR c.requester_id = ? ORDER BY c.created_at DESC LIMIT 100'
);
$stmt->execute([$user['id'], $user['id']]);
api_json(['data' => array_map(fn($c) => [
    'id' => (int) $c['id'], 'status' => $c['status'], 'message' => $c['message'],
    'expert' => $c['expert'], 'requester' => $c['requester'], 'created_at' => $c['created_at'],
], $stmt->fetchAll())]);
