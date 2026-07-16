<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';

$pdo = db();
$slug = $_GET['slug'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1' . sd_filter());
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

$pageTitle = $page['title'] . ' — ' . SITE_NAME;
$pageDescription = $page['meta_description'] ?: mb_substr(strip_tags($page['body']), 0, 160);
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6 prose prose-slate max-w-none">
  <h1 class="text-2xl font-bold mb-4"><?= e($page['title']) ?></h1>
  <?= render_markdown($page['body']) ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
