<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = db();
$questions = $pdo->query("SELECT slug, updated_at FROM questions WHERE group_id IS NULL AND status != 'draft' AND merged_into_id IS NULL ORDER BY updated_at DESC LIMIT 5000")->fetchAll();
// Only advertise category/tag pages that actually have questions — submitting
// hundreds of empty listings burns crawl budget and reads as thin content.
$tags = $pdo->query('SELECT slug FROM tags WHERE use_count > 0')->fetchAll();
$categories = $pdo->query(
    "SELECT c.slug FROM categories c
     JOIN questions q ON q.category_id = c.id AND q.status <> 'draft' AND q.merged_into_id IS NULL
     WHERE c.is_active = 1
     GROUP BY c.id"
)->fetchAll();
$pages = $pdo->query('SELECT slug, updated_at FROM pages WHERE is_published = 1')->fetchAll();
$blogPosts = $pdo->query("SELECT slug, updated_at FROM blog_posts WHERE status = 'published'")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$staticPages = ['/', '/questions', '/jobs', '/about', '/contact', '/blog', '/categories', '/tags', '/features', '/stats', '/leaderboard', '/experts', '/groups', '/collections', '/faq', '/roadmap', '/changelog', '/feedback', '/guidelines'];
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
