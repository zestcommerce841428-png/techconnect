<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';

$pdo = db();
$slug = $_GET['slug'] ?? '';

// Preview: staff may open an unpublished post to see exactly how it will render.
// Gated on role rather than a shareable token — a leaked token is a permanent
// public backdoor to every draft, and staff are already authenticated here.
$viewer = current_user();
$canPreview = $viewer && in_array($viewer['role'], ['admin', 'moderator'], true);
$statusClause = $canPreview ? '' : " AND p.status = 'published'";

$stmt = $pdo->prepare(
    "SELECT p.*, u.username, bc.name AS category_name, bc.slug AS category_slug
     FROM blog_posts p JOIN users u ON u.id = p.author_id
     LEFT JOIN blog_categories bc ON bc.id = p.blog_category_id
     WHERE p.slug = ?" . $statusClause . sd_filter('p')
);
$stmt->execute([$slug]);
$post = $stmt->fetch();

$isPreview = $post && $post['status'] !== 'published';

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post not found — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-12"><p class="text-slate-600">That post doesn\'t exist.</p><a href="/blog" class="text-indigo-600 hover:underline">Back to blog</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Previews must not inflate the post's own view count.
if (!$isPreview) {
    $pdo->prepare('UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?')->execute([$post['id']]);
}

// Adjacent posts for internal linking — keeps readers moving through the blog
// and gives crawlers a path between posts that the index alone does not.
$prev = $pdo->prepare("SELECT title, slug FROM blog_posts
    WHERE status = 'published' AND published_at < ?" . sd_filter() . "
    ORDER BY published_at DESC LIMIT 1");
$prev->execute([$post['published_at']]);
$prevPost = $prev->fetch() ?: null;

$next = $pdo->prepare("SELECT title, slug FROM blog_posts
    WHERE status = 'published' AND published_at > ? AND published_at <= NOW()" . sd_filter() . "
    ORDER BY published_at ASC LIMIT 1");
$next->execute([$post['published_at']]);
$nextPost = $next->fetch() ?: null;

$readMinutes = reading_time($post['body']);

$pageTitle = $post['title'] . ' — ' . SITE_NAME;
$pageDescription = $post['meta_description'] ?: ($post['excerpt'] ?: mb_substr(strip_tags($post['body']), 0, 160));
$ogImage = $post['cover_image'] ? (str_starts_with($post['cover_image'], 'http') ? $post['cover_image'] : SITE_URL . $post['cover_image']) : null;
// An unpublished draft must never reach the index, even though only staff can
// load it — a stray crawl from a logged-in session would otherwise leak it.
if ($isPreview) {
    $pageRobots = 'noindex, nofollow';
}
require __DIR__ . '/includes/header.php';
?>
<?php if ($isPreview): ?>
  <div class="max-w-2xl mx-auto mb-3 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2">
    <span>
      <strong>Preview —</strong> this post is <strong><?= e($post['status']) ?></strong> and is not visible to the public.
      <?php if (!empty($post['publish_at'])): ?>
        Scheduled for <?= e(date('j M Y, g:i a', strtotime($post['publish_at']))) ?>.
      <?php endif; ?>
    </span>
    <a href="/admin/blog?id=<?= (int) $post['id'] ?>" class="shrink-0 bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded text-xs font-medium">Edit post</a>
  </div>
<?php endif; ?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => SITE_URL . '/blog'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => SITE_URL . '/blog/' . $post['slug']],
    ],
], JSON_UNESCAPED_SLASHES) ?></script>
<nav aria-label="Breadcrumb" class="max-w-2xl mx-auto mb-3">
  <ol class="flex items-center gap-1.5 text-sm text-slate-500 min-w-0">
    <li><a href="/" class="hover:underline">Home</a></li>
    <li aria-hidden="true">›</li>
    <li><a href="/blog" class="hover:underline">Blog</a></li>
    <li aria-hidden="true">›</li>
    <li class="truncate text-slate-700" aria-current="page"><?= e(mb_substr($post['title'], 0, 50)) ?><?= mb_strlen($post['title']) > 50 ? '…' : '' ?></li>
  </ol>
</nav>
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
    <?php if ($post['category_name']): ?>&middot; <a href="/blog?category=<?= e($post['category_slug']) ?>" class="hover:underline"><?= e($post['category_name']) ?></a><?php endif; ?>
    &middot; <?= $readMinutes ?> min read
    &middot; <?= (int) $post['view_count'] ?> views
  </div>
  <div class="mt-4 prose prose-slate max-w-none"><?= render_markdown($post['body']) ?></div>

  <div class="mt-6 pt-4 border-t flex flex-wrap items-center gap-2">
    <span class="text-xs text-slate-500 mr-1">Share:</span>
    <?php $bpUrl = urlencode(SITE_URL . '/blog/' . $post['slug']); $bpTitle = urlencode($post['title']); ?>
    <a href="https://wa.me/?text=<?= $bpTitle ?>%20<?= $bpUrl ?>" target="_blank" rel="noopener" class="text-xs border rounded-full px-3 py-1.5 hover:bg-slate-50">WhatsApp</a>
    <a href="https://twitter.com/intent/tweet?url=<?= $bpUrl ?>&text=<?= $bpTitle ?>" target="_blank" rel="noopener" class="text-xs border rounded-full px-3 py-1.5 hover:bg-slate-50">X</a>
    <a href="https://t.me/share/url?url=<?= $bpUrl ?>&text=<?= $bpTitle ?>" target="_blank" rel="noopener" class="text-xs border rounded-full px-3 py-1.5 hover:bg-slate-50">Telegram</a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $bpUrl ?>" target="_blank" rel="noopener" class="text-xs border rounded-full px-3 py-1.5 hover:bg-slate-50">LinkedIn</a>
  </div>
</article>

<?php if ($prevPost || $nextPost): ?>
  <nav class="max-w-2xl mx-auto mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3" aria-label="More posts">
    <?php if ($prevPost): ?>
      <a href="/blog/<?= e($prevPost['slug']) ?>" class="bg-white border rounded-lg p-3 hover:border-indigo-400">
        <div class="text-xs text-slate-400">← Previous</div>
        <div class="text-sm font-medium truncate"><?= e($prevPost['title']) ?></div>
      </a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($nextPost): ?>
      <a href="/blog/<?= e($nextPost['slug']) ?>" class="bg-white border rounded-lg p-3 hover:border-indigo-400 sm:text-right">
        <div class="text-xs text-slate-400">Next →</div>
        <div class="text-sm font-medium truncate"><?= e($nextPost['title']) ?></div>
      </a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
