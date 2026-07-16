<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$username = $_GET['username'] ?? '';
$stmt = db()->prepare('SELECT username, bio, reputation, location_city, location_country, available_for_hire, created_at FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user) api_json(['error' => 'Not found'], 404);

api_json(['data' => [
    'username' => $user['username'],
    'bio' => $user['bio'],
    'reputation' => (int) $user['reputation'],
    'location' => trim(($user['location_city'] ?? '') . ', ' . ($user['location_country'] ?? ''), ', ') ?: null,
    'available_for_hire' => (bool) $user['available_for_hire'],
    'joined' => $user['created_at'],
    'profile_url' => SITE_URL . '/u/' . $user['username'],
]]);
