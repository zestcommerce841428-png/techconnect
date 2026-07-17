<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';

$pdo = db();
$slug = $_GET['slug'] ?? '';

// Staff may preview an unpublished page; the public cannot. See blog_post.php
// for why this is role-gated rather than token-based.
$viewer = current_user();
$canPreview = $viewer && in_array($viewer['role'], ['admin', 'moderator'], true);
$stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = ?' . ($canPreview ? '' : ' AND is_published = 1') . sd_filter());
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
require __DIR__ . '/includes/header.php';
?>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
