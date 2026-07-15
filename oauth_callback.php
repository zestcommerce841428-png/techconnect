<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/oauth/oauth.php';

$provider = $_GET['provider'] ?? '';
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

$expectedState = $_SESSION['oauth_state_' . $provider] ?? '';
unset($_SESSION['oauth_state_' . $provider], $_SESSION['oauth_pkce_' . $provider]);

if (!$code || !$state || !$expectedState || !hash_equals($expectedState, $state)) {
    flash_set('error', 'Login could not be verified. Please try again.');
    redirect('/login');
}

$identity = oauth_complete($provider, $code);
if (!$identity) {
    flash_set('error', 'We could not sign you in with that provider. Please try again or use email/password.');
    redirect('/login');
}

$userId = oauth_login_or_create($provider, $identity);
login_user($userId);
flash_set('success', 'Welcome!');
redirect('/');
