<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text === '' ? 'item' : $text;
}

function unique_slug(string $table, string $base): string
{
    $pdo = db();
    $slug = slugify($base);
    $candidate = $slug;
    $i = 1;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ?");
    while (true) {
        $stmt->execute([$candidate]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $i++;
        $candidate = $slug . '-' . $i;
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function paginate_offset(int $page, int $perPage): int
{
    return max(0, ($page - 1) * $perPage);
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

/**
 * True once migration 031 has added the soft-delete columns.
 *
 * Every public read filters trashed content, but those queries would be fatal
 * on a server where 031 has not been applied yet — and includes/footer.php runs
 * on every page, so that would take the whole site down mid-deploy. This lets
 * the code be deployed before or after the migration, in either order.
 * Result is cached per request (one cheap `LIMIT 0` probe at most).
 *
 * Safe to delete this helper, and inline the filter, once 031 is applied.
 */
function soft_deletes_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            db()->query('SELECT deleted_at FROM pages LIMIT 0');
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/**
 * True once migration 034 has added page_categories and pages.page_category_id.
 * Same purpose as soft_deletes_ready(): lets this code deploy before or after
 * the migration runs, in either order, without a 500 on a missing column.
 */
function page_categories_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            db()->query('SELECT page_category_id FROM pages LIMIT 0');
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** SQL fragment filtering out trashed rows, or '' pre-migration. */
function sd_filter(string $alias = ''): string
{
    if (!soft_deletes_ready()) return '';
    return ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'deleted_at IS NULL';
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = db()->query('SELECT setting_key, setting_value FROM site_settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

/**
 * Estimated reading time in minutes.
 *
 * 200 wpm is the usual English prose average, but code blocks are scanned far
 * more slowly than prose, so they are counted at a third of the rate — otherwise
 * a code-heavy tutorial reports "2 min" and readers stop trusting the number.
 * Always at least 1.
 */
function reading_time(string $markdown): int
{
    $codeWords = 0;
    if (preg_match_all('/```.*?```/s', $markdown, $m)) {
        foreach ($m[0] as $block) {
            $codeWords += str_word_count(strip_tags($block));
        }
        $markdown = preg_replace('/```.*?```/s', '', $markdown) ?? $markdown;
    }
    $proseWords = str_word_count(strip_tags($markdown));
    $minutes = ($proseWords / 200) + ($codeWords / 65);
    return max(1, (int) ceil($minutes));
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

require_once __DIR__ . '/currency.php';

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
