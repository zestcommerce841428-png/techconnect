<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Site Settings — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$editableKeys = ['site_name', 'tagline', 'footer_text', 'social_twitter', 'social_facebook', 'social_linkedin', 'social_instagram', 'max_upload_mb', 'job_listing_price_cents', 'pro_membership_price_cents', 'currency'];
$checkboxKeys = ['allow_registrations', 'maintenance_mode'];

function save_upload_setting(string $field, string $settingKey, PDO $pdo): void
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return;
    }
    $file = $_FILES[$field];
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime]) || $file['size'] > 1024 * 1024) {
        flash_set('error', 'Invalid branding image (png/jpg/svg/ico under 1MB only).');
        return;
    }
    $destDir = __DIR__ . '/../uploads/branding';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $filename = $settingKey . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename);
    $publicPath = '/uploads/branding/' . $filename;
    $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
        ->execute([$settingKey, $publicPath, $publicPath]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($editableKeys as $key) {
        $value = trim($_POST[$key] ?? '');
        $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
            ->execute([$key, $value, $value]);
    }
    foreach ($checkboxKeys as $key) {
        $value = isset($_POST[$key]) ? '1' : '0';
        $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
            ->execute([$key, $value, $value]);
    }
    save_upload_setting('logo_file', 'logo_path', $pdo);
    save_upload_setting('favicon_file', 'favicon_path', $pdo);

    flash_set('success', 'Settings updated.');
    redirect('/admin/site_settings');
}

$settings = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<h1 class="text-2xl font-bold mb-4">Site settings & branding</h1>
<div class="bg-white border rounded-lg p-6 max-w-xl">
  <form method="post" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Site name</label>
      <input type="text" name="site_name" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($settings['site_name'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Tagline</label>
      <input type="text" name="tagline" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($settings['tagline'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Logo</label>
      <?php if (!empty($settings['logo_path'])): ?><img src="<?= e($settings['logo_path']) ?>" alt="Current site logo" class="h-8 mb-2"><?php endif; ?>
      <input type="file" name="logo_file" accept="image/png,image/jpeg,image/svg+xml" class="text-sm">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Favicon</label>
      <?php if (!empty($settings['favicon_path'])): ?><img src="<?= e($settings['favicon_path']) ?>" alt="Current favicon" class="h-6 mb-2"><?php endif; ?>
      <input type="file" name="favicon_file" accept="image/png,image/x-icon,image/svg+xml" class="text-sm">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Footer text</label>
      <input type="text" name="footer_text" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($settings['footer_text'] ?? '') ?>">
    </div>
    <fieldset class="border rounded p-3">
      <legend class="text-sm font-medium px-1">Feature flags</legend>
      <div class="space-y-2">
        <div>
          <label class="block text-sm font-medium mb-1">Max upload size (MB)</label>
          <input type="number" name="max_upload_mb" min="1" max="20" class="w-24 border rounded px-3 py-2 text-sm" value="<?= e($settings['max_upload_mb'] ?? '5') ?>">
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="allow_registrations" <?= ($settings['allow_registrations'] ?? '1') === '1' ? 'checked' : '' ?>>
          Allow new registrations
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
          Maintenance mode (site shows a "down for maintenance" page to non-admins)
        </label>
      </div>
    </fieldset>
    <fieldset class="border rounded p-3">
      <legend class="text-sm font-medium px-1">Monetization pricing</legend>
      <div class="space-y-2">
        <div>
          <label class="block text-sm font-medium mb-1">Currency (3-letter code)</label>
          <input type="text" name="currency" maxlength="3" class="w-24 border rounded px-3 py-2 text-sm uppercase" value="<?= e($settings['currency'] ?? 'USD') ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Paid job listing price (cents)</label>
          <input type="number" name="job_listing_price_cents" min="0" class="w-32 border rounded px-3 py-2 text-sm" value="<?= e($settings['job_listing_price_cents'] ?? '2000') ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Pro membership price (cents / month)</label>
          <input type="number" name="pro_membership_price_cents" min="0" class="w-32 border rounded px-3 py-2 text-sm" value="<?= e($settings['pro_membership_price_cents'] ?? '500') ?>">
        </div>
        <p class="text-xs text-slate-500">Gateway API keys are managed in <a href="/admin/payment_settings" class="text-indigo-600 hover:underline">Payment Settings</a>.</p>
      </div>
    </fieldset>
    <fieldset class="border rounded p-3">
      <legend class="text-sm font-medium px-1">Social links</legend>
      <div class="space-y-2">
        <input type="url" name="social_twitter" placeholder="Twitter/X URL" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($settings['social_twitter'] ?? '') ?>">
        <input type="url" name="social_facebook" placeholder="Facebook URL" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($settings['social_facebook'] ?? '') ?>">
        <input type="url" name="social_linkedin" placeholder="LinkedIn URL" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($settings['social_linkedin'] ?? '') ?>">
        <input type="url" name="social_instagram" placeholder="Instagram URL" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($settings['social_instagram'] ?? '') ?>">
      </div>
    </fieldset>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save settings</button>
  </form>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
