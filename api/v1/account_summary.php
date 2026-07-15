<?php
// GET /api/v1/account_summary.php — a one-call overview of the key owner's account. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$pdo = db();
$profile = $pdo->prepare('SELECT username, reputation, is_pro FROM users WHERE id = ?');
$profile->execute([$user['id']]);
$p = $profile->fetch();

$counts = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM questions WHERE user_id = ? AND status != 'draft') AS questions,
        (SELECT COUNT(*) FROM answers WHERE user_id = ?) AS answers,
        (SELECT COUNT(*) FROM user_badges WHERE user_id = ?) AS badges,
        (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0) AS unread_notifications"
);
$counts->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$c = $counts->fetch();

api_json(['data' => [
    'username' => $p['username'], 'reputation' => (int) $p['reputation'], 'is_pro' => (bool) $p['is_pro'],
    'questions_asked' => (int) $c['questions'], 'answers_posted' => (int) $c['answers'],
    'badges_earned' => (int) $c['badges'], 'unread_notifications' => (int) $c['unread_notifications'],
]]);
