<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = paginate_offset($page, $perPage);
$categorySlug = trim($_GET['category'] ?? '');

$where = "p.status = 'published' AND p.published_at <= NOW()";
$params = [];
$activeCategory = null;
if ($categorySlug !== '') {
    $catStmt = $pdo->prepare('SELECT id, name, slug FROM blog_categories WHERE slug = ?');
    $catStmt->execute([$categorySlug]);
    $activeCategory = $catStmt->fetch();
    if ($activeCategory) {
        $where .= ' AND p.blog_category_id = ?';
        $params[] = $activeCategory['id'];
    } else {
        // Unknown slug: filter to nothing rather than silently showing everything —
        // an old bookmarked category link should read as empty, not "reset".
        $where .= ' AND 0';
    }
}

$posts = $pdo->prepare(
    "SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at, p.view_count, u.username, bc.name AS category_name, bc.slug AS category_slug
     FROM blog_posts p
     JOIN users u ON u.id = p.author_id
     LEFT JOIN blog_categories bc ON bc.id = p.blog_category_id
     WHERE $where" . sd_filter('p') . "
     ORDER BY p.published_at DESC
     LIMIT $perPage OFFSET $offset"
);
$posts->execute($params);
$posts = $posts->fetchAll();

$categories = $pdo->query(
    "SELECT bc.id, bc.name, bc.slug, COUNT(p.id) AS post_count
     FROM blog_categories bc
     JOIN blog_posts p ON p.blog_category_id = bc.id AND p.status = 'published' AND p.published_at <= NOW()" . sd_filter('p') . "
     GROUP BY bc.id ORDER BY bc.name"
)->fetchAll();

$pageTitle = ($activeCategory ? $activeCategory['name'] . ' — ' : '') . 'Blog — ' . SITE_NAME;
$pageDescription = $activeCategory
    ? $activeCategory['name'] . ' articles from ' . SITE_NAME . '.'
    : 'News, guides, and updates from ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-2 mb-4">
  <h1 class="text-2xl font-bold"><?= $activeCategory ? e($activeCategory['name']) : 'Blog' ?></h1>
  <?php if ($activeCategory): ?><a href="/blog" class="text-xs text-indigo-600 hover:underline">Clear category</a><?php endif; ?>
</div>
<?php if ($categories): ?>
  <div class="flex flex-wrap gap-2 mb-5">
    <a href="/blog" class="text-sm px-3 py-1.5 rounded-full border <?= !$activeCategory ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white hover:border-indigo-400' ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a href="/blog?category=<?= e($c['slug']) ?>" class="text-sm px-3 py-1.5 rounded-full border <?= $categorySlug === $c['slug'] ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white hover:border-indigo-400' ?>">
        <?= e($c['name']) ?> <span class="text-xs opacity-70">(<?= (int) $c['post_count'] ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if (!$posts): ?>
  <div class="border rounded-lg p-8 text-center bg-white text-slate-600">
    <?= $activeCategory ? 'No posts in this category yet.' : 'No posts published yet.' ?>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($posts as $p): ?>
      <a href="/blog/<?= e($p['slug']) ?>" class="block bg-white border rounded-lg overflow-hidden hover:border-indigo-400">
        <?php if ($p['cover_image']): ?>
          <img src="<?= e($p['cover_image']) ?>" alt="" class="w-full h-40 object-cover">
        <?php endif; ?>
        <div class="p-4">
          <h2 class="font-semibold"><?= e($p['title']) ?></h2>
          <?php if ($p['excerpt']): ?><p class="text-sm text-slate-600 mt-1"><?= e($p['excerpt']) ?></p><?php endif; ?>
          <div class="text-xs text-slate-500 mt-2">
            by <?= e($p['username']) ?> &middot; <?= time_ago($p['published_at']) ?>
            <?php if ($p['category_name']): ?>&middot; <?= e($p['category_name']) ?><?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="flex justify-between mt-4 text-sm">
    <?php $pageParams = array_filter(['category' => $categorySlug]); ?>
    <?php if ($page > 1): ?><a class="text-indigo-600 hover:underline" href="?<?= http_build_query(array_merge($pageParams, ['page' => $page - 1])) ?>">&larr; Previous</a><?php else: ?><span></span><?php endif; ?>
    <?php if (count($posts) === $perPage): ?><a class="text-indigo-600 hover:underline" href="?<?= http_build_query(array_merge($pageParams, ['page' => $page + 1])) ?>">Next &rarr;</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
