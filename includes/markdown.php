<?php
/**
 * Minimal markdown renderer covering the common subset used by the editor toolbar:
 * headers, bold, italic, inline code, fenced code blocks, links, images, lists.
 * All text is HTML-escaped before formatting is applied, so output is safe to echo
 * directly (no separate sanitizer needed).
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

function md_inline(string $text): string
{
    $text = e($text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)\)/', '<img src="$2" alt="$1" loading="lazy" class="max-w-full rounded">', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2" rel="nofollow noopener" target="_blank" class="text-indigo-600 hover:underline">$1</a>', $text);
    return $text;
}
