<?php
// GET /api/v1/badges.php — badge catalog.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query('SELECT code, name, description, icon, tier FROM badges ORDER BY tier, name');
api_json(['data' => $stmt->fetchAll()]);
