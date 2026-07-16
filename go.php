<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT id, target_url FROM affiliate_links WHERE slug = ?');
$stmt->execute([$slug]);
$link = $stmt->fetch();

if (!$link) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

db()->prepare('UPDATE affiliate_links SET clicks = clicks + 1 WHERE id = ?')->execute([$link['id']]);
db()->prepare('INSERT INTO affiliate_clicks (affiliate_link_id, ip_hash) VALUES (?, ?)')
    ->execute([$link['id'], hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'))]);

header('Location: ' . $link['target_url'], true, 302);
exit;
