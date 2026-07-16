<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/NullQrCodeProvider.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/trusted_device.php';
use RobThree\Auth\TwoFactorAuth;

if (current_user()) redirect('/');

$errors = [];
$pendingUserId = $_SESSION['pending_2fa_user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['totp_code']) && $pendingUserId) {
        if (!rate_limit('totp_verify', 8, 300)) {
            $errors[] = 'Too many attempts. Please wait a few minutes.';
        } else {
            $stmt = db()->prepare('SELECT totp_secret FROM users WHERE id = ?');
            $stmt->execute([$pendingUserId]);
            $secret = $stmt->fetchColumn();
            $tfa = new TwoFactorAuth(new NullQrCodeProvider(), SITE_NAME);
            if ($secret && $tfa->verifyCode($secret, trim($_POST['totp_code']))) {
                unset($_SESSION['pending_2fa_user_id']);
                $rememberMe = !empty($_SESSION['pending_2fa_remember']);
                unset($_SESSION['pending_2fa_remember']);
                login_user((int) $pendingUserId, $rememberMe);
                if (!empty($_POST['trust_device'])) {
                    trust_this_device((int) $pendingUserId);
                }
                redirect('/');
            }
            $errors[] = 'Invalid authentication code.';
        }
    } elseif (!rate_limit('login', 8, 300) || !rate_limit_ip('login', 20, 900)) {
        $errors[] = 'Too many login attempts. Please wait a few minutes.';
    } else {
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        $stmt = db()->prepare('SELECT id, password_hash, totp_enabled FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$identity, $identity]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            if ($row['totp_enabled'] && !is_trusted_device((int) $row['id'])) {
                $_SESSION['pending_2fa_user_id'] = (int) $row['id'];
                $_SESSION['pending_2fa_remember'] = $remember;
                $pendingUserId = (int) $row['id'];
            } else {
                login_user((int) $row['id'], $remember);
                redirect('/');
            }
        } else {
            log_warn('failed_login', ['identity' => $identity]);
            $errors[] = 'Incorrect username/email or password.';
        }
    }
}

$pageTitle = 'Log in — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="/assets/dist/js/passkey.js"></script>
<div class="min-h-[70vh] flex items-center justify-center bg-[radial-gradient(ellipse_at_top,theme(colors.indigo.50),transparent_60%)] dark:bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.08),transparent_60%)] -mx-4 px-4 rounded-2xl">
<div class="w-full max-w-sm card p-7">
  <div class="text-center mb-6">
    <a href="/" class="inline-block font-bold text-lg text-indigo-600">&larr; <?= e(setting('site_name', SITE_NAME)) ?></a>
  </div>
  <?php if (!$pendingUserId): ?>
    <button type="button" data-passkey-login class="w-full flex items-center justify-center gap-2 border rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 mb-3">
      🔑 Sign in with a passkey
    </button>
    <a href="/login_otp" class="w-full flex items-center justify-center gap-2 border rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 mb-3">
      ✉️ Email me a sign-in code
    </a>
    <p data-passkey-status class="text-xs text-center text-slate-500 mb-4"></p>
    <div class="flex items-center gap-3 text-xs text-slate-400 mb-4">
      <span class="flex-1 border-t border-slate-200 dark:border-slate-700"></span>
      or use a password
      <span class="flex-1 border-t border-slate-200 dark:border-slate-700"></span>
    </div>
  <?php endif; ?>
  <?php if ($pendingUserId): ?>
    <h1 class="text-xl font-semibold mb-1 text-center">Two-factor verification</h1>
    <p class="text-sm text-slate-500 text-center mb-5">Enter the 6-digit code from your authenticator app.</p>
    <?php foreach ($errors as $err): ?>
      <div class="mb-3 rounded-lg border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <label for="totp_code" class="sr-only">6-digit authentication code</label>
      <input id="totp_code" type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}" autofocus placeholder="6-digit code" class="w-full border rounded-lg px-3 py-2.5 text-center text-lg tracking-[0.3em]">
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="trust_device" value="1"> Trust this device for 30 days (skip this step next time)
      </label>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-3 py-2.5">Verify</button>
    </form>
  <?php else: ?>
    <h1 class="text-xl font-semibold mb-1 text-center">Welcome back</h1>
    <p class="text-sm text-slate-500 text-center mb-5">Log in to continue to <?= e(setting('site_name', SITE_NAME)) ?>.</p>
    <?php foreach ($errors as $err): ?>
      <div class="mb-3 rounded-lg border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <div>
        <label for="identity" class="block text-sm font-medium mb-1">Username or email</label>
        <input id="identity" type="text" name="identity" required autofocus class="w-full border rounded-lg px-3 py-2.5">
      </div>
      <div>
        <label for="password" class="block text-sm font-medium mb-1">Password</label>
        <input id="password" type="password" name="password" required class="w-full border rounded-lg px-3 py-2.5">
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="remember" value="1"> Remember me for 30 days
      </label>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-3 py-2.5">Log in</button>
    </form>
    <?php require __DIR__ . '/includes/social_login_buttons.php'; ?>
    <p class="text-sm mt-5 flex justify-between">
      <a href="/register" class="text-indigo-600 hover:underline font-medium">Create account</a>
      <a href="/forgot_password" class="text-indigo-600 hover:underline">Forgot password?</a>
    </p>
  <?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
