<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';

$pdo = db();
$slug = $_GET['slug'] ?? '';

$stmt = $pdo->prepare(
    "SELECT p.*, u.username, bc.name AS category_name
     FROM blog_posts p JOIN users u ON u.id = p.author_id
     LEFT JOIN blog_categories bc ON bc.id = p.blog_category_id
     WHERE p.slug = ? AND p.status = 'published'" . sd_filter('p') . ""
);
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post not found — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-12"><p class="text-slate-600">That post doesn\'t exist.</p><a href="/blog" class="text-indigo-600 hover:underline">Back to blog</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pdo->prepare('UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?')->execute([$post['id']]);

$pageTitle = $post['title'] . ' — ' . SITE_NAME;
$pageDescription = $post['meta_description'] ?: ($post['excerpt'] ?: mb_substr(strip_tags($post['body']), 0, 160));
require __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post['title'],
    'description' => $pageDescription,
    'image' => $post['cover_image'] ? SITE_URL . $post['cover_image'] : null,
    'datePublished' => $post['published_at'] ? date('c', strtotime($post['published_at'])) : null,
    'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['published_at'] ?? $post['created_at'])),
    'author' => ['@type' => 'Person', 'name' => $post['username']],
    'publisher' => ['@type' => 'Organization', 'name' => setting('site_name', SITE_NAME)],
], JSON_UNESCAPED_SLASHES) ?></script>
<article class="max-w-2xl mx-auto bg-white border rounded-lg p-6">
  <?php if ($post['cover_image']): ?>
    <img src="<?= e($post['cover_image']) ?>" alt="" class="w-full h-56 object-cover rounded mb-4">
  <?php endif; ?>
  <h1 class="text-2xl font-bold"><?= e($post['title']) ?></h1>
  <div class="text-xs text-slate-500 mt-1">
    by <?= e($post['username']) ?> &middot; <?= time_ago($post['published_at']) ?>
    <?php if ($post['category_name']): ?>&middot; <?= e($post['category_name']) ?><?php endif; ?>
    &middot; <?= (int) $post['view_count'] ?> views
  </div>
  <div class="mt-4 prose prose-slate max-w-none"><?= render_markdown($post['body']) ?></div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
