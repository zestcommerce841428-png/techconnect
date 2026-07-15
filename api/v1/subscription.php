<?php
// GET /api/v1/subscription.php — the authenticated key owner's Pro membership status. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare(
    "SELECT plan, gateway, status, current_period_end FROM subscriptions
     WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1"
);
$stmt->execute([$user['id']]);
$sub = $stmt->fetch();

$flags = db()->prepare('SELECT is_pro, pro_expires_at FROM users WHERE id = ?');
$flags->execute([$user['id']]);
$u = $flags->fetch();

api_json(['data' => [
    'is_pro' => (bool) $u['is_pro'],
    'pro_expires_at' => $u['pro_expires_at'],
    'subscription' => $sub ? [
        'plan' => $sub['plan'], 'gateway' => $sub['gateway'], 'status' => $sub['status'],
        'current_period_end' => $sub['current_period_end'],
    ] : null,
]]);
