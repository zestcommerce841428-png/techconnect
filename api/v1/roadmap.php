<?php
// GET /api/v1/roadmap.php — public roadmap items.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query("SELECT title, description, status, vote_count FROM roadmap_items WHERE status != 'declined' ORDER BY vote_count DESC");
api_json(['data' => array_map(fn($r) => [
    'title' => $r['title'], 'description' => $r['description'], 'status' => $r['status'],
    'votes' => (int) $r['vote_count'],
], $stmt->fetchAll())]);
