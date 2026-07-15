<?php
// GET /api/v1/blog_posts.php — list published blog posts.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare(
    "SELECT b.title, b.slug, b.excerpt, b.published_at, u.username FROM blog_posts b
     JOIN users u ON u.id = b.author_id WHERE b.status = 'published'
     ORDER BY b.published_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute();
api_json(['data' => array_map(fn($b) => [
    'title' => $b['title'], 'url' => SITE_URL . '/blog/' . $b['slug'], 'excerpt' => $b['excerpt'],
    'author' => $b['username'], 'published_at' => $b['published_at'],
], $stmt->fetchAll()), 'page' => $page]);
