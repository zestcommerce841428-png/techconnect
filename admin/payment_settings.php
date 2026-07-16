<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payments/payments.php';
$pageTitle = 'Payment Settings — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

$fieldsByGateway = [
    'razorpay' => ['key_id' => 'Key ID', 'key_secret' => 'Key Secret', 'webhook_secret' => 'Webhook Secret'],
    'stripe' => ['secret_key' => 'Secret Key', 'publishable_key' => 'Publishable Key', 'webhook_secret' => 'Webhook Signing Secret'],
    'paypal' => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'webhook_id' => 'Webhook ID'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $gateway = $_POST['gateway'] ?? '';
    if (!isset($fieldsByGateway[$gateway])) {
        flash_set('error', 'Unknown gateway.');
        redirect('/admin/payment_settings');
    }
    $config = [];
    foreach ($fieldsByGateway[$gateway] as $key => $label) {
        $val = trim($_POST[$key] ?? '');
        // Blank field on save means "keep existing value" so re-saving other settings
        // doesn't force re-entering every secret every time.
        if ($val === '') continue;
        $config[$key] = $val;
    }
    $stmt = $pdo->prepare('SELECT config_encrypted FROM payment_settings WHERE gateway = ?');
    $stmt->execute([$gateway]);
    $existingEncrypted = $stmt->fetchColumn();
    $existing = $existingEncrypted ? json_decode(payments_decrypt($existingEncrypted) ?? '[]', true) : [];
    if (!is_array($existing)) $existing = [];
    $merged = array_merge($existing, $config);

    $mode = ($_POST['mode'] ?? 'test') === 'live' ? 'live' : 'test';
    $enabled = isset($_POST['enabled']) ? 1 : 0;

    $pdo->prepare('UPDATE payment_settings SET enabled = ?, mode = ?, config_encrypted = ? WHERE gateway = ?')
        ->execute([$enabled, $mode, payments_encrypt(json_encode($merged)), $gateway]);

    flash_set('success', ucfirst($gateway) . ' settings saved.');
    redirect('/admin/payment_settings');
}

$rows = [];
foreach ($pdo->query('SELECT gateway, enabled, mode, config_encrypted FROM payment_settings')->fetchAll() as $r) {
    $rows[$r['gateway']] = $r;
}
?>
<h1 class="text-2xl font-bold mb-2">Payment gateway settings</h1>
<p class="text-sm text-slate-600 mb-6">API keys are encrypted at rest (AES-256-GCM). Nothing is charged until a gateway is enabled here with valid live keys. Test mode is recommended until you've verified a full checkout end-to-end.</p>

<div class="space-y-6 max-w-2xl">
<?php foreach ($fieldsByGateway as $gateway => $fields): $row = $rows[$gateway] ?? ['enabled' => 0, 'mode' => 'test', 'config_encrypted' => null];
  $configured = $row['config_encrypted'] ? json_decode(payments_decrypt($row['config_encrypted']) ?? '[]', true) : [];
  if (!is_array($configured)) $configured = [];
?>
  <div class="bg-white border rounded-lg p-6">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-semibold capitalize"><?= e($gateway) ?></h2>
      <span class="text-xs px-2 py-1 rounded <?= $row['enabled'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>">
        <?= $row['enabled'] ? 'Enabled' : 'Disabled' ?>
      </span>
    </div>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="gateway" value="<?= e($gateway) ?>">
      <?php foreach ($fields as $key => $label): ?>
        <div>
          <label class="block text-sm font-medium mb-1"><?= e($label) ?><?= isset($configured[$key]) ? ' (currently set — leave blank to keep)' : '' ?></label>
          <input type="password" name="<?= e($key) ?>" autocomplete="off" placeholder="<?= isset($configured[$key]) ? '••••••••' : 'not set' ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
      <?php endforeach; ?>
      <div class="flex items-center gap-4">
        <label class="text-sm">Mode:
          <select name="mode" class="border rounded px-2 py-1 text-sm">
            <option value="test" <?= $row['mode'] === 'test' ? 'selected' : '' ?>>Test</option>
            <option value="live" <?= $row['mode'] === 'live' ? 'selected' : '' ?>>Live</option>
          </select>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="enabled" <?= $row['enabled'] ? 'checked' : '' ?>>
          Enable this gateway on checkout pages
        </label>
      </div>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save <?= e(ucfirst($gateway)) ?></button>
    </form>
  </div>
<?php endforeach; ?>
</div>

<h2 class="text-lg font-semibold mt-8 mb-2">Pricing</h2>
<div class="bg-white border rounded-lg p-6 max-w-md">
  <form method="post" action="/admin/site_settings" class="text-sm text-slate-600">
    Job listing and Pro membership prices are managed in <a href="/admin/site_settings" class="text-indigo-600 hover:underline">Site Settings</a> (job_listing_price_cents, pro_membership_price_cents, currency).
  </form>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
