<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}
verify_csrf();

if (!rate_limit('newsletter_subscribe', 5, 600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many attempts, please try again later.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

$user = current_user();
$token = bin2hex(random_bytes(24));
db()->prepare(
    'INSERT INTO newsletter_subscribers (email, user_id, unsubscribe_token) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE unsubscribed_at = NULL'
)->execute([$email, $user['id'] ?? null, $token]);

echo json_encode(['success' => true, 'message' => "You're subscribed!"]);
