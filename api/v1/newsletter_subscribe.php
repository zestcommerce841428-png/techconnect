<?php
// POST /api/v1/newsletter_subscribe.php — subscribe an email to the newsletter. Public, IP rate-limited. Body: email.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);
if (!api_rate_limit_ip(10, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = trim($input['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) api_json(['error' => 'A valid email is required'], 422);

$pdo = db();
$existing = $pdo->prepare('SELECT id, unsubscribed_at FROM newsletter_subscribers WHERE email = ?');
$existing->execute([$email]);
$row = $existing->fetch();
if ($row) {
    if ($row['unsubscribed_at'] !== null) {
        $pdo->prepare('UPDATE newsletter_subscribers SET unsubscribed_at = NULL WHERE id = ?')->execute([$row['id']]);
    }
    api_json(['data' => ['subscribed' => true]]);
}

$pdo->prepare('INSERT INTO newsletter_subscribers (email, unsubscribe_token) VALUES (?, ?)')
    ->execute([$email, bin2hex(random_bytes(24))]);

api_json(['data' => ['subscribed' => true]], 201);
