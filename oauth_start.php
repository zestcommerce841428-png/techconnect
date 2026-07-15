<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/oauth/oauth.php';

$provider = $_GET['provider'] ?? '';
$url = oauth_authorize_url($provider);
if (!$url) {
    flash_set('error', 'That login method is not available right now.');
    redirect('/login');
}
header('Location: ' . $url);
exit;
