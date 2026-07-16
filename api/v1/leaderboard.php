<?php
// GET /api/v1/leaderboard.php — top users by reputation.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query('SELECT username, reputation FROM users ORDER BY reputation DESC LIMIT 50');
api_json(['data' => array_map(fn($u) => [
    'username' => $u['username'], 'reputation' => (int) $u['reputation'],
], $stmt->fetchAll())]);
