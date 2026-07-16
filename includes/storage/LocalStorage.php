<?php
require_once __DIR__ . '/StorageInterface.php';

/**
 * Local disk storage under /uploads — the default, and the behaviour the site
 * has always had. uploads/.htaccess disables script execution there.
 */
class LocalStorage implements StorageInterface
{
    private string $root;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2) . '/uploads';
    }

    public function isConfigured(): bool
    {
        return true; // always available
    }

    public function url(string $path): string
    {
        return '/uploads/' . ltrim($path, '/');
    }

    public function put(string $path, string $contents, string $mime): string
    {
        $path = ltrim($path, '/');
        // Refuse traversal outright rather than sanitising: callers generate
        // these paths, so anything with ".." is a bug or an attack, not input.
        if (str_contains($path, '..')) {
            throw new RuntimeException('Invalid storage path.');
        }
        $full = $this->root . '/' . $path;
        $dir = dirname($full);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create upload directory.');
        }
        if (@file_put_contents($full, $contents) === false) {
            throw new RuntimeException('Could not write file to disk.');
        }
        return $this->url($path);
    }

    public function delete(string $path): bool
    {
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) return false;
        $full = $this->root . '/' . $path;
        return !is_file($full) || @unlink($full);
    }

    public function test(): array
    {
        $key = '_healthcheck/' . bin2hex(random_bytes(6)) . '.txt';
        try {
            $this->put($key, 'test', 'text/plain');
        } catch (Throwable $e) {
            return [false, 'Write failed: ' . $e->getMessage()];
        }
        if (!is_file($this->root . '/' . $key)) {
            return [false, 'File was written but cannot be found on disk.'];
        }
        $this->delete($key);
        return [true, 'Success — local uploads directory is writable.'];
    }
}
