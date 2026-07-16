<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/oauth/oauth.php';
$pageTitle = 'Social Login — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$defs = oauth_provider_defs();
$defs['telegram'] = [
    'label' => 'Telegram (Login Widget)',
    'fields' => ['bot_token' => 'Bot Token', 'bot_username' => 'Bot Username (without @)'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $provider = $_POST['provider'] ?? '';
    if (!isset($defs[$provider])) {
        flash_set('error', 'Unknown provider.');
        redirect('/admin/social_login_settings');
    }
    $stmt = $pdo->prepare('SELECT config_encrypted FROM oauth_settings WHERE provider = ?');
    $stmt->execute([$provider]);
    $existing = json_decode(payments_decrypt($stmt->fetchColumn()) ?? '[]', true);
    if (!is_array($existing)) $existing = [];

    $config = [];
    foreach ($defs[$provider]['fields'] as $key => $label) {
        $val = trim($_POST[$key] ?? '');
        if ($val === '') continue; // blank = keep existing secret
        $config[$key] = $val;
    }
    $merged = array_merge($existing, $config);
    $enabled = isset($_POST['enabled']) ? 1 : 0;

    $pdo->prepare('UPDATE oauth_settings SET enabled = ?, config_encrypted = ? WHERE provider = ?')
        ->execute([$enabled, payments_encrypt(json_encode($merged)), $provider]);

    if ($provider === 'telegram' && !empty($merged['bot_username'])) {
        $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES ("telegram_login_bot_username", ?) ON DUPLICATE KEY UPDATE setting_value = ?')
            ->execute([$merged['bot_username'], $merged['bot_username']]);
    }

    flash_set('success', ucfirst($provider) . ' login settings saved.');
    redirect('/admin/social_login_settings');
}

$rows = [];
foreach ($pdo->query('SELECT provider, enabled, config_encrypted FROM oauth_settings')->fetchAll() as $r) {
    $rows[$r['provider']] = $r;
}
?>
<h1 class="text-2xl font-bold mb-2">Social login (sign in / sign up)</h1>
<p class="text-sm text-slate-600 mb-6">Each provider needs a real OAuth app registered on that platform, with the redirect URI set to
  <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs"><?= e(SITE_URL) ?>/oauth_callback?provider=&lt;name&gt;</code>
  (Telegram uses its own bot-based widget instead).</p>

<div class="space-y-6 max-w-2xl">
<?php foreach ($defs as $provider => $def): $row = $rows[$provider] ?? ['enabled' => 0, 'config_encrypted' => null];
  $configured = $row['config_encrypted'] ? json_decode(payments_decrypt($row['config_encrypted']) ?? '[]', true) : [];
  if (!is_array($configured)) $configured = [];
?>
  <div class="bg-white border rounded-lg p-6">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-semibold"><?= e($def['label']) ?></h2>
      <span class="text-xs px-2 py-1 rounded <?= $row['enabled'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>">
        <?= $row['enabled'] ? 'Enabled' : 'Disabled' ?>
      </span>
    </div>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="provider" value="<?= e($provider) ?>">
      <?php foreach ($def['fields'] as $key => $label): ?>
        <div>
          <label class="block text-sm font-medium mb-1"><?= e($label) ?><?= isset($configured[$key]) ? ' (set — leave blank to keep)' : '' ?></label>
          <input type="<?= str_contains($key, 'secret') || str_contains($key, 'token') ? 'password' : 'text' ?>" name="<?= e($key) ?>" autocomplete="off"
                 placeholder="<?= isset($configured[$key]) ? '••••••••' : 'not set' ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
      <?php endforeach; ?>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="enabled" <?= $row['enabled'] ? 'checked' : '' ?>>
        Show "Continue with <?= e($def['label']) ?>" on login/register
      </label>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save <?= e($def['label']) ?></button>
    </form>
  </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
