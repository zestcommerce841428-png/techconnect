<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'logout_others') {
        $pdo->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$user['id']]);
        // Keep the current browser logged in by bumping this session to match the new version.
        $newVersion = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
        $newVersion->execute([$user['id']]);
        $_SESSION['session_version'] = (int) $newVersion->fetchColumn();
        // Also revoke every remember-me token so a stale device can't silently
        // re-authenticate its way past the session-version check above.
        $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$user['id']]);
        flash_set('success', 'You have been logged out of all other sessions. This browser stays signed in.');
    } elseif ($action === 'revoke_trusted_device') {
        $pdo->prepare('DELETE FROM trusted_devices WHERE id = ? AND user_id = ?')->execute([(int) ($_POST['id'] ?? 0), $user['id']]);
        flash_set('success', 'That device will need to verify 2FA again next time.');
    }
    redirect('/security_sessions');
}

$history = $pdo->prepare('SELECT * FROM login_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$history->execute([$user['id']]);
$history = $history->fetchAll();

$trustedDevices = $pdo->prepare('SELECT * FROM trusted_devices WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC');
$trustedDevices->execute([$user['id']]);
$trustedDevices = $trustedDevices->fetchAll();

$pageTitle = 'Login activity & sessions — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold mb-1">Login activity &amp; sessions</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Review recent sign-ins to your account, and sign out everywhere else if something looks unfamiliar.</p>

  <div class="card p-4 mb-6 flex items-center justify-between gap-4">
    <div>
      <p class="text-sm font-medium">Log out of all other sessions</p>
      <p class="text-xs text-slate-500">Signs out every other browser and device. This one stays signed in.</p>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="logout_others">
      <button type="submit" onclick="return confirm('Log out everywhere else?')" class="text-sm bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded shrink-0">Log out others</button>
    </form>
  </div>

  <h2 class="font-semibold text-sm mb-2">Recent sign-ins</h2>
  <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y dark:divide-slate-800">
    <?php foreach ($history as $h): ?>
      <div class="p-3 text-sm flex items-center justify-between gap-4">
        <div>
          <div class="font-medium"><?= e($h['ip_address'] ?? 'Unknown IP') ?></div>
          <div class="text-xs text-slate-500 truncate max-w-md"><?= e($h['user_agent'] ?? 'Unknown device') ?></div>
        </div>
        <div class="text-xs text-slate-400 shrink-0"><?= time_ago($h['created_at']) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$history): ?><div class="p-3 text-sm text-slate-500">No login history recorded yet.</div><?php endif; ?>
  </div>

  <?php if ($trustedDevices): ?>
    <h2 class="font-semibold text-sm mb-2 mt-6">Trusted devices (2FA skipped)</h2>
    <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y dark:divide-slate-800">
      <?php foreach ($trustedDevices as $d): ?>
        <div class="p-3 text-sm flex items-center justify-between gap-4">
          <div>
            <div class="font-medium truncate max-w-md"><?= e($d['label'] ?? 'Unknown device') ?></div>
            <div class="text-xs text-slate-500">Trusted <?= time_ago($d['created_at']) ?><?= $d['last_used_at'] ? ' · last used ' . time_ago($d['last_used_at']) : '' ?></div>
          </div>
          <form method="post" class="shrink-0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="revoke_trusted_device">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button type="submit" class="text-xs text-red-600 hover:underline">Revoke</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
