<?php
// GET /api/v1/jobs.php — list active job listings.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    "SELECT title, slug, company, location, is_remote, created_at FROM jobs
     WHERE status = 'active' ORDER BY created_at DESC LIMIT 100"
);
api_json(['data' => array_map(fn($j) => [
    'title' => $j['title'], 'slug' => $j['slug'], 'company' => $j['company'],
    'location' => $j['location'], 'is_remote' => (bool) $j['is_remote'], 'created_at' => $j['created_at'],
], $stmt->fetchAll())]);
