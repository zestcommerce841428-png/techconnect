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

/** Cost-control settings — these decide how much you actually pay. */
$costFields = [
    'image_optimize_enabled' => ['Optimise images on upload', 'checkbox', 'Downscale and recompress before storing. Typically saves 85-95% — charged twice (storage + every view), so this is the biggest cost lever.'],
    'image_prefer_webp' => ['Convert to WebP', 'checkbox', 'About 30% smaller than JPEG at the same quality. Supported by every browser since 2020.'],
    'image_max_dimension' => ['Max image dimension (px)', 'number', 'Longest edge. 1600 suits a ~800px content column on 2x screens. Lower = cheaper.'],
    'image_quality' => ['Image quality (40-100)', 'number', '82 is visually lossless for photos. Below 70 shows artefacts.'],
    'max_upload_mb' => ['Max upload size (MB)', 'number', 'Hard ceiling per file, enforced before any processing.'],
];

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
        // Cost controls. Checkboxes are absent from POST when unticked, so they
        // must be written explicitly rather than skipped.
        foreach ($costFields as $key => [$label, $type, $hint]) {
            if ($type === 'checkbox') {
                $save->execute([$key, isset($_POST[$key]) ? '1' : '0']);
            } else {
                $val = (int) ($_POST[$key] ?? 0);
                $val = match ($key) {
                    'image_max_dimension' => min(4000, max(200, $val)),
                    'image_quality' => min(100, max(40, $val)),
                    'max_upload_mb' => min(50, max(1, $val)),
                    default => $val,
                };
                $save->execute([$key, (string) $val]);
            }
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

require_once __DIR__ . '/../includes/image_optimizer.php'; // format_bytes()

// ---- Real usage, straight from the uploads ledger ----------------------------
$usage = ['files' => 0, 'bytes' => 0, 'bytes_30d' => 0];
try {
    $row = $pdo->query('SELECT COUNT(*) files, COALESCE(SUM(size),0) bytes FROM uploads')->fetch();
    $usage['files'] = (int) $row['files'];
    $usage['bytes'] = (int) $row['bytes'];
    $usage['bytes_30d'] = (int) $pdo->query('SELECT COALESCE(SUM(size),0) FROM uploads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
} catch (Throwable $e) {
    // uploads table shape differs / missing — usage panel just shows zeros
}

$gb = $usage['bytes'] / 1073741824;
$growthGbMonth = $usage['bytes_30d'] / 1073741824;
// Egress assumption: each stored byte served ~5x/month. Crude, but it is the
// term that actually dominates S3 bills and is invisible until it arrives.
$egressGb = $gb * 5;

/**
 * Published list prices (USD), Jul 2026. Storage $/GB/mo, egress $/GB.
 * R2's zero egress is the entire reason it is recommended for a media-heavy
 * community site — egress, not storage, is what makes S3 bills explode.
 */
$providers = [
    'Cloudflare R2' => ['store' => 0.015, 'egress' => 0.0, 'note' => 'No egress fees — best for public media'],
    'Backblaze B2' => ['store' => 0.006, 'egress' => 0.01, 'note' => 'Cheapest storage; free egress via Cloudflare'],
    'Wasabi' => ['store' => 0.0069, 'egress' => 0.0, 'note' => 'No egress, but 90-day minimum retention'],
    'DO Spaces' => ['store' => 0.02, 'egress' => 0.01, 'note' => '$5/mo includes 250GB + 1TB transfer'],
    'AWS S3 (Mumbai)' => ['store' => 0.025, 'egress' => 0.1093, 'note' => 'Egress is ~7x the storage cost at 5x reads'],
];
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
        <?php
        /**
         * Provider presets. Every one of these speaks the S3 API, so the only
         * thing that actually differs is the endpoint URL shape and the region
         * token — which is exactly the part people get wrong and then blame the
         * integration for. Clicking a preset fills those in and leaves the
         * account-specific parts to be pasted.
         */
        $presets = [
            'r2' => [
                'label' => 'Cloudflare R2', 'badge' => 'recommended',
                'endpoint' => 'https://<ACCOUNT_ID>.r2.cloudflarestorage.com', 'region' => 'auto',
                'hint' => 'Dashboard → R2 → Manage API Tokens. Copy your Account ID into the endpoint. No egress fees — cheapest for public media.',
            ],
            'b2' => [
                'label' => 'Backblaze B2', 'badge' => 'cheapest storage',
                'endpoint' => 'https://s3.us-west-004.backblazeb2.com', 'region' => 'us-west-004',
                'hint' => 'Use the S3-compatible endpoint shown on your bucket page — the region number must match it exactly.',
            ],
            'wasabi' => [
                'label' => 'Wasabi', 'badge' => '',
                'endpoint' => 'https://s3.ap-southeast-1.wasabisys.com', 'region' => 'ap-southeast-1',
                'hint' => 'No egress fees, but a 90-day minimum retention charge applies to deleted objects.',
            ],
            'spaces' => [
                'label' => 'DigitalOcean Spaces', 'badge' => '',
                'endpoint' => 'https://blr1.digitaloceanspaces.com', 'region' => 'blr1',
                'hint' => 'blr1 = Bangalore, the closest region for Indian traffic. $5/mo includes 250GB + 1TB transfer.',
            ],
            's3' => [
                'label' => 'AWS S3', 'badge' => '',
                'endpoint' => 'https://s3.ap-south-1.amazonaws.com', 'region' => 'ap-south-1',
                'hint' => 'ap-south-1 = Mumbai. Watch egress: at ~5x reads it costs several times more than the storage itself.',
            ],
            'minio' => [
                'label' => 'MinIO (self-hosted)', 'badge' => '',
                'endpoint' => 'https://minio.example.com', 'region' => 'us-east-1',
                'hint' => 'Any MinIO server. Region is usually us-east-1 unless you configured otherwise.',
            ],
        ];
        ?>
        <div>
          <span class="block text-sm font-medium mb-1">Provider</span>
          <div class="flex flex-wrap gap-1.5">
            <?php foreach ($presets as $key => $p): ?>
              <button type="button" class="text-xs border rounded-full px-3 py-1.5 bg-white hover:bg-indigo-50 hover:border-indigo-300"
                      data-preset="<?= e(json_encode($p)) ?>">
                <?= e($p['label']) ?>
                <?php if ($p['badge']): ?><span class="text-indigo-600">· <?= e($p['badge']) ?></span><?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>
          <p id="preset-hint" class="hidden text-xs text-slate-600 bg-slate-50 border rounded px-3 py-2 mt-2"></p>
        </div>
        <script>
        (function () {
          // Presets only prefill the two fields people get wrong (endpoint shape
          // and region). Credentials are never touched.
          document.querySelectorAll('[data-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var p = JSON.parse(btn.getAttribute('data-preset'));
              document.getElementById('s3_endpoint').value = p.endpoint;
              document.getElementById('s3_region').value = p.region;
              var hint = document.getElementById('preset-hint');
              hint.textContent = p.label + ': ' + p.hint;
              hint.classList.remove('hidden');
              document.getElementById('s3_endpoint').focus();
            });
          });
        })();
        </script>
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

      <div class="border-t pt-4">
        <h2 class="text-sm font-semibold mb-1">💰 Cost controls</h2>
        <p class="text-xs text-slate-500 mb-3">
          These apply to every upload regardless of backend. Optimising at upload is the only saving that
          compounds — a byte not stored is also a byte never served.
        </p>
        <div class="space-y-3">
          <?php foreach ($costFields as $key => [$label, $type, $hint]): ?>
            <?php if ($type === 'checkbox'): ?>
              <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="<?= e($key) ?>" value="1" class="mt-0.5"
                       <?= setting($key, $key === 'max_upload_mb' ? '' : '1') === '1' ? 'checked' : '' ?>>
                <span>
                  <span class="font-medium"><?= e($label) ?></span>
                  <span class="block text-xs text-slate-400"><?= e($hint) ?></span>
                </span>
              </label>
            <?php else: ?>
              <div>
                <label for="<?= e($key) ?>" class="block text-sm font-medium mb-1"><?= e($label) ?></label>
                <input id="<?= e($key) ?>" type="number" name="<?= e($key) ?>"
                       value="<?= e(setting($key, match ($key) {
                           'image_max_dimension' => '1600',
                           'image_quality' => '82',
                           'max_upload_mb' => '5',
                           default => '',
                       })) ?>" class="w-32 border rounded px-3 py-2 text-sm">
                <p class="text-xs text-slate-400 mt-1"><?= e($hint) ?></p>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
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
      <h2 class="text-sm font-semibold mb-2">📊 Current usage</h2>
      <div class="text-2xl font-bold"><?= e(format_bytes($usage['bytes'])) ?></div>
      <p class="text-xs text-slate-500"><?= number_format($usage['files']) ?> file<?= $usage['files'] === 1 ? '' : 's' ?> stored</p>
      <p class="text-xs text-slate-500 mt-1">
        +<?= e(format_bytes($usage['bytes_30d'])) ?> in the last 30 days
        <?php if ($growthGbMonth > 0): ?>
          <span class="block text-slate-400">≈ <?= round($growthGbMonth * 12, 1) ?> GB/year at this rate</span>
        <?php endif; ?>
      </p>
    </div>

    <div class="bg-white border rounded-lg p-4">
      <h2 class="text-sm font-semibold mb-1">💵 Estimated monthly cost</h2>
      <p class="text-xs text-slate-400 mb-2">
        At <?= round($gb, 2) ?> GB stored and ~<?= round($egressGb, 1) ?> GB served/month (assuming each file viewed ~5x).
      </p>
      <table class="w-full text-xs">
        <tbody>
          <?php foreach ($providers as $name => $p):
              $cost = $gb * $p['store'] + $egressGb * $p['egress']; ?>
            <tr class="border-b last:border-0">
              <td class="py-1.5">
                <span class="font-medium"><?= e($name) ?></span>
                <span class="block text-slate-400 text-[10px] leading-tight"><?= e($p['note']) ?></span>
              </td>
              <td class="py-1.5 text-right align-top whitespace-nowrap font-mono">
                <?= $cost < 0.01 ? '<$0.01' : '$' . number_format($cost, 2) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="text-[10px] text-slate-400 mt-2">
        List prices, Jul 2026. Estimates only — egress is the term that surprises people, so it is included.
      </p>
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
