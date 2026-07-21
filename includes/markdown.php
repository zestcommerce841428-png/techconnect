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

        // A video URL alone on a line becomes a player; inside a sentence it
        // stays an ordinary link.
        if ($embed = md_video_embed($line)) {
            $html[] = $embed;
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
 * Renders a bare video URL on its own line as a responsive embed, or null if the
 * line is not a recognised video link.
 *
 * Only the video ID is ever extracted, with a strict charset, and the iframe src
 * is then rebuilt from a hardcoded template. The author's URL never reaches the
 * src attribute, so no crafted "youtube.com..." string can smuggle anything in.
 * youtube-nocookie.com is used so embeds do not set tracking cookies on visitors
 * (which also keeps the cookie banner honest).
 */
function md_video_embed(string $line): ?string
{
    $line = trim($line);

    // Each provider: [regex, src template, sizing, iframe title].
    //
    // The security rule that must never be relaxed: the regex captures nothing
    // but an ID, and the src is rebuilt from the hardcoded template. No part of
    // the user's URL — not the query string, not the fragment, not the host —
    // ever reaches the iframe. Every capture group is charset-limited, so a
    // capture cannot smuggle a quote, an angle bracket or a second attribute.
    // Adding a provider means adding a strict pattern here, never loosening one.
    //
    // Sizing: ['ratio' => n] for anything with an aspect ratio (video), or
    // ['px' => n] for players with a fixed natural height (audio, code) — a
    // 16:9 box around a Spotify player is mostly empty space.
    $providers = [
        // ---- Video -------------------------------------------------------
        // youtu.be/ID · youtube.com/watch?v=ID · /embed/ID · /shorts/ID · /live/ID
        ['~^https?://(?:www\.)?(?:youtube\.com/(?:watch\?(?:[\w=&;-]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#][^\s]*)?$~i',
            'https://www.youtube-nocookie.com/embed/%s', ['ratio' => 56.25], 'Embedded video'],
        // vimeo.com/123456789
        ['~^https?://(?:www\.)?vimeo\.com/(\d{6,12})(?:[?#][^\s]*)?$~i',
            'https://player.vimeo.com/video/%s', ['ratio' => 56.25], 'Embedded video'],
        // dailymotion.com/video/xABC123 · dai.ly/xABC123
        ['~^https?://(?:www\.)?(?:dailymotion\.com/video/|dai\.ly/)([A-Za-z0-9]{5,12})(?:[?&#][^\s]*)?$~i',
            'https://www.dailymotion.com/embed/video/%s', ['ratio' => 56.25], 'Embedded video'],
        // loom.com/share/<32 hex> — screen recordings, the natural way to answer
        // a "how do I do this" question with a walkthrough.
        ['~^https?://(?:www\.)?loom\.com/(?:share|embed)/([a-f0-9]{32})(?:[?&#][^\s]*)?$~i',
            'https://www.loom.com/embed/%s', ['ratio' => 56.25], 'Embedded screen recording'],
        // streamable.com/abc12
        ['~^https?://(?:www\.)?streamable\.com/(?:e/)?([a-z0-9]{4,10})(?:[?&#][^\s]*)?$~i',
            'https://streamable.com/e/%s', ['ratio' => 56.25], 'Embedded video'],
        // drive.google.com/file/d/<id>/view — pairs with the storage integrations.
        ['~^https?://drive\.google\.com/file/d/([A-Za-z0-9_-]{10,60})(?:/[^\s]*)?$~i',
            'https://drive.google.com/file/d/%s/preview', ['ratio' => 56.25], 'Embedded video'],

        // ---- Code --------------------------------------------------------
        // codepen.io/<user>/pen/<id> — two captures, both charset-limited.
        ['~^https?://(?:www\.)?codepen\.io/([A-Za-z0-9_-]{1,40})/(?:pen|embed|details|full)/([A-Za-z0-9]{5,12})(?:[?&#][^\s]*)?$~i',
            'https://codepen.io/%s/embed/%s?default-tab=result', ['px' => 420], 'Embedded code example'],
        // jsfiddle.net/<user>/<id> and jsfiddle.net/<id>
        ['~^https?://(?:www\.)?jsfiddle\.net/([A-Za-z0-9_-]{1,40})/([A-Za-z0-9]{4,12})/?(?:[?&#][^\s]*)?$~i',
            'https://jsfiddle.net/%s/%s/embedded/result,html,css,js/', ['px' => 420], 'Embedded code example'],
        // codesandbox.io/s/<id> · /embed/<id> · /p/sandbox/<id>
        ['~^https?://(?:www\.)?codesandbox\.io/(?:s|embed|p/sandbox)/([A-Za-z0-9_-]{4,40})(?:[?&#][^\s]*)?$~i',
            'https://codesandbox.io/embed/%s', ['px' => 460], 'Embedded code sandbox'],

        // ---- Audio -------------------------------------------------------
        // open.spotify.com/{track|album|playlist|episode|show}/<id>
        ['~^https?://open\.spotify\.com/(track|album|playlist|episode|show)/([A-Za-z0-9]{16,30})(?:[?&#][^\s]*)?$~i',
            'https://open.spotify.com/embed/%s/%s', ['px' => 152], 'Embedded audio'],
    ];

    foreach ($providers as [$re, $template, $size, $title]) {
        if (!preg_match($re, $line, $m)) {
            continue;
        }
        // vsprintf over every capture: providers with a user+id pair need two.
        $src = vsprintf($template, array_slice($m, 1));
        $common = ' title="' . e($title) . '" loading="lazy" frameborder="0"'
            . ' allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"'
            . ' referrerpolicy="strict-origin-when-cross-origin" allowfullscreen';

        if (isset($size['px'])) {
            // Fixed-height player: no ratio box, so nothing is letterboxed.
            return '<iframe src="' . e($src) . '"' . $common
                . ' class="w-full my-4 rounded-lg border dark:border-slate-800"'
                . ' style="height:' . (int) $size['px'] . 'px"></iframe>';
        }
        // Ratio box without aspect-ratio utilities, so it survives email/print.
        return '<div class="relative w-full my-4 rounded-lg overflow-hidden bg-slate-900" style="padding-top:' . e((string) $size['ratio']) . '%">'
            . '<iframe src="' . e($src) . '"' . $common
            . ' class="absolute inset-0 w-full h-full"></iframe></div>';
    }
    return null;
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
