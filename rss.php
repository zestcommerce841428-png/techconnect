<?php
// RSS 2.0 feeds: /rss (latest questions) and /rss?feed=blog (blog posts).
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/rss+xml; charset=UTF-8');
$pdo = db();
$feed = $_GET['feed'] ?? 'questions';
$siteName = SITE_NAME;

function rss_item(string $title, string $link, string $description, string $date): string
{
    return '<item>'
        . '<title>' . htmlspecialchars($title, ENT_XML1) . '</title>'
        . '<link>' . htmlspecialchars($link, ENT_XML1) . '</link>'
        . '<guid>' . htmlspecialchars($link, ENT_XML1) . '</guid>'
        . '<description>' . htmlspecialchars(mb_strimwidth(strip_tags($description), 0, 300, '…'), ENT_XML1) . '</description>'
        . '<pubDate>' . date(DATE_RSS, strtotime($date)) . '</pubDate>'
        . '</item>';
}

$items = '';
if ($feed === 'blog') {
    $title = $siteName . ' Blog';
    $rows = $pdo->query("SELECT title, slug, excerpt, body, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 30")->fetchAll();
    foreach ($rows as $r) {
        $items .= rss_item($r['title'], SITE_URL . '/blog/' . $r['slug'], $r['excerpt'] ?: $r['body'], $r['published_at']);
    }
} else {
    $title = $siteName . ' — Latest questions';
    $rows = $pdo->query("SELECT title, slug, body, created_at FROM questions WHERE group_id IS NULL AND status != 'draft' AND merged_into_id IS NULL ORDER BY created_at DESC LIMIT 30")->fetchAll();
    foreach ($rows as $r) {
        $items .= rss_item($r['title'], SITE_URL . '/q/' . $r['slug'], $r['body'], $r['created_at']);
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>'
    . '<rss version="2.0"><channel>'
    . '<title>' . htmlspecialchars($title, ENT_XML1) . '</title>'
    . '<link>' . htmlspecialchars(SITE_URL, ENT_XML1) . '</link>'
    . '<description>' . htmlspecialchars($title, ENT_XML1) . '</description>'
    . '<language>en</language>'
    . $items
    . '</channel></rss>';
