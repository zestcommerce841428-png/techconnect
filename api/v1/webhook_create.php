<?php
// POST /api/v1/webhook_create.php — register a new webhook. Requires an admin API key.
// Body: label, url, events (comma-separated from: question.created, answer.created, user.registered, order.paid).
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if ($user['role'] !== 'admin') api_json(['error' => 'Admin access required'], 403);
if (!api_rate_limit($user['key_id'], 10, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$label = trim($input['label'] ?? '');
$url = trim($input['url'] ?? '');
$allowedEvents = ['question.created', 'answer.created', 'user.registered', 'order.paid'];
$events = array_values(array_intersect($allowedEvents, array_map('trim', explode(',', $input['events'] ?? ''))));

if ($label === '') api_json(['error' => 'label is required'], 422);
if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
    api_json(['error' => 'url must be a valid https:// URL'], 422);
}
if (!$events) api_json(['error' => 'events is required (comma-separated): ' . implode(', ', $allowedEvents)], 422);

$secret = bin2hex(random_bytes(24));
$pdo = db();
$pdo->prepare('INSERT INTO webhooks (label, url, secret, events) VALUES (?, ?, ?, ?)')
    ->execute([$label, $url, $secret, implode(',', $events)]);

api_json(['data' => ['id' => (int) $pdo->lastInsertId(), 'secret' => $secret]], 201);
