<?php
/**
 * Structured JSON-lines logging to logs/app-YYYY-MM-DD.log.
 * The logs/ directory is denied over HTTP via .htaccess, and log files
 * match the existing FilesMatch .log denial as a second layer.
 */
function app_log(string $level, string $message, array $context = []): void
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $line = json_encode([
        'ts' => date('c'),
        'level' => $level,
        'message' => $message,
        'context' => $context ?: (object) [],
        'ip' => function_exists('client_ip') ? (client_ip() ?: null) : ($_SERVER['REMOTE_ADDR'] ?? null),
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'user_id' => $_SESSION['user_id'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
    @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function log_info(string $message, array $context = []): void { app_log('info', $message, $context); }
function log_warn(string $message, array $context = []): void { app_log('warn', $message, $context); }
function log_error(string $message, array $context = []): void { app_log('error', $message, $context); }
