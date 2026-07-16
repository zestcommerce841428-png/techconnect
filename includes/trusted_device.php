<?php
/**
 * "Trust this device" — lets a 2FA-enabled user skip the TOTP prompt for 30
 * days on a specific browser. Same selector/validator cookie pattern as
 * remember-me, in a separate table/cookie so trusting a device never grants
 * a full login by itself (it only ever short-circuits the *second* factor,
 * after a correct password has already been verified).
 */
function is_trusted_device(int $userId): bool
{
    if (empty($_COOKIE['trusted_device'])) return false;
    $parts = explode(':', $_COOKIE['trusted_device'], 2);
    if (count($parts) !== 2) return false;
    [$selector, $validator] = $parts;

    $stmt = db()->prepare('SELECT * FROM trusted_devices WHERE selector = ? AND user_id = ? AND expires_at > NOW()');
    $stmt->execute([$selector, $userId]);
    $row = $stmt->fetch();
    if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        return false;
    }

    db()->prepare('UPDATE trusted_devices SET last_used_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return true;
}

function trust_this_device(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
    $label = mb_substr(trim(($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device')), 0, 150);

    db()->prepare('INSERT INTO trusted_devices (user_id, selector, validator_hash, label, expires_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $selector, hash('sha256', $validator), $label, $expiresAt]);

    setcookie('trusted_device', $selector . ':' . $validator, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
