<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Integrations — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$editableKeys = [
    'ga_measurement_id', 'recaptcha_site_key', 'recaptcha_secret_key',
    'turnstile_site_key', 'turnstile_secret_key', 'tawk_widget_id',
    'whatsapp_number', 'whatsapp_default_message',
    'telegram_bot_token', 'telegram_notify_chat_id', 'telegram_channel_url', 'telegram_group_url',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($editableKeys as $key) {
        $value = trim($_POST[$key] ?? '');
        $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
            ->execute([$key, $value, $value]);
    }
    $captchaProvider = in_array($_POST['captcha_provider'] ?? '', ['none', 'recaptcha', 'turnstile'], true) ? $_POST['captcha_provider'] : 'none';
    $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES ("captcha_provider", ?) ON DUPLICATE KEY UPDATE setting_value = ?')
        ->execute([$captchaProvider, $captchaProvider]);

    flash_set('success', 'Integration settings saved.');
    redirect('/admin/integrations');
}

$settings = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<h1 class="text-2xl font-bold mb-2">Integrations</h1>
<p class="text-sm text-slate-600 mb-6">Social login accounts are managed separately under <a href="/admin/social_login_settings" class="text-indigo-600 hover:underline">Social Login</a>. Payment gateways are under <a href="/admin/payment_settings" class="text-indigo-600 hover:underline">Payment Settings</a>.</p>

<form method="post" class="space-y-6 max-w-2xl">
  <?= csrf_field() ?>

  <fieldset class="bg-white border rounded-lg p-6">
    <legend class="text-sm font-semibold px-1">Google Analytics</legend>
    <label class="block text-sm font-medium mb-1 mt-2">Measurement ID (e.g. G-XXXXXXXXXX)</label>
    <input type="text" name="ga_measurement_id" value="<?= e($settings['ga_measurement_id'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
  </fieldset>

  <fieldset class="bg-white border rounded-lg p-6">
    <legend class="text-sm font-semibold px-1">Spam protection (captcha)</legend>
    <label class="block text-sm font-medium mb-1 mt-2">Provider</label>
    <select name="captcha_provider" class="border rounded px-3 py-2 text-sm mb-3">
      <option value="none" <?= ($settings['captcha_provider'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
      <option value="recaptcha" <?= ($settings['captcha_provider'] ?? '') === 'recaptcha' ? 'selected' : '' ?>>Google reCAPTCHA v3</option>
      <option value="turnstile" <?= ($settings['captcha_provider'] ?? '') === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile</option>
    </select>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium mb-1">reCAPTCHA Site Key</label>
        <input type="text" name="recaptcha_site_key" value="<?= e($settings['recaptcha_site_key'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">reCAPTCHA Secret Key</label>
        <input type="password" name="recaptcha_secret_key" value="<?= e($settings['recaptcha_secret_key'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Turnstile Site Key</label>
        <input type="text" name="turnstile_site_key" value="<?= e($settings['turnstile_site_key'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Turnstile Secret Key</label>
        <input type="password" name="turnstile_secret_key" value="<?= e($settings['turnstile_secret_key'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
    </div>
  </fieldset>

  <fieldset class="bg-white border rounded-lg p-6">
    <legend class="text-sm font-semibold px-1">Live chat (Tawk.to)</legend>
    <label class="block text-sm font-medium mb-1 mt-2">Widget path (from Tawk.to dashboard, e.g. 507f1.../default)</label>
    <input type="text" name="tawk_widget_id" value="<?= e($settings['tawk_widget_id'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
  </fieldset>

  <fieldset class="bg-white border rounded-lg p-6">
    <legend class="text-sm font-semibold px-1">WhatsApp chat button</legend>
    <div class="space-y-3 mt-2">
      <div>
        <label class="block text-xs font-medium mb-1">WhatsApp number (with country code, digits only)</label>
        <input type="text" name="whatsapp_number" placeholder="15551234567" value="<?= e($settings['whatsapp_number'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Default pre-filled message</label>
        <input type="text" name="whatsapp_default_message" value="<?= e($settings['whatsapp_default_message'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
    </div>
  </fieldset>

  <fieldset class="bg-white border rounded-lg p-6">
    <legend class="text-sm font-semibold px-1">Telegram</legend>
    <p class="text-xs text-slate-500 mb-3">Bot notifications alert you in Telegram when a new question is posted. Login widget settings are under Social Login.</p>
    <div class="space-y-3">
      <div>
        <label class="block text-xs font-medium mb-1">Notification Bot Token</label>
        <input type="password" name="telegram_bot_token" value="<?= e($settings['telegram_bot_token'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Notify Chat ID</label>
        <input type="text" name="telegram_notify_chat_id" value="<?= e($settings['telegram_notify_chat_id'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Channel URL (shown in footer)</label>
        <input type="url" name="telegram_channel_url" value="<?= e($settings['telegram_channel_url'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Group URL (shown in footer)</label>
        <input type="url" name="telegram_group_url" value="<?= e($settings['telegram_group_url'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
    </div>
  </fieldset>

  <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save integrations</button>
</form>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
