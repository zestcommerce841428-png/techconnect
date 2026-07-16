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
            // Store only a hash — a leaked/backed-up users table must not contain usable reset links.
            $upd->execute([hash('sha256', $token), $expires, $row['id']]);
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
<div class="min-h-[70vh] flex items-center justify-center bg-[radial-gradient(ellipse_at_top,theme(colors.indigo.50),transparent_60%)] dark:bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.08),transparent_60%)] -mx-4 px-4 rounded-2xl">
<div class="w-full max-w-sm card p-7">
  <div class="text-center mb-6">
    <a href="/" class="inline-block font-bold text-lg text-indigo-600">&larr; <?= e(setting('site_name', SITE_NAME)) ?></a>
  </div>
  <h1 class="text-xl font-semibold mb-1 text-center">Reset your password</h1>
  <?php if ($sent): ?>
    <p class="text-sm text-slate-600 text-center mt-4">If that email is registered, a reset link has been sent. Check your inbox.</p>
    <a href="/login" class="block text-center text-sm text-indigo-600 hover:underline font-medium mt-5">Back to log in</a>
  <?php else: ?>
    <p class="text-sm text-slate-500 text-center mb-5">Enter your email and we'll send you a reset link.</p>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <div>
        <label for="fp_email" class="block text-sm font-medium mb-1">Email</label>
        <input id="fp_email" type="email" name="email" required autofocus class="w-full border rounded-lg px-3 py-2.5">
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-3 py-2.5">Send reset link</button>
    </form>
  <?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
