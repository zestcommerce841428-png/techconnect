<?php
// POST /api/v1/notification_prefs_update.php — update notification preferences. Requires API key.
// Body: any of email_on_answer, email_on_mention, email_on_message, email_tag_digest (booleans).
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 30, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$fields = ['email_on_answer', 'email_on_mention', 'email_on_message', 'email_tag_digest'];
$updates = [];
foreach ($fields as $f) {
    if (array_key_exists($f, $input)) $updates[$f] = filter_var($input[$f], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}
if (!$updates) api_json(['error' => 'No valid fields provided: ' . implode(', ', $fields)], 422);

$pdo = db();
$pdo->prepare('INSERT IGNORE INTO notification_prefs (user_id) VALUES (?)')->execute([$user['id']]);
$set = implode(', ', array_map(fn($f) => "$f = ?", array_keys($updates)));
$pdo->prepare("UPDATE notification_prefs SET $set WHERE user_id = ?")
    ->execute([...array_values($updates), $user['id']]);

api_json(['data' => ['updated' => true]]);
