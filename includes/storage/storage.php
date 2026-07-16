<?php
/**
 * Storage factory. Upload code calls storage() and never knows or cares which
 * backend is active — same shape as includes/payments/payments.php.
 *
 * Adding a provider means adding one class implementing StorageInterface and one
 * line in storage_driver_defs(). Any S3-compatible service (AWS S3, Cloudflare
 * R2, DigitalOcean Spaces, Backblaze B2, Wasabi, MinIO) needs no new code at
 * all — only different endpoint/region/bucket settings.
 */
require_once __DIR__ . '/StorageInterface.php';
require_once __DIR__ . '/LocalStorage.php';
require_once __DIR__ . '/S3Storage.php';

/** Selectable backends, for the admin dropdown. */
function storage_driver_defs(): array
{
    return [
        'local' => 'Local disk (this server)',
        's3' => 'S3-compatible (AWS S3, Cloudflare R2, DO Spaces, Backblaze B2, Wasabi, MinIO)',
    ];
}

/**
 * The active backend. Falls back to local disk if the configured remote is
 * incomplete, so a half-finished S3 setup can never break uploads entirely.
 */
function storage(): StorageInterface
{
    static $instance = null;
    if ($instance !== null) {
        return $instance;
    }
    $driver = setting('storage_driver', 'local');
    if ($driver === 's3') {
        $s3 = new S3Storage();
        if ($s3->isConfigured()) {
            return $instance = $s3;
        }
    }
    return $instance = new LocalStorage();
}
