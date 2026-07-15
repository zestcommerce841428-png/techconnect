<?php
// GET /api/v1/expert.php?username=<username> — single expert profile.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$username = trim($_GET['username'] ?? '');
if ($username === '') api_json(['error' => 'username is required'], 422);

$stmt = db()->prepare(
    'SELECT u.username, u.reputation, e.headline, e.bio, e.hourly_rate_cents FROM expert_profiles e
     JOIN users u ON u.id = e.user_id WHERE u.username = ? AND e.is_approved = 1'
);
$stmt->execute([$username]);
$e = $stmt->fetch();
if (!$e) api_json(['error' => 'Not found'], 404);

api_json(['data' => [
    'username' => $e['username'], 'reputation' => (int) $e['reputation'], 'headline' => $e['headline'],
    'bio' => $e['bio'], 'hourly_rate_cents' => $e['hourly_rate_cents'] !== null ? (int) $e['hourly_rate_cents'] : null,
]]);
