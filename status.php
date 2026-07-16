<?php
require_once __DIR__ . '/includes/auth.php';

$checks = [];
$start = microtime(true);
try {
    db()->query('SELECT 1');
    $checks['Database'] = ['ok' => true, 'detail' => round((microtime(true) - $start) * 1000) . ' ms'];
} catch (Throwable $e) {
    $checks['Database'] = ['ok' => false, 'detail' => 'Unreachable'];
}
$checks['Storage'] = ['ok' => is_writable(__DIR__ . '/uploads'), 'detail' => is_writable(__DIR__ . '/uploads') ? 'Writable' : 'Not writable'];
$checks['Email (SMTP configured)'] = ['ok' => SMTP_HOST !== '', 'detail' => SMTP_HOST !== '' ? 'Configured' : 'Not configured'];

$allOk = !in_array(false, array_column($checks, 'ok'), true);

$pageTitle = 'System status — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto">
  <div class="card p-6 text-center mb-6">
    <div class="text-4xl mb-2"><?= $allOk ? '🟢' : '🔴' ?></div>
    <h1 class="text-xl font-semibold"><?= $allOk ? 'All systems operational' : 'Degraded performance' ?></h1>
  </div>
  <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y">
    <?php foreach ($checks as $name => $c): ?>
      <div class="p-4 flex items-center justify-between text-sm">
        <span><?= e($name) ?></span>
        <span class="<?= $c['ok'] ? 'text-green-600' : 'text-red-600' ?> font-medium"><?= $c['ok'] ? '✓' : '✗' ?> <?= e($c['detail']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="text-xs text-slate-400 text-center mt-4">Machine-readable version: <a href="/health" class="text-indigo-600 hover:underline">/health</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
