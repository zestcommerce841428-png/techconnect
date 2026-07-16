<?php
/**
 * HTML output minifier, applied as an ob_start() callback from auth.php.
 *
 * Conservative by design for correctness on user-generated content:
 * - <pre>, <textarea>, <script>, <style> blocks are protected byte-for-byte
 *   (code answers and markdown code blocks must keep their whitespace).
 * - Whitespace runs collapse to a single space rather than being removed,
 *   because a space between inline elements (links, badges) is meaningful.
 * - Non-HTML output (JSON APIs, redirects, downloads) passes through untouched.
 * - HTML comments are stripped, except conditional/comment-critical ones.
 */
function minify_html_output(string $html): string
{
    $trimmed = ltrim($html);
    // Only minify full HTML documents; leave JSON, XML feeds, plain text alone.
    if (stripos($trimmed, '<!doctype') !== 0 && stripos($trimmed, '<html') !== 0) {
        return $html;
    }

    $protected = [];
    $html = preg_replace_callback(
        '#<(pre|textarea|script|style)\b[^>]*>.*?</\1\s*>#si',
        function (array $m) use (&$protected): string {
            $key = "\x1A" . count($protected) . "\x1A";
            $protected[$key] = $m[0];
            return $key;
        },
        $html
    ) ?? $html;

    $html = preg_replace('/<!--(?!\[if|<!\[endif)(?!\s*ko).*?-->/s', '', $html) ?? $html;
    $html = preg_replace('/\s+/', ' ', $html) ?? $html;
    // Native lazy-loading for images that don't declare their own strategy —
    // big win on question pages full of user-uploaded screenshots.
    $html = preg_replace('/<img (?![^>]*\bloading=)/i', '<img loading="lazy" decoding="async" ', $html) ?? $html;

    return trim(strtr($html, $protected));
}
