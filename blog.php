<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = paginate_offset($page, $perPage);

$posts = $pdo->prepare(
    "SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at, p.view_count, u.username, bc.name AS category_name
     FROM blog_posts p
     JOIN users u ON u.id = p.author_id
     LEFT JOIN blog_categories bc ON bc.id = p.blog_category_id
     WHERE p.status = 'published' AND p.published_at <= NOW()" . sd_filter('p') . "
     ORDER BY p.published_at DESC
     LIMIT $perPage OFFSET $offset"
);
$posts->execute();
$posts = $posts->fetchAll();

$pageTitle = 'Blog — ' . SITE_NAME;
$pageDescription = 'News, guides, and updates from ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Blog</h1>
<?php if (!$posts): ?>
  <div class="border rounded-lg p-8 text-center bg-white text-slate-600">No posts published yet.</div>
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
    <?php if ($page > 1): ?><a class="text-indigo-600 hover:underline" href="?page=<?= $page - 1 ?>">&larr; Previous</a><?php else: ?><span></span><?php endif; ?>
    <?php if (count($posts) === $perPage): ?><a class="text-indigo-600 hover:underline" href="?page=<?= $page + 1 ?>">Next &rarr;</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
