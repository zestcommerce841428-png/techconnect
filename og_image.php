<?php
/**
 * Dynamic Open Graph image for questions: /og_image.php?slug=<question-slug>
 * Renders a branded 1200x630 PNG with the question title, entirely locally
 * with GD — no external services. Generated images are cached to disk so each
 * question renders at most once.
 */
define('SKIP_HTML_MINIFY', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (!preg_match('/^[a-zA-Z0-9-]{1,220}$/', $slug)) {
    http_response_code(404);
    exit;
}

$cacheDir = __DIR__ . '/uploads/og_cache';
$cacheFile = $cacheDir . '/' . $slug . '.png';

if (!is_file($cacheFile)) {
    $stmt = db()->prepare("SELECT q.title, u.username, q.answer_count FROM questions q JOIN users u ON u.id = q.user_id WHERE q.slug = ? AND q.status <> 'draft'");
    $stmt->execute([$slug]);
    $q = $stmt->fetch();
    if (!$q) {
        http_response_code(404);
        exit;
    }

    $w = 1200; $h = 630;
    $im = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($im, 15, 23, 42);        // slate-900
    $accent = imagecolorallocate($im, 99, 102, 241);  // indigo-500
    $white = imagecolorallocate($im, 248, 250, 252);
    $muted = imagecolorallocate($im, 148, 163, 184);  // slate-400
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    imagefilledrectangle($im, 0, 0, $w, 10, $accent); // top accent bar

    $bold = __DIR__ . '/assets/fonts/DejaVuSans-Bold.ttf';
    $regular = __DIR__ . '/assets/fonts/DejaVuSans.ttf';

    // Brand
    imagettftext($im, 30, 0, 70, 100, $accent, $bold, SITE_NAME);

    // Title, word-wrapped to fit; shrink font for long titles.
    $title = mb_substr($q['title'], 0, 160);
    $size = mb_strlen($title) > 90 ? 40 : 50;
    $maxWidth = $w - 140;
    $words = preg_split('/\s+/', $title);
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $try = $line === '' ? $word : $line . ' ' . $word;
        $box = imagettfbbox($size, 0, $bold, $try);
        if ($box[2] - $box[0] > $maxWidth && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
        if (count($lines) === 4) { $line .= '…'; break; }
    }
    $lines[] = $line;
    $y = 220;
    foreach (array_slice($lines, 0, 5) as $l) {
        imagettftext($im, $size, 0, 70, $y, $white, $bold, $l);
        $y += (int) ($size * 1.5);
    }

    // Footer meta
    $meta = 'Asked by ' . $q['username'] . '  ·  ' . (int) $q['answer_count'] . ' answer' . ((int) $q['answer_count'] === 1 ? '' : 's') . '  ·  puchonow.in';
    imagettftext($im, 22, 0, 70, $h - 60, $muted, $regular, $meta);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    imagepng($im, $cacheFile, 8);
    imagedestroy($im);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . filesize($cacheFile));
readfile($cacheFile);
