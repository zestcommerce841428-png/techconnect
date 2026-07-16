<?php
/**
 * Short links: puchonow.in/s/<code> -> an internal path.
 * Codes are short, unambiguous (no 0/O/1/l), and reused for the same target so
 * sharing the same question twice yields the same link.
 */

/** Returns the short code for an internal path, creating one on first use. Null if unavailable. */
function short_link_for(string $targetPath, ?int $userId = null): ?string
{
    // Internal, absolute paths only — never let this become an open redirector.
    if ($targetPath === '' || !str_starts_with($targetPath, '/') || str_starts_with($targetPath, '//')) {
        return null;
    }
    $targetPath = mb_substr($targetPath, 0, 500);

    try {
        $find = db()->prepare('SELECT code FROM short_links WHERE target_path = ? LIMIT 1');
        $find->execute([$targetPath]);
        $existing = $find->fetchColumn();
        if ($existing) {
            return (string) $existing;
        }

        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $insert = db()->prepare('INSERT INTO short_links (code, target_path, created_by) VALUES (?, ?, ?)');
        // Retry on the (rare) unique-code collision rather than pre-checking.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            try {
                $insert->execute([$code, $targetPath, $userId]);
                return $code;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e; // not a duplicate-key error
            }
        }
    } catch (Throwable $e) {
        // Table missing mid-migration or DB hiccup: callers fall back to the full URL.
    }
    return null;
}

/** Full short URL, or the canonical URL as a fallback when short links are unavailable. */
function short_url_for(string $targetPath, ?int $userId = null): string
{
    $code = short_link_for($targetPath, $userId);
    return $code ? SITE_URL . '/s/' . $code : SITE_URL . $targetPath;
}
