<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Site Health — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$logDir = __DIR__ . '/../logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_rate_limit_cache') {
        $dir = sys_get_temp_dir() . '/tc_api_rl';
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $f) @unlink($f);
        }
        audit_log($admin['id'], 'cleared_api_rate_limit_cache');
        flash_set('success', 'API rate-limit cache cleared.');
    } elseif ($action === 'clear_logs') {
        foreach (glob($logDir . '/*.log') as $f) @unlink($f);
        audit_log($admin['id'], 'cleared_app_logs');
        flash_set('success', 'Application logs cleared.');
    }
    redirect('/admin/site_health');
}

$pdo = db();
$dbSize = $pdo->query(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = DATABASE()"
)->fetchColumn();
$tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
$logFiles = is_dir($logDir) ? glob($logDir . '/*.log') : [];
$logSize = array_sum(array_map('filesize', $logFiles));
$uploadsDir = __DIR__ . '/../uploads';
$uploadsSize = 0;
if (is_dir($uploadsDir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS)) as $f) {
        $uploadsSize += $f->getSize();
    }
}
?>
<h1 class="text-2xl font-bold mb-4">Site health</h1>

<?php
require_once __DIR__ . '/../includes/client_ip.php';
$seenIp = client_ip();
$rawRemote = $_SERVER['REMOTE_ADDR'] ?? '';
$hasXff = !empty($_SERVER['HTTP_X_FORWARDED_FOR']);
$trustedList = trim(setting('trusted_proxies', ''));
$viaProxy = from_trusted_proxy();
?>
<div class="bg-white border rounded-lg p-4 mb-6">
  <h2 class="text-sm font-semibold mb-1">🌐 Visitor IP detection</h2>
  <p class="text-xs text-slate-500 mb-3">
    IP blocking, per-IP rate limiting, login alerts and the security dashboard all depend on seeing the
    <em>visitor's</em> address. This site serves through Hostinger's CDN, so it is worth confirming the origin
    is not just seeing the CDN.
  </p>
  <table class="w-full text-xs">
    <tbody>
      <tr class="border-b"><td class="py-1.5 text-slate-500">Your IP as this server sees it</td><td class="py-1.5 font-mono font-medium"><?= e($seenIp ?: '(none)') ?></td></tr>
      <tr class="border-b"><td class="py-1.5 text-slate-500">Direct peer (REMOTE_ADDR)</td><td class="py-1.5 font-mono"><?= e($rawRemote ?: '(none)') ?></td></tr>
      <tr class="border-b"><td class="py-1.5 text-slate-500">X-Forwarded-For present</td><td class="py-1.5"><?= $hasXff ? 'yes' : 'no' ?></td></tr>
      <tr class="border-b"><td class="py-1.5 text-slate-500">Trusted proxies configured</td><td class="py-1.5"><?= $trustedList !== '' ? e($trustedList) : '— none (forwarded headers ignored)' ?></td></tr>
      <tr><td class="py-1.5 text-slate-500">Reading forwarded header</td><td class="py-1.5"><?= $viaProxy ? 'yes — peer is a trusted proxy' : 'no' ?></td></tr>
    </tbody>
  </table>
  <div class="mt-3 text-xs rounded px-3 py-2 <?= $hasXff && !$viaProxy ? 'bg-amber-50 border border-amber-200 text-amber-800' : 'bg-slate-50 border text-slate-600' ?>">
    <strong>How to check:</strong> open
    <a href="https://api.ipify.org" target="_blank" rel="noopener" class="text-indigo-600">api.ipify.org</a>
    in this browser. If it matches <span class="font-mono"><?= e($seenIp ?: '—') ?></span> above, IP detection is correct and nothing needs changing.
    <?php if ($hasXff && !$viaProxy): ?>
      <br><br>If it does <em>not</em> match, the CDN is forwarding your real IP in a header this site is deliberately ignoring
      (ignoring it is the safe default — trusting that header blindly lets anyone forge an IP and walk through IP bans and rate limits).
      To use it safely, set <code>trusted_proxies</code> in Settings to the CDN's address — currently <span class="font-mono"><?= e($rawRemote) ?></span> —
      or its published CIDR range.
    <?php endif; ?>
  </div>
</div>

<div class="grid sm:grid-cols-3 gap-4 mb-6">
  <div class="bg-white border rounded-lg p-4">
    <div class="text-xs text-slate-500">Database size</div>
    <div class="text-xl font-bold"><?= e($dbSize ?? '0') ?> MB</div>
    <div class="text-xs text-slate-500"><?= $tableCount ?> tables</div>
  </div>
  <div class="bg-white border rounded-lg p-4">
    <div class="text-xs text-slate-500">Uploads</div>
    <div class="text-xl font-bold"><?= round($uploadsSize / 1024 / 1024, 1) ?> MB</div>
  </div>
  <div class="bg-white border rounded-lg p-4">
    <div class="text-xs text-slate-500">Log files</div>
    <div class="text-xl font-bold"><?= round($logSize / 1024, 1) ?> KB</div>
    <div class="text-xs text-slate-500"><?= count($logFiles) ?> file(s)</div>
  </div>
</div>

<div class="grid sm:grid-cols-2 gap-4">
  <div class="bg-white border rounded-lg p-4">
    <h2 class="font-semibold text-sm mb-2">API rate-limit cache</h2>
    <p class="text-xs text-slate-500 mb-3">Clears per-key request counters, letting rate-limited API keys retry immediately.</p>
    <form method="post"><?= csrf_field() ?><button type="submit" name="action" value="clear_rate_limit_cache" class="text-sm bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded">Clear cache</button></form>
  </div>
  <div class="bg-white border rounded-lg p-4">
    <h2 class="font-semibold text-sm mb-2">Application logs</h2>
    <p class="text-xs text-slate-500 mb-3">Deletes local JSON-lines logs (failed logins, spam flags). Does not affect the database.</p>
    <form method="post" onsubmit="return confirm('Delete all local log files?');"><?= csrf_field() ?><button type="submit" name="action" value="clear_logs" class="text-sm bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded">Clear logs</button></form>
  </div>
</div>

<p class="text-xs text-slate-400 mt-6">Public status page: <a href="/status" class="text-indigo-600 hover:underline">/status</a> · Machine health check: <a href="/health" class="text-indigo-600 hover:underline">/health</a></p>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
