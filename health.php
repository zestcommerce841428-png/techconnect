<?php
// Lightweight health check for uptime monitors: verifies PHP + DB connectivity.
// Returns 200 {"status":"ok"} or 503 {"status":"degraded"}. No auth/session needed.
define('SKIP_IP_BLOCK_CHECK', true);
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
