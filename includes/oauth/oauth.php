<?php
require_once __DIR__ . '/providers.php';
require_once __DIR__ . '/../payments/crypto.php'; // reuses the same AES-256-GCM helpers for at-rest secrets

function oauth_config(string $provider): ?array
{
    $stmt = db()->prepare('SELECT enabled, config_encrypted FROM oauth_settings WHERE provider = ?');
    $stmt->execute([$provider]);
    $row = $stmt->fetch();
    if (!$row || !$row['enabled']) return null;
    $json = payments_decrypt($row['config_encrypted']);
    $config = $json ? json_decode($json, true) : null;
    return is_array($config) && !empty($config['client_id']) && !empty($config['client_secret']) ? $config : null;
}

function oauth_enabled_providers(): array
{
    $enabled = [];
    foreach (array_keys(oauth_provider_defs()) as $p) {
        if (oauth_config($p) !== null) $enabled[] = $p;
    }
    return $enabled;
}

function oauth_redirect_uri(string $provider): string
{
    return rtrim(SITE_URL, '/') . '/oauth_callback?provider=' . $provider;
}

/** Builds the "Continue with X" link and stashes CSRF-style state (+ PKCE verifier) in session. */
function oauth_authorize_url(string $provider): ?string
{
    $defs = oauth_provider_defs();
    if (!isset($defs[$provider])) return null;
    $config = oauth_config($provider);
    if (!$config) return null;
    $def = $defs[$provider];

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state_' . $provider] = $state;

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => oauth_redirect_uri($provider),
        'response_type' => 'code',
        'scope' => $def['scope'],
        'state' => $state,
    ];
    if (!empty($def['pkce'])) {
        $verifier = bin2hex(random_bytes(32));
        $_SESSION['oauth_pkce_' . $provider] = $verifier;
        $params['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $params['code_challenge_method'] = 'S256';
    }
    return $def['authorize_url'] . '?' . http_build_query($params);
}

/** Exchanges the callback's ?code= for an access token, then fetches and normalizes the profile. */
function oauth_complete(string $provider, string $code): ?array
{
    $defs = oauth_provider_defs();
    if (!isset($defs[$provider])) return null;
    $config = oauth_config($provider);
    if (!$config) return null;
    $def = $defs[$provider];

    $tokenFields = [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code' => $code,
        'redirect_uri' => oauth_redirect_uri($provider),
        'grant_type' => 'authorization_code',
    ];
    if (!empty($def['pkce'])) {
        $tokenFields['code_verifier'] = $_SESSION['oauth_pkce_' . $provider] ?? '';
    }

    $ch = curl_init($def['token_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_POSTFIELDS => http_build_query($tokenFields),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $tokenData = json_decode((string) $response, true);
    if ($status !== 200 || empty($tokenData['access_token'])) return null;

    $ch = curl_init($def['profile_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenData['access_token'], 'Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $profileResponse = curl_exec($ch);
    $profileStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($profileStatus !== 200) return null;
    $profile = json_decode((string) $profileResponse, true);
    if (!is_array($profile)) return null;

    $mapped = ($def['map'])($profile);
    return empty($mapped['id']) ? null : $mapped;
}

/**
 * Logs in the matching user, links a new provider to an already-logged-in
 * account, or creates a fresh account for a brand-new social signup.
 */
function oauth_login_or_create(string $provider, array $identity): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM oauth_accounts WHERE provider = ? AND provider_user_id = ?');
    $stmt->execute([$provider, $identity['id']]);
    $userId = $stmt->fetchColumn();
    if ($userId) return (int) $userId;

    if (!empty($_SESSION['user_id'])) {
        $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id) VALUES (?, ?, ?)')
            ->execute([$_SESSION['user_id'], $provider, $identity['id']]);
        return (int) $_SESSION['user_id'];
    }

    if (!empty($identity['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$identity['email']]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id) VALUES (?, ?, ?)')
                ->execute([$existing, $provider, $identity['id']]);
            return (int) $existing;
        }
    }

    $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($identity['name'] ?? $provider . '_user')) ?: $provider . '_user';
    $baseUsername = mb_substr($baseUsername, 0, 20) ?: $provider . '_user';
    $username = $baseUsername;
    $suffix = 1;
    $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    while (true) {
        $check->execute([$username]);
        if (!$check->fetchColumn()) break;
        $username = $baseUsername . $suffix++;
    }
    $email = $identity['email'] ?? ($provider . '_' . $identity['id'] . '@users.' . parse_url(SITE_URL, PHP_URL_HOST));

    $pdo->prepare('INSERT INTO users (username, email, password_hash, email_verified_at) VALUES (?, ?, ?, ?)')
        ->execute([$username, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), !empty($identity['email']) ? date('Y-m-d H:i:s') : null]);
    $newId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id) VALUES (?, ?, ?)')
        ->execute([$newId, $provider, $identity['id']]);
    return $newId;
}
