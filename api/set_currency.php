<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/currency.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
verify_csrf();

$code = strtoupper(trim($_POST['currency'] ?? ''));
if (!currency_get($code)) {
    http_response_code(422);
    exit('Unknown currency');
}

setcookie('display_currency', $code, time() + 86400 * 365, '/', '', !empty($_SERVER['HTTPS']), true);

$user = current_user();
if ($user) {
    db()->prepare('UPDATE users SET display_currency = ? WHERE id = ?')->execute([$code, $user['id']]);
}

$redirect = $_POST['redirect'] ?? '/';
if (!str_starts_with($redirect, '/')) $redirect = '/';
redirect($redirect);
