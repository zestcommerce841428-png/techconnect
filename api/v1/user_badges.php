<?php
// GET /api/v1/user_badges.php?username=<username> — badges earned by a user.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$username = trim($_GET['username'] ?? '');
if ($username === '') api_json(['error' => 'username is required'], 422);

$userCheck = db()->prepare('SELECT id FROM users WHERE username = ?');
$userCheck->execute([$username]);
if (!$userCheck->fetchColumn()) api_json(['error' => 'Not found'], 404);

$stmt = db()->prepare(
    'SELECT b.code, b.name, b.icon, b.tier, ub.awarded_at FROM user_badges ub
     JOIN badges b ON b.id = ub.badge_id JOIN users u ON u.id = ub.user_id
     WHERE u.username = ? ORDER BY ub.awarded_at DESC'
);
$stmt->execute([$username]);
api_json(['data' => $stmt->fetchAll()]);
