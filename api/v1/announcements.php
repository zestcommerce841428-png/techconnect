<?php
// GET /api/v1/announcements.php — currently enabled site announcements.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query('SELECT message, link_url, style FROM announcements WHERE enabled = 1 ORDER BY id DESC');
api_json(['data' => $stmt->fetchAll()]);
