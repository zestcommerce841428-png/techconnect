<?php
require_once __DIR__ . '/includes/auth.php';
$rows = db()->query('SELECT * FROM changelog_entries WHERE is_published = 1 ORDER BY published_at DESC LIMIT 100')->fetchAll();

$badgeColor = ['added' => 'bg-green-100 text-green-700', 'changed' => 'bg-blue-100 text-blue-700', 'fixed' => 'bg-amber-100 text-amber-700', 'removed' => 'bg-red-100 text-red-700'];

$pageTitle = "What's new — " . SITE_NAME;
$pageDescription = 'Recent updates and changes to ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold mb-6">What's new</h1>
  <div class="space-y-4">
    <?php foreach ($rows as $c): ?>
      <div class="card p-4">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs px-2 py-0.5 rounded-full font-medium uppercase <?= $badgeColor[$c['entry_type']] ?? 'bg-slate-100 text-slate-700' ?>"><?= e($c['entry_type']) ?></span>
          <?php if ($c['version']): ?><span class="text-xs text-slate-500"><?= e($c['version']) ?></span><?php endif; ?>
          <span class="text-xs text-slate-400"><?= time_ago($c['published_at']) ?></span>
        </div>
        <h2 class="font-semibold"><?= e($c['title']) ?></h2>
        <?php if ($c['body']): ?><p class="text-sm text-slate-600 dark:text-slate-400 mt-1"><?= e($c['body']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="text-sm text-slate-500">No updates published yet.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
