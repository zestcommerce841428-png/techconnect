<?php
// Authenticated question creation via API key — POST only.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['error' => 'POST required'], 405);

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
api_require_write_scope($user);
if (!api_rate_limit($user['key_id'], 20, 3600)) api_json(['error' => 'Rate limit exceeded'], 429);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$title = trim($input['title'] ?? '');
$body = trim($input['body'] ?? '');
$categorySlug = trim($input['category'] ?? '');
$tags = array_slice(array_filter(array_map('trim', explode(',', $input['tags'] ?? ''))), 0, 5);

if (mb_strlen($title) < 10 || mb_strlen($title) > 200) api_json(['error' => 'Title must be 10-200 characters'], 422);
if (mb_strlen($body) < 20) api_json(['error' => 'Body must be at least 20 characters'], 422);

$pdo = db();
$catStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
$catStmt->execute([$categorySlug]);
$categoryId = $catStmt->fetchColumn();
if (!$categoryId) api_json(['error' => 'Unknown category'], 422);

$pdo->beginTransaction();
try {
    $slug = unique_slug('questions', $title);
    $pdo->prepare('INSERT INTO questions (user_id, category_id, title, slug, body) VALUES (?, ?, ?, ?, ?)')
        ->execute([$user['id'], $categoryId, $title, $slug, $body]);
    $questionId = (int) $pdo->lastInsertId();

    foreach ($tags as $name) {
        $name = strtolower($name);
        if ($name === '') continue;
        $find = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
        $find->execute([$name]);
        $tagId = $find->fetchColumn();
        if (!$tagId) {
            $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, slugify($name)]);
            $tagId = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT IGNORE INTO question_tags (question_id, tag_id) VALUES (?, ?)')->execute([$questionId, $tagId]);
        $pdo->prepare('UPDATE tags SET use_count = use_count + 1 WHERE id = ?')->execute([$tagId]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    api_json(['error' => 'Could not create question'], 500);
}

api_json(['data' => ['id' => $questionId, 'url' => SITE_URL . '/q/' . $slug]], 201);
