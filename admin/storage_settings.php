<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Storage — Admin';
require __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/storage/storage.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Admins only.');
}

$pdo = db();
$testResult = null;

/** Settings this page owns. Secrets are write-only in the UI (never echoed back). */
$fields = [
    's3_endpoint' => ['Endpoint URL', 'https://<account-id>.r2.cloudflarestorage.com', false],
    's3_region' => ['Region', 'auto (Cloudflare R2) or ap-south-1 (AWS Mumbai)', false],
    's3_bucket' => ['Bucket name', 'puchonow-uploads', false],
    's3_access_key' => ['Access key ID', '', false],
    's3_secret_key' => ['Secret access key', '', true],
    's3_public_url' => ['Public / CDN base URL', 'https://cdn.puchonow.in (leave blank to use the bucket URL)', false],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $driver = array_key_exists($_POST['storage_driver'] ?? '', storage_driver_defs()) ? $_POST['storage_driver'] : 'local';
        $save = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $save->execute(['storage_driver', $driver]);

        foreach ($fields as $key => [$label, $hint, $isSecret]) {
            $val = trim($_POST[$key] ?? '');
            // Blank secret = "leave unchanged", so re-saving the form never wipes it.
            if ($isSecret && $val === '') {
                continue;
            }
            $save->execute([$key, $val]);
        }
        audit_log($admin['id'], 'storage_settings_updated', 'settings', null, 'driver=' . $driver);
        flash_set('success', 'Storage settings saved.');
        redirect('/admin/storage_settings');
    }

    if ($action === 'test') {
        // Test what is saved, not what is typed — proves the live config works.
        [$ok, $msg] = storage()->test();
        $testResult = ['ok' => $ok, 'msg' => $msg];
        audit_log($admin['id'], 'storage_tested', 'settings', null, $ok ? 'passed' : 'failed: ' . $msg);
    }
}

$currentDriver = setting('storage_driver', 'local');
$active = storage();
$activeName = $active instanceof S3Storage ? 'S3-compatible' : 'Local disk';
$fellBack = $currentDriver === 's3' && !($active instanceof S3Storage);
?>
<h1 class="text-2xl font-bold mb-1">☁️ Storage</h1>
<p class="text-sm text-slate-500 mb-5">
  Where uploaded images and files are stored. Local disk works out of the box; moving to object storage
  frees your hosting quota and serves media from a CDN.
</p>

<?php if ($testResult): ?>
  <div class="mb-4 rounded border px-4 py-3 text-sm <?= $testResult['ok'] ? 'border-green-300 bg-green-50 text-green-800' : 'border-red-300 bg-red-50 text-red-800' ?>">
    <?= $testResult['ok'] ? '✅ ' : '❌ ' ?><?= e($testResult['msg']) ?>
  </div>
<?php endif; ?>

<?php if ($fellBack): ?>
  <div class="mb-4 rounded border border-amber-300 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
    S3 is selected but the configuration is incomplete, so uploads are still going to local disk.
    Fill in endpoint, bucket, access key and secret key below.
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <form method="post" class="bg-white border rounded-lg p-5 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">

      <div>
        <label for="storage_driver" class="block text-sm font-medium mb-1">Storage backend</label>
        <select id="storage_driver" name="storage_driver" class="w-full border rounded px-3 py-2 text-sm"
                onchange="document.getElementById('s3-config').classList.toggle('hidden', this.value !== 's3')">
          <?php foreach (storage_driver_defs() as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $currentDriver === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="s3-config" class="<?= $currentDriver === 's3' ? '' : 'hidden' ?> space-y-4 border-t pt-4">
        <?php foreach ($fields as $key => [$label, $hint, $isSecret]): ?>
          <div>
            <label for="<?= e($key) ?>" class="block text-sm font-medium mb-1"><?= e($label) ?></label>
            <input id="<?= e($key) ?>" type="<?= $isSecret ? 'password' : 'text' ?>" name="<?= e($key) ?>"
                   value="<?= $isSecret ? '' : e(setting($key)) ?>"
                   placeholder="<?= e($isSecret && setting($key) !== '' ? '•••••••• (saved — leave blank to keep)' : $hint) ?>"
                   autocomplete="off" class="w-full border rounded px-3 py-2 text-sm font-mono">
            <?php if ($hint && !$isSecret): ?><p class="text-xs text-slate-400 mt-1"><?= e($hint) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm font-medium">Save settings</button>
      </div>
    </form>

    <form method="post" class="mt-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <button type="submit" class="border px-4 py-2 rounded text-sm bg-white hover:bg-slate-50">🔌 Test connection</button>
      <span class="text-xs text-slate-400 ml-2">Writes, reads and deletes a real test object using the saved settings.</span>
    </form>
  </div>

  <div class="lg:col-span-1 space-y-4">
    <div class="bg-white border rounded-lg p-4">
      <h2 class="text-sm font-semibold mb-2">Currently active</h2>
      <p class="text-sm"><span class="inline-block w-2 h-2 rounded-full <?= $fellBack ? 'bg-amber-500' : 'bg-green-500' ?> mr-1.5"></span><?= e($activeName) ?></p>
      <p class="text-xs text-slate-500 mt-2">New uploads go here. Existing files stay where they were written — switching backends does not migrate old media.</p>
    </div>

    <div class="bg-white border rounded-lg p-4">
      <h2 class="text-sm font-semibold mb-2">Works with</h2>
      <ul class="text-xs text-slate-600 space-y-1">
        <li>• Cloudflare R2 <span class="text-slate-400">— no egress fees</span></li>
        <li>• AWS S3</li>
        <li>• DigitalOcean Spaces</li>
        <li>• Backblaze B2 <span class="text-slate-400">— S3 endpoint</span></li>
        <li>• Wasabi</li>
        <li>• MinIO <span class="text-slate-400">— self-hosted</span></li>
      </ul>
      <p class="text-xs text-slate-400 mt-2">They all speak the S3 API, so one driver covers every one — only the endpoint changes.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
