<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);

api_json(['data' => ['username' => $user['username'], 'role' => $user['role']]]);
