<?php
// GET /api/v1/blog_post.php?slug=<slug> — single published blog post.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$stmt = db()->prepare(
    "SELECT b.title, b.slug, b.excerpt, b.body, b.published_at, u.username FROM blog_posts b
     JOIN users u ON u.id = b.author_id WHERE b.slug = ? AND b.status = 'published'"
);
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) api_json(['error' => 'Not found'], 404);

api_json(['data' => [
    'title' => $post['title'], 'excerpt' => $post['excerpt'], 'body' => $post['body'],
    'author' => $post['username'], 'published_at' => $post['published_at'],
]]);
