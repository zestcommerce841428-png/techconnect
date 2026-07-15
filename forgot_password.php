<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (rate_limit('forgot_password', 5, 600)) {
        $email = trim($_POST['email'] ?? '');
        $stmt = db()->prepare('SELECT id, username FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $upd = db()->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
            $upd->execute([$token, $expires, $row['id']]);
            $link = SITE_URL . '/reset_password.php?token=' . $token;
            send_mail($email, $row['username'], 'Reset your ' . SITE_NAME . ' password',
                '<p>Click the link below to reset your password (valid 1 hour):</p><p><a href="' . e($link) . '">' . e($link) . '</a></p>');
        }
    }
    $sent = true; // Always show the same message to avoid leaking which emails are registered
}

$pageTitle = 'Reset password — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-sm mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Reset your password</h1>
  <?php if ($sent): ?>
    <p class="text-sm text-slate-700">If that email is registered, a reset link has been sent.</p>
  <?php else: ?>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="email" required class="w-full border rounded px-3 py-2">
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded px-3 py-2">Send reset link</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
