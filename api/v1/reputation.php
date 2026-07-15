<?php
// GET /api/v1/reputation.php?username=<username> — reputation event history for a user.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$username = trim($_GET['username'] ?? '');
if ($username === '') api_json(['error' => 'username is required'], 422);

$userCheck = db()->prepare('SELECT id FROM users WHERE username = ?');
$userCheck->execute([$username]);
$userId = $userCheck->fetchColumn();
if (!$userId) api_json(['error' => 'Not found'], 404);

$stmt = db()->prepare('SELECT points, reason, created_at FROM reputation_events WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$userId]);
api_json(['data' => array_map(fn($r) => [
    'points' => (int) $r['points'], 'reason' => $r['reason'], 'created_at' => $r['created_at'],
], $stmt->fetchAll())]);
