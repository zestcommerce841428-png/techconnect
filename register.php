<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/captcha.php';

if (current_user()) redirect('/');

if (setting('allow_registrations', '1') !== '1') {
    $pageTitle = 'Registrations closed — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="max-w-sm mx-auto bg-white border rounded-lg p-6 text-center text-slate-600">New registrations are temporarily closed. Please check back later.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (honeypot_tripped()) {
        // Bot filled the invisible field — pretend nothing happened.
        redirect('/');
    }
    if (!rate_limit('register', 5, 600) || !rate_limit_ip('register', 10, 3600)) {
        $errors[] = 'Too many attempts. Please try again later.';
    } elseif (!captcha_verify()) {
        $errors[] = 'Captcha verification failed. Please try again.';
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 characters (letters, numbers, underscore).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    require_once __DIR__ . '/includes/password_policy.php';
    foreach (validate_password($password, [$username, $email]) as $pwErr) {
        $errors[] = $pwErr;
    }

    if (!$errors) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $newUserId = (int) db()->lastInsertId();
        login_user($newUserId);

        // Email verification: token link valid for 48h. Registration still succeeds if mail fails.
        require_once __DIR__ . '/includes/mailer.php';
        $token = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))
                       ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)')
            ->execute([$newUserId, $token]);
        $link = SITE_URL . '/verify_email?token=' . $token;
        require_once __DIR__ . '/includes/email_template.php';
        @send_mail($email, $username, 'Verify your email — ' . SITE_NAME,
            email_layout('Welcome to ' . setting('site_name', SITE_NAME) . '!', [
                email_paragraph('Hi ' . $username . ','),
                email_paragraph('Thanks for joining. Confirm your email address to unlock everything — asking questions, posting answers and earning reputation.'),
                email_button('Verify my email', $link),
                email_muted('This link expires in 48 hours.'),
            ], 'Confirm your email to finish setting up your account'));

        require_once __DIR__ . '/includes/webhooks.php';
        fire_webhook('user.registered', ['id' => $newUserId, 'username' => $username]);

        db()->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "welcome", JSON_OBJECT())')
            ->execute([$newUserId]);

        flash_set('success', 'Welcome to ' . SITE_NAME . '! We sent a verification link to your email.');
        redirect('/index.php');
    }
}

$pageTitle = 'Join ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="min-h-[70vh] flex items-center justify-center bg-[radial-gradient(ellipse_at_top,theme(colors.indigo.50),transparent_60%)] dark:bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.08),transparent_60%)] -mx-4 px-4 rounded-2xl">
<div class="w-full max-w-sm card p-7">
  <div class="text-center mb-6">
    <a href="/" class="inline-block font-bold text-lg text-indigo-600">&larr; <?= e(setting('site_name', SITE_NAME)) ?></a>
  </div>
  <h1 class="text-xl font-semibold mb-1 text-center">Create your account</h1>
  <p class="text-sm text-slate-500 text-center mb-5">Free forever. Join the conversation in seconds.</p>
  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded-lg border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" class="space-y-4">
    <?= csrf_field() . honeypot_field() ?>
    <div>
      <label for="reg_username" class="block text-sm font-medium mb-1">Username</label>
      <input id="reg_username" type="text" name="username" required autofocus class="w-full border rounded-lg px-3 py-2.5" value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div>
      <label for="reg_email" class="block text-sm font-medium mb-1">Email</label>
      <input id="reg_email" type="email" name="email" required class="w-full border rounded-lg px-3 py-2.5" value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div>
      <label for="reg_password" class="block text-sm font-medium mb-1">Password</label>
      <input id="reg_password" type="password" name="password" required minlength="8" aria-describedby="reg_password_hint" class="w-full border rounded-lg px-3 py-2.5">
      <div class="h-1.5 mt-1.5 rounded bg-slate-200 overflow-hidden" aria-hidden="true">
        <div id="reg_pw_bar" class="h-full w-0 rounded transition-all"></div>
      </div>
      <p id="reg_password_hint" class="text-xs text-slate-400 mt-1" aria-live="polite">At least 8 characters.</p>
      <script>
      (function () {
        var pw = document.getElementById('reg_password'), bar = document.getElementById('reg_pw_bar'), hint = document.getElementById('reg_password_hint');
        var levels = [
          [0, '#ef4444', 'Too weak — add more characters.'],
          [30, '#ef4444', 'Weak — mix letters, numbers and symbols.'],
          [55, '#f59e0b', 'Okay — longer is stronger.'],
          [80, '#22c55e', 'Strong password.'],
          [100, '#16a34a', 'Very strong password.']
        ];
        pw.addEventListener('input', function () {
          var v = pw.value, score = 0;
          score += Math.min(40, v.length * 4);
          if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score += 15;
          if (/\d/.test(v)) score += 15;
          if (/[^A-Za-z0-9]/.test(v)) score += 20;
          if (v.length >= 14) score += 10;
          if (/^(.)\1+$/.test(v) || /^(0123|1234|abcd|qwer|pass|admin)/i.test(v)) score = Math.min(score, 20);
          var lvl = levels[0];
          levels.forEach(function (l) { if (score >= l[0]) lvl = l; });
          bar.style.width = Math.min(100, score) + '%';
          bar.style.background = lvl[1];
          hint.textContent = v ? lvl[2] : 'At least 8 characters.';
        });
      })();
      </script>
    </div>
    <?= captcha_field('register') ?>
    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-3 py-2.5">Create account</button>
  </form>
  <?php require __DIR__ . '/includes/social_login_buttons.php'; ?>
  <p class="text-sm mt-5 text-center">Already have an account? <a href="/login" class="text-indigo-600 hover:underline font-medium">Log in</a></p>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
