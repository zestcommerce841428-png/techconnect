<?php
/** Renders an admin-managed ad slot by key, or nothing if disabled/empty/Pro user browsing ad-free. */
function ad_slot(string $key): string
{
    if (!empty($GLOBALS['user']['is_pro'])) return '';
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = db()->prepare('SELECT html_content, image_path, link_url FROM ad_slots WHERE slot_key = ? AND enabled = 1');
        $stmt->execute([$key]);
        $cache[$key] = $stmt->fetch() ?: null;
    }
    $slot = $cache[$key];
    if (!$slot) return '';
    if (!empty($slot['html_content'])) {
        return $slot['html_content'];
    }
    if (!empty($slot['image_path'])) {
        $img = '<img src="' . e($slot['image_path']) . '" class="w-full rounded" alt="Advertisement">';
        return $slot['link_url'] ? '<a href="' . e($slot['link_url']) . '" rel="sponsored noopener" target="_blank">' . $img . '</a>' : $img;
    }
    return '';
}
