<?php
/** AES-256-GCM encrypt/decrypt for storing gateway API keys at rest, keyed off APP_KEY. */

function payments_encrypt(string $plaintext): string
{
    $key = hash('sha256', APP_KEY, true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function payments_decrypt(?string $encoded): ?string
{
    if (!$encoded) return null;
    $raw = base64_decode($encoded);
    if ($raw === false || strlen($raw) < 28) return null;
    $key = hash('sha256', APP_KEY, true);
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}
