<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';

$pdo = db();
$slug = $_GET['slug'] ?? '';

// Staff may preview an unpublished page; the public cannot. See blog_post.php
// for why this is role-gated rather than token-based.
$viewer = current_user();
$canPreview = $viewer && in_array($viewer['role'], ['admin', 'moderator'], true);
$catReady = page_categories_ready();
$stmt = $pdo->prepare(
    'SELECT p.*' . ($catReady ? ', pc.name AS category_name, pc.slug AS category_slug' : '') . '
     FROM pages p' . ($catReady ? ' LEFT JOIN page_categories pc ON pc.id = p.page_category_id' : '') . '
     WHERE p.slug = ?' . ($canPreview ? '' : ' AND p.is_published = 1') . sd_filter('p')
);
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Page not found — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-12"><p class="text-slate-600">That page doesn\'t exist.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isPreview = empty($page['is_published']);
$pageTitle = $page['title'] . ' — ' . SITE_NAME;
$pageDescription = $page['meta_description'] ?: mb_substr(strip_tags($page['body']), 0, 160);
if ($isPreview) {
    $pageRobots = 'noindex, nofollow';
}

// Related pages: same category, excluding self and previews of other drafts —
// only relevant once 034 is applied and the page actually has a category.
$relatedPages = [];
if ($catReady && !empty($page['page_category_id'])) {
    $relStmt = $pdo->prepare(
        'SELECT title, slug FROM pages
         WHERE is_published = 1 AND page_category_id = ? AND id != ?' . sd_filter() . '
         ORDER BY title LIMIT 5'
    );
    $relStmt->execute([$page['page_category_id'], $page['id']]);
    $relatedPages = $relStmt->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Pages', 'item' => SITE_URL . '/pages'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $page['title'], 'item' => SITE_URL . '/page/' . $page['slug']],
    ],
], JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $page['title'],
    'description' => $pageDescription,
    'url' => SITE_URL . '/page/' . $page['slug'],
    'dateModified' => date('c', strtotime($page['updated_at'] ?? $page['created_at'])),
], JSON_UNESCAPED_SLASHES) ?></script>
<nav aria-label="Breadcrumb" class="max-w-2xl mx-auto mb-3">
  <ol class="flex items-center gap-1.5 text-sm text-slate-500 min-w-0">
    <li><a href="/" class="hover:underline">Home</a></li>
    <li aria-hidden="true">›</li>
    <li><a href="/pages" class="hover:underline">Pages</a></li>
    <?php if (!empty($page['category_name'])): ?>
      <li aria-hidden="true">›</li>
      <li><a href="/pages#<?= e($page['category_slug']) ?>" class="hover:underline"><?= e($page['category_name']) ?></a></li>
    <?php endif; ?>
    <li aria-hidden="true">›</li>
    <li class="truncate text-slate-700" aria-current="page"><?= e(mb_substr($page['title'], 0, 50)) ?><?= mb_strlen($page['title']) > 50 ? '…' : '' ?></li>
  </ol>
</nav>
<?php if ($isPreview): ?>
  <div class="max-w-2xl mx-auto mb-3 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2">
    <span><strong>Preview —</strong> this page is unpublished and not visible to the public.</span>
    <a href="/admin/pages?id=<?= (int) $page['id'] ?>" class="shrink-0 bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded text-xs font-medium">Edit page</a>
  </div>
<?php endif; ?>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6 prose prose-slate max-w-none">
  <h1 class="text-2xl font-bold mb-4"><?= e($page['title']) ?></h1>
  <?= render_markdown($page['body']) ?>
</div>
<?php if ($relatedPages): ?>
  <div class="max-w-2xl mx-auto mt-4">
    <h2 class="text-sm font-semibold text-slate-500 mb-2">Related pages</h2>
    <div class="bg-white border rounded-lg divide-y">
      <?php foreach ($relatedPages as $rp): ?>
        <a href="/page/<?= e($rp['slug']) ?>" class="block px-4 py-2.5 text-sm hover:bg-slate-50"><?= e($rp['title']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
