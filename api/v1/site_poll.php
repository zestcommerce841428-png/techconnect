<?php
// GET /api/v1/site_poll.php — the currently active site-wide poll, with live vote counts.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$poll = db()->query(
    "SELECT id, question FROM site_polls WHERE is_active = 1 AND (closes_at IS NULL OR closes_at > NOW())
     ORDER BY created_at DESC LIMIT 1"
)->fetch();
if (!$poll) api_json(['data' => null]);

$options = db()->prepare(
    'SELECT o.id, o.label, COUNT(v.user_id) AS votes FROM site_poll_options o
     LEFT JOIN site_poll_votes v ON v.option_id = o.id
     WHERE o.poll_id = ? GROUP BY o.id ORDER BY o.sort_order'
);
$options->execute([$poll['id']]);

api_json(['data' => [
    'question' => $poll['question'],
    'options' => array_map(fn($o) => [
        'id' => (int) $o['id'], 'label' => $o['label'], 'votes' => (int) $o['votes'],
    ], $options->fetchAll()),
]]);
