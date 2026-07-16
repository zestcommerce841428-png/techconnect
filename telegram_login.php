<?php
// Handles the redirect/callback from Telegram's Login Widget, which signs
// its payload with HMAC-SHA256 of the bot token instead of a code exchange.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/oauth/oauth.php';

$config = null;
$stmt = db()->prepare('SELECT enabled, config_encrypted FROM oauth_settings WHERE provider = ?');
$stmt->execute(['telegram']);
$row = $stmt->fetch();
if ($row && $row['enabled']) {
    $json = payments_decrypt($row['config_encrypted']);
    $config = $json ? json_decode($json, true) : null;
}
$botToken = $config['bot_token'] ?? null;

if (!$botToken || empty($_GET['hash']) || empty($_GET['id'])) {
    flash_set('error', 'Telegram login is not available right now.');
    redirect('/login');
}

$data = $_GET;
$hash = $data['hash'];
unset($data['hash']);
ksort($data);
$checkString = implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys($data), $data));
$secretKey = hash('sha256', $botToken, true);
$computedHash = hash_hmac('sha256', $checkString, $secretKey);

if (!hash_equals($computedHash, $hash) || (time() - (int) $data['auth_date']) > 86400) {
    flash_set('error', 'Telegram login could not be verified.');
    redirect('/login');
}

$identity = [
    'id' => (string) $data['id'],
    'email' => null,
    'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: ($data['username'] ?? 'Telegram user'),
];
$userId = oauth_login_or_create('telegram', $identity);
login_user($userId);
flash_set('success', 'Welcome!');
redirect('/');
