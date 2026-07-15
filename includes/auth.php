<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, username, email, avatar, bio, role, reputation, location_city, location_country, available_for_hire FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash_set('error', 'Please log in to continue.');
        redirect('/login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form submission. Please go back and try again.');
    }
}

/**
 * Simple fixed-window rate limiter stored in session; good enough for shared hosting
 * without needing a cache layer. Key should be action-specific (e.g. 'login').
 */
function rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $bucket = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'reset' => $now + $windowSeconds];
    if ($now > $bucket['reset']) {
        $bucket = ['count' => 0, 'reset' => $now + $windowSeconds];
    }
    $bucket['count']++;
    $_SESSION['rate_limit'][$key] = $bucket;
    return $bucket['count'] <= $maxAttempts;
}
