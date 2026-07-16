<?php
// GET /api/v1/job.php?slug=<slug> — single job listing.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') api_json(['error' => 'slug is required'], 422);

$stmt = db()->prepare(
    "SELECT j.title, j.slug, j.description, j.company, j.location, j.is_remote, j.created_at, u.username
     FROM jobs j JOIN users u ON u.id = j.posted_by WHERE j.slug = ? AND j.status = 'active'"
);
$stmt->execute([$slug]);
$job = $stmt->fetch();
if (!$job) api_json(['error' => 'Not found'], 404);

api_json(['data' => [
    'title' => $job['title'], 'slug' => $job['slug'], 'description' => $job['description'],
    'company' => $job['company'], 'location' => $job['location'], 'is_remote' => (bool) $job['is_remote'],
    'posted_by' => $job['username'], 'created_at' => $job['created_at'],
]]);
