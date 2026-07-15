<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = db();
$questions = $pdo->query("SELECT slug, updated_at FROM questions WHERE group_id IS NULL AND status != 'draft' AND merged_into_id IS NULL ORDER BY updated_at DESC LIMIT 5000")->fetchAll();
$tags = $pdo->query('SELECT slug FROM tags')->fetchAll();
$categories = $pdo->query('SELECT slug FROM categories WHERE is_active = 1')->fetchAll();
$pages = $pdo->query('SELECT slug, updated_at FROM pages WHERE is_published = 1')->fetchAll();
$blogPosts = $pdo->query("SELECT slug, updated_at FROM blog_posts WHERE status = 'published'")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$staticPages = ['/', '/questions', '/jobs', '/about', '/contact', '/blog'];
foreach ($staticPages as $path) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . $path) . '</loc></url>' . "\n";
}
foreach ($questions as $q) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/q/' . $q['slug']) . '</loc>'
        . '<lastmod>' . date('c', strtotime($q['updated_at'])) . '</lastmod></url>' . "\n";
}
foreach ($tags as $t) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/tag/' . $t['slug']) . '</loc></url>' . "\n";
}
foreach ($categories as $c) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/c/' . $c['slug']) . '</loc></url>' . "\n";
}
foreach ($pages as $p) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/page/' . $p['slug']) . '</loc>'
        . '<lastmod>' . date('c', strtotime($p['updated_at'])) . '</lastmod></url>' . "\n";
}
foreach ($blogPosts as $b) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/blog/' . $b['slug']) . '</loc>'
        . '<lastmod>' . date('c', strtotime($b['updated_at'])) . '</lastmod></url>' . "\n";
}
echo '</urlset>';
