<?php
/**
 * Minimal markdown renderer covering the common subset used by the editor toolbar:
 * headers, bold, italic, inline code, fenced code blocks, links, images, lists.
 *
 * Text is HTML-escaped before formatting is applied. That alone is NOT enough for
 * link/image targets: htmlspecialchars() does not touch "javascript:" or
 * "data:text/html", so `[x](javascript:alert(1))` used to render a working XSS
 * link that any member could post. URLs therefore go through md_safe_url(),
 * which allows only http/https/mailto and relative paths.
 */
function render_markdown(string $source): string
{
    $lines = explode("\n", str_replace("\r\n", "\n", $source));
    $html = [];
    $inCode = false;
    $codeLang = '';
    $codeBuffer = [];
    $inList = false;

    foreach ($lines as $line) {
        if (preg_match('/^```(\w*)\s*$/', $line, $m)) {
            if (!$inCode) {
                $inCode = true;
                $codeLang = $m[1];
                $codeBuffer = [];
            } else {
                $inCode = false;
                $langClass = $codeLang ? ' class="language-' . e($codeLang) . '"' : '';
                $html[] = '<pre><code' . $langClass . '>' . e(implode("\n", $codeBuffer)) . '</code></pre>';
            }
            continue;
        }
        if ($inCode) {
            $codeBuffer[] = $line;
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            $level = strlen($m[1]);
            $html[] = "<h{$level}>" . md_inline($m[2]) . "</h{$level}>";
            continue;
        }

        if (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
            if (!$inList) { $html[] = '<ul>'; $inList = true; }
            $html[] = '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }
        if ($inList) { $html[] = '</ul>'; $inList = false; }

        if (trim($line) === '') {
            $html[] = '';
            continue;
        }

        $html[] = '<p>' . md_inline($line) . '</p>';
    }
    if ($inList) { $html[] = '</ul>'; }
    if ($inCode) {
        $html[] = '<pre><code>' . e(implode("\n", $codeBuffer)) . '</code></pre>';
    }

    return implode("\n", array_filter($html, fn($l) => $l !== ''));
}

/**
 * Returns the URL if it is safe to place in href/src, or null to refuse it.
 *
 * Allowed: http, https, mailto, protocol-relative, site-relative, anchors, and
 * scheme-less URLs (example.com/x). Everything else — javascript:, data:, vbscript:,
 * file: — is rejected. The scheme is probed with whitespace and control characters
 * stripped, because "java\tscript:alert(1)" and "java\nscript:" are treated as a
 * scheme by browsers but slip past a naive string comparison.
 */
function md_safe_url(string $url): ?string
{
    $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    $probe = strtolower(preg_replace('/[\s\x00-\x1f\x7f]+/', '', $decoded) ?? '');
    if ($probe === '') {
        return null;
    }
    if ($probe[0] === '/' || $probe[0] === '#' || $probe[0] === '?') {
        return $url; // relative / anchor / query — cannot carry a scheme
    }
    if (preg_match('~^(https?:|mailto:)~', $probe)) {
        return $url;
    }
    // No colon at all means no scheme, so it cannot be javascript: et al.
    if (!str_contains($probe, ':')) {
        return $url;
    }
    return null;
}

function md_inline(string $text): string
{
    $text = e($text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);

    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function (array $m): string {
        $url = md_safe_url($m[2]);
        // Refused URL: keep the author's text visible, drop the dangerous target.
        if ($url === null) {
            return '<em>' . $m[1] . '</em>';
        }
        return '<img src="' . $url . '" alt="' . $m[1] . '" loading="lazy" decoding="async" class="max-w-full rounded">';
    }, $text) ?? $text;

    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function (array $m): string {
        $url = md_safe_url($m[2]);
        if ($url === null) {
            return $m[1];
        }
        return '<a href="' . $url . '" rel="nofollow noopener" target="_blank" class="text-indigo-600 hover:underline">' . $m[1] . '</a>';
    }, $text) ?? $text;

    return $text;
}
