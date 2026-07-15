<?php
require_once __DIR__ . '/db.php';

/** Generates a new API key, storing only its SHA-256 hash. Returns the plaintext key (shown once). */
function api_key_create(int $userId, string $label, string $scope = 'full_access'): string
{
    if (!in_array($scope, ['read_only', 'full_access'], true)) {
        $scope = 'full_access';
    }
    $secret = bin2hex(random_bytes(24));
    $prefix = substr($secret, 0, 8);
    $plaintext = 'tc_' . $secret;
    db()->prepare('INSERT INTO api_keys (user_id, label, scope, key_hash, key_prefix) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $label, $scope, hash('sha256', $plaintext), $prefix]);
    return $plaintext;
}

/** Resolves a Bearer token (or ?api_key=) to its owning user, or null if invalid/revoked. */
function api_authenticate(): ?array
{
    $token = null;
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        $token = $m[1];
    } elseif (!empty($_GET['api_key'])) {
        $token = $_GET['api_key'];
    }
    if (!$token || !str_starts_with($token, 'tc_')) return null;

    $stmt = db()->prepare(
        'SELECT u.id, u.username, u.role, ak.id AS key_id, ak.scope FROM api_keys ak JOIN users u ON u.id = ak.user_id
         WHERE ak.key_hash = ? AND ak.revoked_at IS NULL'
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    if (!$row) return null;

    db()->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$row['key_id']]);
    return $row;
}

/** Call after api_authenticate() on write endpoints; ends the request with a 403 if the key is read-only. */
function api_require_write_scope(array $authUser): void
{
    if (($authUser['scope'] ?? 'full_access') !== 'full_access') {
        api_json(['error' => 'This API key is read-only and cannot perform write operations. Generate a full-access key at /api_keys.'], 403);
    }
}

function api_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/** Simple per-key rate limit reusing the session-based rate_limit() keyed by API key id instead. */
function api_rate_limit(int $keyId, int $max = 60, int $windowSeconds = 60): bool
{
    return api_rate_limit_bucket('key_' . $keyId, $max, $windowSeconds);
}

/**
 * Rate-limits the public (no-API-key-required) read endpoints by IP, since
 * they have no other identity to key off of. Generous by default — this is
 * an abuse/scrape guard, not a per-key quota.
 */
function api_rate_limit_ip(int $max = 120, int $windowSeconds = 60): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // Hash rather than sanitize-and-keep-readable: IPv6 addresses contain ':',
    // which is illegal in Windows filenames (and the cache dir is a plain
    // filesystem path, not necessarily *nix), so any valid IP must map to a
    // safe filename regardless of format.
    return api_rate_limit_bucket('ip_' . substr(hash('sha256', $ip), 0, 40), $max, $windowSeconds);
}

function api_rate_limit_bucket(string $key, int $max, int $windowSeconds): bool
{
    $cacheDir = sys_get_temp_dir() . '/tc_api_rl';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $file = $cacheDir . '/' . $key . '.json';
    $now = time();
    $data = is_file($file) ? json_decode(file_get_contents($file), true) : ['count' => 0, 'reset' => $now + $windowSeconds];
    if ($now > ($data['reset'] ?? 0)) {
        $data = ['count' => 0, 'reset' => $now + $windowSeconds];
    }
    $data['count']++;
    file_put_contents($file, json_encode($data));
    return $data['count'] <= $max;
}
