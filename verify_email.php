<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$user = current_user();
$pdo = db();

// Resend flow (POST from the banner/profile).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user = require_login();
    if ($user && !rate_limit('resend_verification', 3, 3600)) {
        flash_set('error', 'Too many resend attempts. Please try again later.');
        redirect('/profile');
    }
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))
                   ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)')
        ->execute([$user['id'], $token]);
    $link = SITE_URL . '/verify_email?token=' . $token;
    @send_mail($user['email'], $user['username'], 'Verify your email — ' . SITE_NAME,
        '<p>Hi ' . htmlspecialchars($user['username']) . ',</p><p><a href="' . htmlspecialchars($link) . '">Verify my email</a> (expires in 48 hours).</p>');
    flash_set('success', 'Verification email sent to ' . $user['email'] . '.');
    redirect('/profile');
}

$token = $_GET['token'] ?? '';
$verified = false;
$emailChanged = null;
if ($token && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare('SELECT user_id, new_email FROM email_verifications WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
        if ($row['new_email']) {
            $pdo->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ?')
                ->execute([$row['new_email'], $row['user_id']]);
            $emailChanged = $row['new_email'];
        } else {
            $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$row['user_id']]);
        }
        $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$row['user_id']]);
        $verified = true;
    }
}

$pageTitle = 'Email verification — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6 text-center">
  <?php if ($verified): ?>
    <h1 class="text-xl font-semibold mb-2">✅ Email verified</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400"><?= $emailChanged ? 'Your login email has been changed to ' . e($emailChanged) . '.' : 'Thanks — your email address is confirmed.' ?></p>
  <?php else: ?>
    <h1 class="text-xl font-semibold mb-2">Invalid or expired link</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400">This verification link is invalid or has expired.<?= $user ? ' You can request a new one from your profile.' : '' ?></p>
  <?php endif; ?>
  <a href="/" class="inline-block mt-4 text-indigo-600 hover:underline text-sm">Back to home</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
