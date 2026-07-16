<?php
/**
 * Resolves the visitor's IP address.
 *
 * Why this exists: puchonow.in serves through Hostinger's CDN ("Server: hcdn").
 * If the CDN terminates the connection and the origin does not restore the real
 * client address, REMOTE_ADDR is the CDN's IP — and then every IP-based control
 * silently degrades:
 *   - IP blocking blocks the CDN (i.e. everyone, or no one)
 *   - rate_limit_ip() puts ALL visitors in ONE bucket, so a single abuser
 *     locks out the whole site
 *   - login alerts and the security dashboard record a meaningless address
 *
 * Why it is NOT simply "read X-Forwarded-For": that header is attacker-supplied.
 * Trusting it blindly is strictly worse than trusting REMOTE_ADDR, because an
 * attacker can then forge any IP and walk through IP blocks and rate limits at
 * will. Countless "IP ban" bypasses are exactly this bug.
 *
 * So: REMOTE_ADDR by default. Forwarded headers are honoured ONLY when the
 * request genuinely arrives from an address an admin has explicitly listed as a
 * trusted proxy (setting: trusted_proxies, comma-separated IPs or CIDRs).
 */

/** True if $ip falls inside $cidr ("203.0.113.4" or "203.0.113.0/24"). */
function ip_in_cidr(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($cidr === '') return false;
    if (!str_contains($cidr, '/')) {
        return $ip === $cidr;
    }
    [$subnet, $bits] = explode('/', $cidr, 2);
    $bits = (int) $bits;

    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false; // mixed IPv4/IPv6 or malformed
    }
    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) return false;

    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;
    if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
        return false;
    }
    if ($remainder === 0) {
        return true;
    }
    $mask = chr(0xFF << (8 - $remainder) & 0xFF);
    return (($ipBin[$bytes] & $mask) === ($subnetBin[$bytes] & $mask));
}

/** True if the direct peer is an admin-declared trusted proxy. */
function from_trusted_proxy(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote === '') return false;
    $list = trim(setting('trusted_proxies', ''));
    if ($list === '') return false;
    foreach (explode(',', $list) as $cidr) {
        if (ip_in_cidr($remote, $cidr)) return true;
    }
    return false;
}

/**
 * The visitor's IP. Falls back to REMOTE_ADDR whenever forwarding is not
 * explicitly trusted, so this can never be weaker than the previous behaviour.
 */
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!from_trusted_proxy()) {
        return $remote;
    }
    // Behind a trusted proxy: take the right-most address that is not itself a
    // trusted proxy. Left-most is attacker-controlled (clients can prepend
    // arbitrary entries); walking from the right skips only hops we trust.
    $chain = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($chain === '') {
        return $remote;
    }
    $parts = array_reverse(array_map('trim', explode(',', $chain)));
    foreach ($parts as $candidate) {
        // Strip an IPv6 bracket/port form like "[2001:db8::1]:443"
        $candidate = preg_replace('/^\[(.+)\](:\d+)?$/', '$1', $candidate) ?? $candidate;
        if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
            continue;
        }
        $isProxy = false;
        foreach (explode(',', (string) setting('trusted_proxies', '')) as $cidr) {
            if (ip_in_cidr($candidate, $cidr)) { $isProxy = true; break; }
        }
        if (!$isProxy) {
            return $candidate;
        }
    }
    return $remote;
}
