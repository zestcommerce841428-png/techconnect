<?php
// GET /api/v1/experts.php — list approved experts.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    'SELECT u.username, e.headline, e.hourly_rate_cents FROM expert_profiles e
     JOIN users u ON u.id = e.user_id WHERE e.is_approved = 1 ORDER BY u.reputation DESC LIMIT 100'
);
api_json(['data' => array_map(fn($e) => [
    'username' => $e['username'], 'headline' => $e['headline'],
    'hourly_rate_cents' => $e['hourly_rate_cents'] !== null ? (int) $e['hourly_rate_cents'] : null,
], $stmt->fetchAll())]);
