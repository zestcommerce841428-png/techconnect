<?php
// POST /api/v1/consultation_request.php — request a paid consultation with an expert. Requires API key.
// Body: expert_username, message (optional).
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 10, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$expertUsername = trim($input['expert_username'] ?? '');
$message = trim($input['message'] ?? '');
if ($expertUsername === '') api_json(['error' => 'expert_username is required'], 422);

$pdo = db();
$expert = $pdo->prepare(
    'SELECT u.id FROM expert_profiles e JOIN users u ON u.id = e.user_id WHERE u.username = ? AND e.is_approved = 1'
);
$expert->execute([$expertUsername]);
$expertId = $expert->fetchColumn();
if (!$expertId) api_json(['error' => 'Expert not found'], 404);
if ((int) $expertId === $user['id']) api_json(['error' => 'You cannot request a consultation with yourself'], 403);

$pdo->prepare('INSERT INTO consultations (expert_id, requester_id, message) VALUES (?, ?, ?)')
    ->execute([$expertId, $user['id'], $message ?: null]);

api_json(['data' => ['id' => (int) $pdo->lastInsertId(), 'status' => 'requested']], 201);
