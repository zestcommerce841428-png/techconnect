<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$rows = db()->query('SELECT name, slug, use_count FROM tags ORDER BY use_count DESC LIMIT 100')->fetchAll();
api_json(['data' => array_map(fn($t) => ['name' => $t['name'], 'slug' => $t['slug'], 'questions' => (int) $t['use_count']], $rows)]);
