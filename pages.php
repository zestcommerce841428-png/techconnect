<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$catReady = page_categories_ready();

// Grouped by category once 034 is applied; a single "All pages" list before
// that, same fail-open pattern as every other readiness shim in this project.
if ($catReady) {
    $rows = $pdo->query(
        "SELECT p.title, p.slug, p.meta_description, pc.name AS category_name
         FROM pages p LEFT JOIN page_categories pc ON pc.id = p.page_category_id
         WHERE p.is_published = 1" . sd_filter('p') . "
         ORDER BY COALESCE(pc.name, ''), p.title"
    )->fetchAll();
} else {
    $rows = $pdo->query(
        "SELECT title, slug, meta_description, NULL AS category_name FROM pages
         WHERE is_published = 1" . sd_filter() . " ORDER BY title"
    )->fetchAll();
}

$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['category_name'] ?: 'General'][] = $r;
}
ksort($grouped);

$pageTitle = 'Pages — ' . SITE_NAME;
$pageDescription = 'Every published page on ' . SITE_NAME . ', including help, legal and informational pages.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold mb-4">Pages</h1>
  <?php if (!$rows): ?>
    <div class="border rounded-lg p-8 text-center bg-white dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-400">
      No pages published yet.
    </div>
  <?php else: ?>
    <div class="space-y-6">
      <?php foreach ($grouped as $label => $items): ?>
        <section>
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2"><?= e($label) ?></h2>
          <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y dark:divide-slate-800">
            <?php foreach ($items as $p): ?>
              <a href="/page/<?= e($p['slug']) ?>" class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                <span class="font-medium text-sm"><?= e($p['title']) ?></span>
                <?php if ($p['meta_description']): ?>
                  <p class="text-xs text-slate-500 mt-0.5 line-clamp-1"><?= e($p['meta_description']) ?></p>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
