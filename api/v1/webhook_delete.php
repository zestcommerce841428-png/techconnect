<?php
// POST /api/v1/webhook_delete.php — remove a webhook. Requires an admin API key. Body: id.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if ($user['role'] !== 'admin') api_json(['error' => 'Admin access required'], 403);
if (!api_rate_limit($user['key_id'], 10, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int) ($input['id'] ?? 0);
if ($id <= 0) api_json(['error' => 'id is required'], 422);

$stmt = db()->prepare('DELETE FROM webhooks WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount() === 0) api_json(['error' => 'Not found'], 404);

api_json(['data' => ['deleted' => true]]);
