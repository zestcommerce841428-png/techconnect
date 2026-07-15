<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = db();
$questions = $pdo->query('SELECT slug, updated_at FROM questions ORDER BY updated_at DESC LIMIT 5000')->fetchAll();
$tags = $pdo->query('SELECT slug FROM tags')->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$staticPages = ['/index.php', '/questions.php', '/jobs.php', '/about.php', '/contact.php'];
foreach ($staticPages as $path) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . $path) . '</loc></url>' . "\n";
}
foreach ($questions as $q) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/question.php?slug=' . $q['slug']) . '</loc>'
        . '<lastmod>' . date('c', strtotime($q['updated_at'])) . '</lastmod></url>' . "\n";
}
foreach ($tags as $t) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/questions.php?tag=' . $t['slug']) . '</loc></url>' . "\n";
}
echo '</urlset>';
