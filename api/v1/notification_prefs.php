<?php
// GET /api/v1/notification_prefs.php — the authenticated key owner's notification preferences. Requires API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->prepare('SELECT email_on_answer, email_on_mention, email_on_message, email_tag_digest, muted_until FROM notification_prefs WHERE user_id = ?');
$stmt->execute([$user['id']]);
$prefs = $stmt->fetch();
if (!$prefs) {
    $prefs = ['email_on_answer' => 1, 'email_on_mention' => 1, 'email_on_message' => 1, 'email_tag_digest' => 1, 'muted_until' => null];
}

api_json(['data' => [
    'email_on_answer' => (bool) $prefs['email_on_answer'],
    'email_on_mention' => (bool) $prefs['email_on_mention'],
    'email_on_message' => (bool) $prefs['email_on_message'],
    'email_tag_digest' => (bool) $prefs['email_tag_digest'],
    'muted_until' => $prefs['muted_until'],
]]);
