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
