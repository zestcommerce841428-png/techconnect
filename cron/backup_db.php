<?php
/**
 * Nightly DB backup via mysqldump, keeping the last N days locally under
 * /backups (outside the web root's document tree is preferable on Hostinger;
 * adjust $backupDir to a non-public path if your hosting layout allows it).
 * Intended to be triggered by a Hostinger cron job:
 *   php /home/user/domains/site/cron/backup_db.php
 */
require_once __DIR__ . '/../config.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0700, true);
}

$filename = $backupDir . '/backup_' . date('Y-m-d_His') . '.sql.gz';

$mysqldumpBin = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
$cmd = sprintf(
    '%s --host=%s --user=%s %s %s | gzip > %s',
    escapeshellcmd($mysqldumpBin),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '',
    escapeshellarg(DB_NAME),
    escapeshellarg($filename)
);

exec($cmd, $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Backup failed with exit code {$exitCode}\n");
    exit(1);
}

// Retention: keep the last 14 daily backups, delete anything older.
$keepDays = 14;
$files = glob($backupDir . '/backup_*.sql.gz');
usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
foreach (array_slice($files, $keepDays) as $old) {
    unlink($old);
}

echo "Backup written to {$filename}\n";
