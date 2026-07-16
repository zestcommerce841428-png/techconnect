<?php
/**
 * Brute-force protection: failed-login recording, account lockout, IP throttling.
 *
 * Layered with the existing session/IP rate limiters rather than replacing them:
 *   rate_limit()      — per session (trivially bypassed by dropping cookies)
 *   rate_limit_ip()   — per IP     (bypassed by a botnet / rotating proxies)
 *   this file         — per ACCOUNT (survives both, because the target is fixed)
 *
 * Account lockout is the only layer that reliably protects one specific user
 * from a distributed password-guessing attack.
 *
 * Design notes:
 * - Lockout is temporary (not permanent) — a permanent lock hands attackers a
 *   denial-of-service against any account whose email they know.
 * - Lock duration escalates with repeated lockouts, so a determined attacker
 *   backs off fast while a fat-fingered user is barely inconvenienced.
 * - A successful login clears the counter; nothing accumulates forever.
 * - Every function fails OPEN (returns "not locked") if the table is missing,
 *   so a mid-migration server can never lock everyone out of the site.
 */
require_once __DIR__ . '/db.php';

const BF_MAX_FAILURES = 8;        // failures within the window before locking
const BF_WINDOW_MINUTES = 15;     // rolling window to count failures in
const BF_BASE_LOCK_MINUTES = 15;  // first lockout duration

/** Records a failed attempt and applies a lock if the threshold is crossed. */
function record_failed_login(string $identity, ?int $userId, string $reason = 'bad_password'): void
{
    try {
        db()->prepare('INSERT INTO failed_logins (identity, user_id, ip_address, user_agent, reason) VALUES (?, ?, ?, ?, ?)')
            ->execute([
                mb_substr($identity, 0, 255),
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $reason,
            ]);

        if ($userId === null) {
            return; // unknown account — nothing to lock
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM failed_logins
                               WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)');
        $stmt->execute([$userId, BF_WINDOW_MINUTES]);
        $recent = (int) $stmt->fetchColumn();

        if ($recent < BF_MAX_FAILURES) {
            return;
        }

        // Escalate: each additional lockout in the last 24h doubles the duration,
        // capped so a legitimate user is never locked out for a whole day.
        $priorStmt = db()->prepare("SELECT COUNT(*) FROM failed_logins
                                    WHERE user_id = ? AND reason = 'locked'
                                      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $priorStmt->execute([$userId]);
        $priorLocks = (int) $priorStmt->fetchColumn();
        $minutes = min(BF_BASE_LOCK_MINUTES * (2 ** min($priorLocks, 4)), 240);

        db()->prepare('UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
            ->execute([$minutes, $userId]);

        db()->prepare("INSERT INTO failed_logins (identity, user_id, ip_address, user_agent, reason)
                       VALUES (?, ?, ?, ?, 'locked')")
            ->execute([
                mb_substr($identity, 0, 255), $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
    } catch (Throwable $e) {
        // Never block a login attempt because monitoring is unavailable.
        error_log('record_failed_login failed: ' . $e->getMessage());
    }
}

/** Minutes remaining on an account lock, or 0 if not locked. Fails open. */
function account_lock_minutes_left(?int $userId): int
{
    if ($userId === null) return 0;
    try {
        $stmt = db()->prepare('SELECT locked_until FROM users WHERE id = ? AND locked_until > NOW()');
        $stmt->execute([$userId]);
        $until = $stmt->fetchColumn();
        if (!$until) return 0;
        return max(1, (int) ceil((strtotime($until) - time()) / 60));
    } catch (Throwable $e) {
        return 0; // fail open — a broken table must not lock the site
    }
}

/** Clears failure history and any lock after a successful authentication. */
function clear_failed_logins(int $userId): void
{
    try {
        db()->prepare('UPDATE users SET locked_until = NULL WHERE id = ? AND locked_until IS NOT NULL')
            ->execute([$userId]);
        db()->prepare('DELETE FROM failed_logins WHERE user_id = ? AND reason <> ?')
            ->execute([$userId, 'locked']); // keep lock events for the audit trail
    } catch (Throwable $e) {
        // non-fatal
    }
}
