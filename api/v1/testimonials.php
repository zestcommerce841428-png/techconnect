<?php
// GET /api/v1/testimonials.php — published testimonials.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    'SELECT author_name, author_role, quote, rating FROM testimonials
     WHERE is_published = 1 ORDER BY sort_order, id DESC'
);
api_json(['data' => array_map(fn($t) => [
    'author_name' => $t['author_name'], 'author_role' => $t['author_role'],
    'quote' => $t['quote'], 'rating' => $t['rating'] !== null ? (int) $t['rating'] : null,
], $stmt->fetchAll())]);
