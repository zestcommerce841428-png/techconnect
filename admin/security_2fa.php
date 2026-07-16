<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/NullQrCodeProvider.php';
use RobThree\Auth\TwoFactorAuth;

$pageTitle = 'Two-Factor Authentication — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();
$tfa = new TwoFactorAuth(new NullQrCodeProvider(), SITE_NAME);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'enable') {
        $secret = $_POST['secret'] ?? '';
        $code = $_POST['code'] ?? '';
        if ($secret && $tfa->verifyCode($secret, $code)) {
            $pdo->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?')->execute([$secret, $admin['id']]);
            flash_set('success', '2FA enabled on your account.');
        } else {
            flash_set('error', 'Invalid code. Please try again.');
        }
    } elseif ($action === 'disable') {
        $pdo->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?')->execute([$admin['id']]);
        flash_set('success', '2FA disabled.');
    }
    redirect('/admin/security_2fa');
}

$stmt = $pdo->prepare('SELECT totp_enabled FROM users WHERE id = ?');
$stmt->execute([$admin['id']]);
$enabled = (bool) $stmt->fetchColumn();

$newSecret = $enabled ? null : $tfa->createSecret();
?>
<h1 class="text-2xl font-bold mb-4">Two-factor authentication</h1>
<div class="bg-white border rounded-lg p-6 max-w-md">
  <?php if ($enabled): ?>
    <p class="text-sm text-green-700 mb-4">2FA is currently <strong>enabled</strong> on your account.</p>
    <form method="post" onsubmit="return confirm('Disable 2FA on your account?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="disable">
      <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded text-sm">Disable 2FA</button>
    </form>
  <?php else: ?>
    <p class="text-sm text-slate-600 mb-3">Scan this into Google Authenticator, Authy, or any TOTP app (manual entry — no QR image to keep this dependency-light):</p>
    <div class="bg-slate-50 border rounded p-3 mb-4">
      <div class="text-xs text-slate-500 mb-1">Account</div>
      <div class="font-mono text-sm mb-2"><?= e($admin['username']) ?> — <?= e(SITE_NAME) ?></div>
      <div class="text-xs text-slate-500 mb-1">Secret key</div>
      <div class="font-mono text-sm break-all"><?= e($newSecret) ?></div>
    </div>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="enable">
      <input type="hidden" name="secret" value="<?= e($newSecret) ?>">
      <div>
        <label class="block text-sm font-medium mb-1">Enter the 6-digit code from your app to confirm</label>
        <input type="text" name="code" required maxlength="6" pattern="[0-9]{6}" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Enable 2FA</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
