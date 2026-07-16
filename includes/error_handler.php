<?php
/**
 * Global error/exception handler: turns any uncaught PHP error into the
 * branded error.php page instead of a raw stack trace, blank screen, or
 * partial JSON. Registered once from config.php so it covers every entry
 * point (pages, cron, api/*) — api/* endpoints catch their own Throwables
 * before this ever fires, so this is strictly the last-resort fallback.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function render_fatal_error_page(int $code = 500): void
{
    if (headers_sent()) return;
    while (ob_get_level() > 0) ob_end_clean();
    $_SERVER['REDIRECT_STATUS'] = $code;
    require __DIR__ . '/../error.php';
}

set_exception_handler(function (Throwable $e): void {
    require_once __DIR__ . '/logger.php';
    log_error('Uncaught exception', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    render_fatal_error_page(500);
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    require_once __DIR__ . '/logger.php';
    log_error('Fatal error', ['message' => $err['message'], 'file' => $err['file'], 'line' => $err['line']]);
    render_fatal_error_page(500);
});
