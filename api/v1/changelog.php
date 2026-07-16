<?php
// GET /api/v1/changelog.php — published changelog entries.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    "SELECT version, title, body, entry_type, published_at FROM changelog_entries
     WHERE is_published = 1 ORDER BY published_at DESC LIMIT 100"
);
api_json(['data' => array_map(fn($c) => [
    'version' => $c['version'], 'title' => $c['title'], 'body' => $c['body'],
    'type' => $c['entry_type'], 'published_at' => $c['published_at'],
], $stmt->fetchAll())]);
