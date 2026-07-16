<?php
// Lightweight health check for uptime monitors: verifies PHP + DB connectivity.
// Returns 200 {"status":"ok"} or 503 {"status":"degraded"}. No auth/session needed.
// Browsers get redirected to the human-friendly /status page; anything that
// asks for JSON explicitly (uptime monitors, curl, ?format=json) gets JSON.
define('SKIP_IP_BLOCK_CHECK', true);

$wantsJson = isset($_GET['format']) && $_GET['format'] === 'json';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (!$wantsJson && str_contains($accept, 'text/html')) {
    header('Location: /status');
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

$checks = ['php' => true, 'db' => false];
try {
    require_once __DIR__ . '/includes/db.php';
    $checks['db'] = (bool) db()->query('SELECT 1')->fetchColumn();
} catch (Throwable $e) {
    $checks['db'] = false;
}

$ok = !in_array(false, $checks, true);
http_response_code($ok ? 200 : 503);
echo json_encode(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks, 'time' => date('c')]);
