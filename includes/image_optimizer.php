<?php
/**
 * Image optimisation before storage.
 *
 * This is the highest-leverage cost control in the whole storage stack: a phone
 * photo is routinely 4-8 MB at 4000px wide, but it is displayed in a ~800px
 * column. Downscaling and recompressing typically cuts 85-95% of the bytes —
 * and those bytes are charged twice, once as storage and again as egress every
 * single time the image is viewed. Optimising once at upload beats any CDN
 * tuning later.
 *
 * WebP is used when the server supports it (~30% smaller than JPEG at
 * equivalent quality, supported by every browser since 2020), otherwise it
 * re-encodes to the original format so nothing ever breaks.
 *
 * Uses GD (bundled with essentially all shared hosting). Never throws: if
 * anything is unsupported, the original bytes are returned unchanged.
 */

/**
 * @return array{0:string,1:string,2:string} [optimised bytes, mime, extension]
 */
function optimize_image(string $bytes, string $mime): array
{
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => '',
    };
    // Non-images and GIFs pass through untouched — GIFs are usually animated and
    // GD would silently flatten them to a single frame.
    if ($ext === '' || $ext === 'gif' || !extension_loaded('gd')) {
        return [$bytes, $mime, $ext ?: 'bin'];
    }

    if (setting('image_optimize_enabled', '1') !== '1') {
        return [$bytes, $mime, $ext];
    }

    $maxDim = max(200, (int) setting('image_max_dimension', '1600'));
    $quality = min(100, max(40, (int) setting('image_quality', '82')));

    try {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return [$bytes, $mime, $ext];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxDim / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        if ($scale < 1) {
            $dst = imagecreatetruecolor($nw, $nh);
            // Preserve transparency for PNG/WebP; without this, transparent
            // areas render as black after resampling.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
        } else {
            $dst = $src;
        }

        $useWebp = setting('image_prefer_webp', '1') === '1' && function_exists('imagewebp');
        ob_start();
        if ($useWebp) {
            imagewebp($dst, null, $quality);
            $outMime = 'image/webp';
            $outExt = 'webp';
        } elseif ($mime === 'image/png') {
            // PNG quality is 0-9 (higher = smaller), inverse of JPEG's scale.
            imagepng($dst, null, 8);
            $outMime = 'image/png';
            $outExt = 'png';
        } else {
            imagejpeg($dst, null, $quality);
            $outMime = 'image/jpeg';
            $outExt = 'jpg';
        }
        $out = (string) ob_get_clean();
        imagedestroy($dst);

        // Never ship a "optimised" file that is larger than what we started with
        // (small or already-compressed images can grow on re-encode).
        if ($out === '' || strlen($out) >= strlen($bytes)) {
            return [$bytes, $mime, $ext];
        }
        return [$out, $outMime, $outExt];
    } catch (Throwable $e) {
        error_log('image optimise failed: ' . $e->getMessage());
        return [$bytes, $mime, $ext];
    }
}

/** Human-readable byte size. */
function format_bytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB', 'MB', 'GB', 'TB'];
    $i = -1;
    $val = $bytes;
    do {
        $val /= 1024;
        $i++;
    } while ($val >= 1024 && $i < count($units) - 1);
    return round($val, $val < 10 ? 1 : 0) . ' ' . $units[$i];
}
