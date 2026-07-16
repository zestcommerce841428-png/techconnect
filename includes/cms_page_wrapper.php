<?php
/**
 * Renders a CMS-managed page by fixed slug at a clean top-level URL
 * (e.g. about.php sets $cmsSlug = 'about' then includes this file).
 * Falls back to a "content coming soon" notice if the admin hasn't
 * created the page row yet, so the URL never 404s.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/markdown.php';

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1' . sd_filter());
$stmt->execute([$cmsSlug]);
$page = $stmt->fetch();

$pageTitle = ($page['title'] ?? ucfirst($cmsSlug)) . ' — ' . SITE_NAME;
$pageDescription = $page['meta_description'] ?? '';
require __DIR__ . '/header.php';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6 prose prose-slate max-w-none">
  <h1 class="text-2xl font-bold mb-4"><?= e($page['title'] ?? ucfirst($cmsSlug)) ?></h1>
  <?php if ($page): ?>
    <?= render_markdown($page['body']) ?>
  <?php else: ?>
    <p class="text-slate-500">This page hasn't been published yet.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
