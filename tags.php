<?php
require_once __DIR__ . '/includes/auth.php';

$sort = ($_GET['sort'] ?? 'popular') === 'name' ? 'name' : 'popular';
$orderSql = $sort === 'name' ? 't.name ASC' : 't.use_count DESC, t.name ASC';

$tags = db()->query("SELECT t.name, t.slug, t.description, t.use_count FROM tags t WHERE t.use_count > 0 ORDER BY $orderSql LIMIT 500")->fetchAll();

$pageTitle = 'All tags — ' . SITE_NAME;
$pageDescription = 'Browse every tag on ' . SITE_NAME . ' and find questions on the exact technology or topic you care about.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-4xl mx-auto">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
      <h1 class="text-2xl font-bold">Tags</h1>
      <p class="text-sm text-slate-500 mt-1">A tag marks the specific tech or topic a question is about — narrower than a <a href="/categories" class="text-indigo-600 hover:underline">category</a>.</p>
    </div>
    <div class="flex items-center gap-2">
      <label for="tag-search" class="sr-only">Filter tags</label>
      <input id="tag-search" type="search" placeholder="Filter tags…" class="w-48 border rounded-lg px-3 py-2 text-sm" autocomplete="off">
      <a href="?sort=<?= $sort === 'name' ? 'popular' : 'name' ?>" class="text-xs px-3 py-2 rounded-lg border bg-white dark:bg-slate-900 hover:bg-slate-50"><?= $sort === 'name' ? 'Sort: A–Z' : 'Sort: popular' ?></a>
    </div>
  </div>

  <?php if ($tags): ?>
    <div id="tag-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
      <?php foreach ($tags as $t): ?>
        <a href="/tag/<?= e($t['slug']) ?>" data-tag="<?= e(mb_strtolower($t['name'] . ' ' . ($t['description'] ?? ''))) ?>"
           class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-3 hover:border-indigo-400 transition">
          <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-indigo-700 dark:text-indigo-400">#<?= e($t['name']) ?></span>
            <span class="text-xs text-slate-500 shrink-0"><?= (int) $t['use_count'] ?> question<?= (int) $t['use_count'] === 1 ? '' : 's' ?></span>
          </div>
          <?php if ($t['description']): ?><p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= e($t['description']) ?></p><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <p id="tag-no-results" class="hidden text-sm text-slate-500 py-8 text-center">No tags match your filter.</p>
  <?php else: ?>
    <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-8 text-center text-sm text-slate-500">
      No tags in use yet. Tags appear here once questions start using them — <a href="/ask" class="text-indigo-600 hover:underline">ask one</a>.
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var input = document.getElementById('tag-search');
  if (!input) return;
  var cards = Array.prototype.slice.call(document.querySelectorAll('[data-tag]'));
  var empty = document.getElementById('tag-no-results');
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var any = false;
    cards.forEach(function (c) {
      var show = !q || c.getAttribute('data-tag').indexOf(q) !== -1;
      c.classList.toggle('hidden', !show);
      if (show) any = true;
    });
    if (empty) empty.classList.toggle('hidden', any);
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
