<?php
/**
 * Email OTP (one-time passcode) login.
 *
 * Security model:
 * - Codes are 6 digits, generated with random_int(), stored only as SHA-256
 *   hashes, and valid for 10 minutes.
 * - Any prior unconsumed code for the user is invalidated when a new one is
 *   issued, so only the newest code ever works.
 * - Max 5 verification attempts per code, then it is burned — this is what
 *   stops brute-forcing a 6-digit space.
 * - Verification is single-use and atomic-ish: the code is marked consumed
 *   before the caller logs the user in.
 * - Issuing is rate-limited per IP and per session by the caller.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/client_ip.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_template.php';

const OTP_TTL_SECONDS = 600;
const OTP_MAX_ATTEMPTS = 5;

/**
 * True once migration 030 has created the login_otps table.
 *
 * Without this, requesting a code on a server where 030 has not been applied
 * threw an uncaught PDOException and returned a 500 to the user — the whole
 * OTP page has to disable itself cleanly instead. Cached per request.
 */
function otp_available(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            db()->query('SELECT 1 FROM login_otps LIMIT 0');
            $ok = true;
        } catch (Throwable $e) {
            $ok = false;
        }
    }
    return $ok;
}

/** Issues a fresh OTP for the user and emails it. Returns false if it could not be sent. */
function otp_issue(int $userId, string $email, string $username): bool
{
    if (!otp_available()) {
        return false;
    }
    try {
        $pdo = db();
        // Only the newest code may be usable.
        $pdo->prepare('UPDATE login_otps SET consumed_at = NOW() WHERE user_id = ? AND consumed_at IS NULL')
            ->execute([$userId]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO login_otps (user_id, code_hash, expires_at, ip_address) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)')
            ->execute([$userId, hash('sha256', $code), OTP_TTL_SECONDS, client_ip() ?: null]);

        $minutes = (int) (OTP_TTL_SECONDS / 60);
        $html = email_layout('Your sign-in code', [
            email_paragraph('Hi ' . $username . ','),
            email_paragraph('Use this code to sign in. It expires in ' . $minutes . ' minutes.'),
            email_code($code),
            email_alert('If you did not try to sign in, ignore this email and consider changing your password — someone may know it.', 'warning'),
            email_muted('For your security, never share this code with anyone. ' . setting('site_name', SITE_NAME) . ' staff will never ask for it.'),
        ], 'Your ' . setting('site_name', SITE_NAME) . ' sign-in code (expires in ' . $minutes . ' minutes)');

        return send_mail($email, $username, 'Your sign-in code — ' . setting('site_name', SITE_NAME), $html);
    } catch (Throwable $e) {
        // A broken code path must not 500 the sign-in page.
        error_log('otp_issue failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verifies a submitted code. Returns [ok, message]; on success the code is
 * consumed and the caller may log the user in.
 */
function otp_verify(int $userId, string $submitted): array
{
    $submitted = preg_replace('/\D/', '', $submitted) ?? '';
    if (strlen($submitted) !== 6) {
        return [false, 'Enter the 6-digit code from your email.'];
    }
    if (!otp_available()) {
        return [false, 'Code sign-in is temporarily unavailable. Please sign in with your password.'];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, code_hash, attempts, expires_at FROM login_otps
                               WHERE user_id = ? AND consumed_at IS NULL
                               ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('otp_verify failed: ' . $e->getMessage());
        return [false, 'Code sign-in is temporarily unavailable. Please sign in with your password.'];
    }

    if (!$row) {
        return [false, 'That code is no longer valid. Request a new one.'];
    }
    if (strtotime($row['expires_at']) < time()) {
        $pdo->prepare('UPDATE login_otps SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return [false, 'That code has expired. Request a new one.'];
    }
    if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) {
        $pdo->prepare('UPDATE login_otps SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return [false, 'Too many incorrect attempts. Request a new code.'];
    }

    // Count the attempt before comparing, so a crash mid-request cannot grant a free retry.
    $pdo->prepare('UPDATE login_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);

    if (!hash_equals($row['code_hash'], hash('sha256', $submitted))) {
        $left = OTP_MAX_ATTEMPTS - ((int) $row['attempts'] + 1);
        return [false, $left > 0
            ? "Incorrect code. {$left} attempt" . ($left === 1 ? '' : 's') . ' remaining.'
            : 'Too many incorrect attempts. Request a new code.'];
    }

    $pdo->prepare('UPDATE login_otps SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return [true, ''];
}
