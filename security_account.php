<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        if (!rate_limit('change_password', 5, 3600)) {
            flash_set('error', 'Too many attempts. Please try again later.');
            redirect('/security_account');
        }
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $row->execute([$user['id']]);
        $hash = $row->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            flash_set('error', 'Current password is incorrect.');
        } elseif (mb_strlen($new) < 8) {
            flash_set('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash_set('error', "New passwords don't match.");
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            // Bumping session_version logged this session out too — re-establish it so the user isn't kicked immediately after changing their own password.
            $newVersion = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
            $newVersion->execute([$user['id']]);
            $_SESSION['session_version'] = (int) $newVersion->fetchColumn();
            // A leaked old password shouldn't leave a remember-me cookie able to silently re-auth.
            $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$user['id']]);
            @send_mail($user['email'], $user['username'], 'Your password was changed — ' . SITE_NAME,
                '<p>Hi ' . e($user['username']) . ',</p><p>Your password was just changed. If this wasn\'t you, reset your password immediately and contact support.</p>');
            flash_set('success', 'Password changed. You have been logged out of all other sessions.');
        }
        redirect('/security_account');
    }

    if ($action === 'change_email') {
        if (!rate_limit('change_email', 3, 3600)) {
            flash_set('error', 'Too many attempts. Please try again later.');
            redirect('/security_account');
        }
        $newEmail = trim($_POST['new_email'] ?? '');
        $password = $_POST['password'] ?? '';

        $row = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $row->execute([$user['id']]);
        $hash = $row->fetchColumn();

        if (!$hash || !password_verify($password, $hash)) {
            flash_set('error', 'Password is incorrect.');
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Please enter a valid email address.');
        } elseif (strcasecmp($newEmail, $user['email']) === 0) {
            flash_set('error', 'That\'s already your current email.');
        } else {
            $taken = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $taken->execute([$newEmail, $user['id']]);
            if ($taken->fetchColumn()) {
                flash_set('error', 'That email is already in use by another account.');
            } else {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('INSERT INTO email_verifications (user_id, token, new_email, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))
                    ON DUPLICATE KEY UPDATE token = VALUES(token), new_email = VALUES(new_email), expires_at = VALUES(expires_at)')
                    ->execute([$user['id'], $token, $newEmail]);
                $link = SITE_URL . '/verify_email?token=' . $token;
                @send_mail($newEmail, $user['username'], 'Confirm your new email — ' . SITE_NAME,
                    '<p>Hi ' . e($user['username']) . ',</p><p><a href="' . e($link) . '">Confirm this email address</a> to finish changing your ' . e(SITE_NAME) . ' login email (expires in 48 hours).</p>');
                flash_set('success', "We sent a confirmation link to {$newEmail}. Your current email stays active until you confirm.");
            }
        }
        redirect('/security_account');
    }

    if ($action === 'delete_passkey') {
        $pdo->prepare('DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?')
            ->execute([(int) ($_POST['id'] ?? 0), $user['id']]);
        flash_set('success', 'Passkey removed.');
        redirect('/security_account');
    }
}

$pendingEmail = $pdo->prepare('SELECT new_email FROM email_verifications WHERE user_id = ? AND new_email IS NOT NULL AND expires_at > NOW()');
$pendingEmail->execute([$user['id']]);
$pendingEmail = $pendingEmail->fetchColumn();

$passkeys = $pdo->prepare('SELECT id, label, created_at, last_used_at FROM webauthn_credentials WHERE user_id = ? ORDER BY created_at DESC');
$passkeys->execute([$user['id']]);
$passkeys = $passkeys->fetchAll();

$pageTitle = 'Account security — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="/assets/dist/js/passkey.js"></script>
<div class="max-w-lg mx-auto space-y-6">
  <h1 class="text-2xl font-bold">Account security</h1>

  <div class="card p-6">
    <h2 class="font-semibold mb-3">Change email</h2>
    <p class="text-sm text-slate-500 mb-3">Current: <strong><?= e($user['email']) ?></strong></p>
    <?php if ($pendingEmail): ?>
      <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-3">Confirmation pending for <strong><?= e($pendingEmail) ?></strong> — check that inbox to finish the change.</p>
    <?php endif; ?>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_email">
      <input type="email" name="new_email" required placeholder="New email address" class="w-full border rounded px-3 py-2 text-sm">
      <input type="password" name="password" required placeholder="Current password to confirm" class="w-full border rounded px-3 py-2 text-sm">
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Send confirmation link</button>
    </form>
  </div>

  <div class="card p-6">
    <h2 class="font-semibold mb-3">Change password</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <input type="password" name="current_password" required placeholder="Current password" class="w-full border rounded px-3 py-2 text-sm">
      <input type="password" name="new_password" required minlength="8" placeholder="New password (8+ characters)" class="w-full border rounded px-3 py-2 text-sm">
      <input type="password" name="confirm_password" required minlength="8" placeholder="Confirm new password" class="w-full border rounded px-3 py-2 text-sm">
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Change password</button>
    </form>
    <p class="text-xs text-slate-500 mt-2">Changing your password signs you out of every other device.</p>
  </div>

  <div class="card p-6">
    <h2 class="font-semibold mb-1">Passkeys</h2>
    <p class="text-sm text-slate-500 mb-3">Sign in with your fingerprint, face, or device PIN — no password needed. Backed by your device or browser's own passkey manager.</p>

    <div class="flex flex-wrap items-center gap-2 mb-4">
      <input type="text" data-passkey-label placeholder="Label (e.g. My laptop)" class="border rounded px-3 py-2 text-sm flex-1 min-w-0">
      <button type="button" data-passkey-add class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm shrink-0">+ Add a passkey</button>
    </div>
    <p data-passkey-add-status class="text-xs text-slate-500 mb-3"></p>

    <div class="divide-y border rounded-lg">
      <?php foreach ($passkeys as $pk): ?>
        <div class="p-3 text-sm flex items-center justify-between gap-4">
          <div>
            <div class="font-medium"><?= e($pk['label']) ?></div>
            <div class="text-xs text-slate-500">Added <?= time_ago($pk['created_at']) ?><?= $pk['last_used_at'] ? ' · last used ' . time_ago($pk['last_used_at']) : ' · never used' ?></div>
          </div>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $pk['id'] ?>">
            <button type="submit" name="action" value="delete_passkey" class="text-xs text-red-600 hover:underline">Remove</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$passkeys): ?><div class="p-3 text-sm text-slate-500">No passkeys yet.</div><?php endif; ?>
    </div>
  </div>

  <p class="text-sm text-slate-500">
    Also see <a href="/security_sessions" class="text-indigo-600 hover:underline">login activity &amp; sessions</a>.
    <?php if (in_array($user['role'], ['admin', 'moderator'], true)): ?>
      Staff accounts can also manage <a href="/admin/security_2fa" class="text-indigo-600 hover:underline">two-factor authentication</a>.
    <?php endif; ?>
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
