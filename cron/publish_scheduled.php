<?php
// Flips scheduled blog posts (status=draft, publish_at in the past) to published.
// Intended for a Hostinger cron job, e.g.: */15 * * * * php /path/to/cron/publish_scheduled.php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$due = $pdo->query("SELECT id, title FROM blog_posts WHERE status = 'draft' AND publish_at IS NOT NULL AND publish_at <= NOW()")->fetchAll();

foreach ($due as $post) {
    $pdo->prepare("UPDATE blog_posts SET status = 'published', published_at = publish_at, publish_at = NULL WHERE id = ?")
        ->execute([$post['id']]);
    echo "Published: {$post['title']}\n";
}

if (!$due) {
    echo "Nothing due.\n";
}
