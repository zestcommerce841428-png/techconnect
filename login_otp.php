<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/trusted_device.php';

if (current_user()) redirect('/');

// Migration 030 not applied: say so honestly and point at password sign-in,
// rather than accepting an email and silently failing to send anything.
if (!otp_available()) {
    $pageTitle = 'Sign in with a code — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="min-h-[60vh] flex items-center justify-center">
      <div class="w-full max-w-sm card p-7 text-center">
        <div class="text-3xl mb-2">✉️</div>
        <h1 class="text-xl font-semibold mb-2">Code sign-in isn't available yet</h1>
        <p class="text-sm text-slate-500 mb-5">This sign-in method hasn't been switched on for this site yet. You can sign in with your password instead.</p>
        <a href="/login" class="block w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-3 py-2.5">Sign in with a password</a>
        <a href="/forgot_password" class="block mt-3 text-sm text-indigo-600">Forgot your password?</a>
      </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
$stage = !empty($_SESSION['otp_user_id']) ? 'verify' : 'request';
$sentTo = $_SESSION['otp_email_masked'] ?? '';

/** name@example.com -> n••e@example.com — confirms the address without exposing it. */
function mask_email(string $email): string
{
    [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $visible = mb_substr($user, 0, 1) . str_repeat('•', max(1, mb_strlen($user) - 2)) . mb_substr($user, -1);
    return $visible . '@' . $domain;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'request') {
        if (honeypot_tripped()) redirect('/');
        $email = trim($_POST['email'] ?? '');

        // Rate-limit before any lookup so this can't be used to probe addresses.
        if (!rate_limit('otp_request', 4, 900) || !rate_limit_ip('otp_request', 8, 3600)) {
            $errors[] = 'Too many code requests. Please wait a few minutes.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $stmt = db()->prepare('SELECT id, username, email FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                otp_issue((int) $user['id'], $user['email'], $user['username']);
                $_SESSION['otp_user_id'] = (int) $user['id'];
                $_SESSION['otp_email_masked'] = mask_email($user['email']);
            } else {
                // Unknown address: still advance to the code screen so this page
                // never reveals which emails are registered.
                $_SESSION['otp_user_id'] = 0;
                $_SESSION['otp_email_masked'] = mask_email($email);
            }
            redirect('/login_otp');
        }
    } elseif ($action === 'verify' && isset($_SESSION['otp_user_id'])) {
        if (!rate_limit('otp_verify', 10, 900)) {
            $errors[] = 'Too many attempts. Please request a new code.';
        } else {
            $userId = (int) $_SESSION['otp_user_id'];
            // userId 0 = the unknown-address case above; keep the timing and
            // messaging identical to a genuinely wrong code.
            [$ok, $msg] = $userId > 0 ? otp_verify($userId, $_POST['code'] ?? '') : [false, 'Incorrect code.'];
            if ($ok) {
                unset($_SESSION['otp_user_id'], $_SESSION['otp_email_masked']);
                login_user($userId, !empty($_POST['remember']));
                if (!empty($_POST['trust_device'])) {
                    trust_this_device($userId);
                }
                redirect('/');
            }
            $errors[] = $msg;
        }
    } elseif ($action === 'restart') {
        unset($_SESSION['otp_user_id'], $_SESSION['otp_email_masked']);
        redirect('/login_otp');
    }
}

$pageTitle = 'Sign in with a code — ' . SITE_NAME;
$pageDescription = 'Sign in to ' . SITE_NAME . ' with a one-time code sent to your email — no password needed.';
require __DIR__ . '/includes/header.php';
?>
<div class="min-h-[70vh] flex items-center justify-center bg-[radial-gradient(ellipse_at_top,theme(colors.indigo.50),transparent_60%)] dark:bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.08),transparent_60%)] -mx-4 px-4 rounded-2xl">
<div class="w-full max-w-sm card p-7">
  <div class="text-center mb-6">
    <a href="/" class="inline-block font-bold text-lg text-indigo-600">&larr; <?= e(setting('site_name', SITE_NAME)) ?></a>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded-lg border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if ($stage === 'verify'): ?>
    <h1 class="text-xl font-semibold mb-1 text-center">Check your email</h1>
    <p class="text-sm text-slate-500 text-center mb-5">
      We sent a 6-digit code to <strong><?= e($sentTo) ?></strong>. It expires in 10 minutes.
    </p>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div>
        <label for="otp_code" class="sr-only">6-digit code</label>
        <input id="otp_code" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]{6}" maxlength="6" autofocus placeholder="000000"
               class="w-full border rounded-lg px-3 py-2.5 text-center text-2xl tracking-[0.4em] font-mono">
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="remember" value="1"> Keep me signed in for 30 days
      </label>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="trust_device" value="1"> Trust this device
      </label>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-3 py-2.5">Sign in</button>
    </form>
    <form method="post" class="mt-4 text-center">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="restart">
      <button type="submit" class="text-sm text-indigo-600">Use a different email</button>
    </form>
  <?php else: ?>
    <h1 class="text-xl font-semibold mb-1 text-center">Sign in with a code</h1>
    <p class="text-sm text-slate-500 text-center mb-5">No password needed — we'll email you a one-time code.</p>
    <form method="post" class="space-y-4">
      <?= csrf_field() . honeypot_field() ?>
      <input type="hidden" name="action" value="request">
      <div>
        <label for="otp_email" class="block text-sm font-medium mb-1">Email</label>
        <input id="otp_email" type="email" name="email" required autofocus autocomplete="email"
               class="w-full border rounded-lg px-3 py-2.5">
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-3 py-2.5">Email me a code</button>
    </form>
  <?php endif; ?>

  <p class="text-sm mt-5 text-center text-slate-500">
    <a href="/login" class="text-indigo-600 font-medium">Sign in with a password</a>
    &middot;
    <a href="/register" class="text-indigo-600 font-medium">Create account</a>
  </p>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
