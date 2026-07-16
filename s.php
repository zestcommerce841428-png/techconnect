<?php
/** Short-link resolver: /s/<code> -> internal path. Counts clicks, then redirects. */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$code = $_GET['code'] ?? '';
if (!preg_match('/^[a-z2-9]{4,12}$/', $code)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

try {
    $stmt = db()->prepare('SELECT id, target_path FROM short_links WHERE code = ?');
    $stmt->execute([$code]);
    $link = $stmt->fetch();
} catch (Throwable $e) {
    $link = null;
}

if (!$link) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Defence in depth: the column is written by short_link_for() which enforces
// internal paths, but re-check before redirecting so a bad row can never turn
// this into an open redirector.
$target = (string) $link['target_path'];
if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

try {
    db()->prepare('UPDATE short_links SET clicks = clicks + 1, last_clicked_at = NOW() WHERE id = ?')
        ->execute([$link['id']]);
} catch (Throwable $e) {
    // Never fail the redirect over analytics.
}

header('Location: ' . $target, true, 301);
exit;
