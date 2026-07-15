<?php
// POST /api/v1/draft_save.php — create or update a draft question. Requires API key.
// Body: title, body, category (optional), id (optional, to update an existing draft you own).
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 30, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$title = trim($input['title'] ?? '');
$body = trim($input['body'] ?? '');
$draftId = (int) ($input['id'] ?? 0) ?: null;
$categorySlug = trim($input['category'] ?? '');

if (mb_strlen($title) < 3) api_json(['error' => 'title must be at least 3 characters'], 422);

$pdo = db();
$categoryId = null;
if ($categorySlug !== '') {
    $cat = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $cat->execute([$categorySlug]);
    $categoryId = $cat->fetchColumn() ?: null;
}

if ($draftId) {
    $own = $pdo->prepare("SELECT id FROM questions WHERE id = ? AND user_id = ? AND status = 'draft'");
    $own->execute([$draftId, $user['id']]);
    if (!$own->fetchColumn()) api_json(['error' => 'Draft not found'], 404);
    $pdo->prepare('UPDATE questions SET title = ?, body = ?, category_id = COALESCE(?, category_id) WHERE id = ?')
        ->execute([$title, $body, $categoryId, $draftId]);
    api_json(['data' => ['id' => $draftId]]);
}

$slug = unique_slug('questions', $title ?: 'draft-' . bin2hex(random_bytes(4)));
$pdo->prepare("INSERT INTO questions (user_id, category_id, title, slug, body, status) VALUES (?, ?, ?, ?, ?, 'draft')")
    ->execute([$user['id'], $categoryId, $title, $slug, $body]);

api_json(['data' => ['id' => (int) $pdo->lastInsertId()]], 201);
