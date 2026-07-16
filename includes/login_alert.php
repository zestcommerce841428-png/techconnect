<?php
/**
 * New-device login alerts.
 *
 * login_history has always recorded every sign-in, but nothing read it back.
 * This turns that record into account-takeover detection: if a sign-in arrives
 * from a device/IP combination the account has never used before, the owner is
 * emailed so a stolen password cannot be used silently.
 *
 * Deliberate design choices:
 * - Fingerprint = parsed device family + network prefix, NOT the raw IP. Mobile
 *   users change IP constantly (CGNAT), so alerting on exact IP would fire on
 *   every train ride and train users to ignore the alerts.
 * - The very first login of a new account never alerts (nothing to compare to).
 * - Alerts never block the login and never throw: security email failure must
 *   not lock anyone out.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_template.php';

/** Human-readable device string, e.g. "Chrome on Windows". */
function device_label(string $userAgent): string
{
    $browser = 'Unknown browser';
    foreach ([
        'Edg/' => 'Edge', 'OPR/' => 'Opera', 'Chrome/' => 'Chrome',
        'Safari/' => 'Safari', 'Firefox/' => 'Firefox',
    ] as $needle => $name) {
        if (str_contains($userAgent, $needle)) { $browser = $name; break; }
    }
    $os = 'Unknown device';
    foreach ([
        'Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad',
        'Windows' => 'Windows', 'Mac OS X' => 'Mac', 'Linux' => 'Linux',
    ] as $needle => $name) {
        if (str_contains($userAgent, $needle)) { $os = $name; break; }
    }
    return $browser . ' on ' . $os;
}

/** Coarse network identity: /24 for IPv4, /48 for IPv6. Tolerates roaming. */
function network_prefix(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $p = explode('.', $ip);
        return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0/24';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $p = explode(':', $ip);
        return implode(':', array_slice($p, 0, 3)) . '::/48';
    }
    return 'unknown';
}

/**
 * Call immediately AFTER a successful login has been recorded in login_history.
 * Emails the account owner if this device+network pairing is new.
 */
function maybe_send_login_alert(int $userId): void
{
    try {
        if (setting('login_alerts_enabled', '1') !== '1') {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device = device_label($ua);
        $network = network_prefix($ip);

        $pdo = db();

        // Compare against history excluding the row just written for this login.
        $stmt = $pdo->prepare('SELECT ip_address, user_agent FROM login_history
                               WHERE user_id = ? ORDER BY id DESC LIMIT 50 OFFSET 1');
        $stmt->execute([$userId]);
        $previous = $stmt->fetchAll();

        // First-ever login: nothing to compare against, so never alert.
        if (!$previous) {
            return;
        }

        foreach ($previous as $row) {
            if (device_label((string) $row['user_agent']) === $device
                && network_prefix((string) $row['ip_address']) === $network) {
                return; // seen before — not a new device
            }
        }

        $userStmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user || empty($user['email'])) {
            return;
        }

        $siteName = setting('site_name', SITE_NAME);
        $when = date('j M Y, g:i a T');

        $html = email_layout('New sign-in to your account', [
            email_paragraph('Hi ' . $user['username'] . ','),
            email_paragraph('Your ' . $siteName . ' account was just signed in to from a device we have not seen before.'),
            email_list([
                ['title' => $device, 'url' => SITE_URL . '/security_sessions', 'meta' => 'Device'],
                ['title' => $ip !== '' ? $ip : 'Unknown', 'url' => SITE_URL . '/security_sessions', 'meta' => 'IP address'],
                ['title' => $when, 'url' => SITE_URL . '/security_sessions', 'meta' => 'Time'],
            ]),
            email_paragraph('If this was you, no action is needed.'),
            email_alert('If this was NOT you, change your password immediately and sign out all other devices.', 'danger'),
            email_button('Review account security', SITE_URL . '/security_sessions'),
            email_muted('You can turn these alerts off in your notification settings.'),
        ], 'New sign-in from ' . $device);

        send_mail($user['email'], $user['username'], 'New sign-in to your ' . $siteName . ' account', $html);
    } catch (Throwable $e) {
        // Never let alerting break or block a legitimate login.
        error_log('login alert failed: ' . $e->getMessage());
    }
}
