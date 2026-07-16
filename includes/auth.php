<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Minify HTML output site-wide (the callback ignores non-HTML responses).
// Define SKIP_HTML_MINIFY before including auth.php to opt a page out.
if (!defined('SKIP_HTML_MINIFY') && PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/minify.php';
    ob_start('minify_html_output');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

// "Remember me": if there's no active session but a valid remember-token cookie,
// transparently log the user back in. Selector/validator split (not a single
// opaque token) so a DB read to find the row never needs a timing-sensitive
// comparison against secret data — only the validator hash comparison does,
// and that uses hash_equals(). The token is rotated on every use.
if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_token'])) {
    $parts = explode(':', $_COOKIE['remember_token'], 2);
    if (count($parts) === 2) {
        [$selector, $validator] = $parts;
        try {
            $stmt = db()->prepare('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()');
            $stmt->execute([$selector]);
            $tokenRow = $stmt->fetch();
            if ($tokenRow && hash_equals($tokenRow['validator_hash'], hash('sha256', $validator))) {
                db()->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$tokenRow['id']]);
                login_user((int) $tokenRow['user_id'], true);
            } else {
                setcookie('remember_token', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), true);
            }
        } catch (Throwable $e) {
            // fail open (e.g. mid-migration) — just skip auto-login
        }
    }
}

// Global IP block: abusive IPs get a flat 403 on every page. Fail-open if the
// table doesn't exist yet (mid-migration) so the whole site can't be taken down by it.
if (!defined('SKIP_IP_BLOCK_CHECK')) {
    try {
        $ipStmt = db()->prepare('SELECT 1 FROM ip_blocks WHERE ip_address = ?');
        $ipStmt->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
        if ($ipStmt->fetchColumn()) {
            http_response_code(403);
            exit('Access denied.');
        }
    } catch (Throwable $e) {
        // fail open
    }
}

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, username, email, avatar, bio, role, session_version, reputation, location_city, location_country, available_for_hire, is_pro, email_verified_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch() ?: null;
    // A session created before a "log out other sessions" action carries a stale
    // version number and must be treated as logged out, even though the PHP
    // session cookie itself is still valid.
    if ($row && (int) ($_SESSION['session_version'] ?? 0) !== (int) $row['session_version']) {
        logout_user();
        return null;
    }
    $user = $row;
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

function login_user(int $userId, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    $stmt = db()->prepare('SELECT session_version FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $_SESSION['session_version'] = (int) ($stmt->fetchColumn() ?: 1);

    db()->prepare('INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)')
        ->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? null, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);

    // Hooked here rather than in each login page so every auth path — password,
    // 2FA, OTP, passkey, social — is covered by one implementation.
    require_once __DIR__ . '/login_alert.php';
    maybe_send_login_alert($userId);

    if ($remember) {
        set_remember_cookie($userId);
    }
}

/** Issues a fresh selector/validator remember-me token, replacing any prior one for this user. */
function set_remember_cookie(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

    db()->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $selector, hash('sha256', $validator), $expiresAt]);

    setcookie('remember_token', $selector . ':' . $validator, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function logout_user(): void
{
    if (!empty($_COOKIE['remember_token'])) {
        $parts = explode(':', $_COOKIE['remember_token'], 2);
        if (count($parts) === 2) {
            try {
                db()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$parts[0]]);
            } catch (Throwable $e) {
                // ignore — session is being cleared regardless
            }
        }
        setcookie('remember_token', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), true);
    }
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
 * IP-keyed fixed-window rate limiter backed by temp files, for abuse-sensitive
 * endpoints (login, register, password reset) where the session-based limiter
 * can be bypassed by discarding cookies. Complements rate_limit(), not a replacement.
 */
function rate_limit_ip(string $key, int $maxAttempts, int $windowSeconds): bool
{
    require_once __DIR__ . '/api_auth.php';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return api_rate_limit_bucket($key . '_' . substr(hash('sha256', $ip), 0, 40), $maxAttempts, $windowSeconds);
}

/**
 * Honeypot anti-spam: render a field real users never see; bots that fill it
 * are silently detected. Pair honeypot_field() in the form with
 * honeypot_tripped() in the POST handler.
 */
function honeypot_field(): string
{
    return '<div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">'
        . '<label>Leave this field empty<input type="text" name="website_url" tabindex="-1" autocomplete="off" value=""></label></div>';
}

function honeypot_tripped(): bool
{
    return ($_POST['website_url'] ?? '') !== '';
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
