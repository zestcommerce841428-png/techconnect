<?php
/**
 * Lightweight rule-based spam heuristics — link density, repeated characters,
 * a small blocklist of common spam phrases, and posting-rate signals already
 * enforced via rate_limit(). Not ML-based; cheap enough to run inline on every
 * post without a queue. Flags land in spam_flags for admin review rather than
 * being auto-deleted, to avoid false-positive takedowns.
 */
function spam_score(string $title, string $body): array
{
    $score = 0;
    $reasons = [];

    $linkCount = preg_match_all('#https?://#i', $body);
    if ($linkCount >= 3) {
        $score += 30;
        $reasons[] = "{$linkCount} links in body";
    }

    if (preg_match('/(.)\1{6,}/', $body)) {
        $score += 15;
        $reasons[] = 'repeated character spam pattern';
    }

    $blocklist = ['buy now', 'click here', 'free money', 'work from home', 'viagra', 'crypto giveaway', 'forex signals'];
    $haystack = mb_strtolower($title . ' ' . $body);
    foreach ($blocklist as $phrase) {
        if (str_contains($haystack, $phrase)) {
            $score += 40;
            $reasons[] = "contains blocked phrase: {$phrase}";
        }
    }

    $capsRatio = mb_strlen($title) > 0 ? mb_strlen(preg_replace('/[^A-Z]/', '', $title)) / mb_strlen($title) : 0;
    if (mb_strlen($title) > 15 && $capsRatio > 0.6) {
        $score += 10;
        $reasons[] = 'excessive capitalization in title';
    }

    return ['score' => $score, 'reasons' => $reasons];
}

function flag_if_spammy(string $targetType, int $targetId, string $title, string $body): void
{
    $result = spam_score($title, $body);
    if ($result['score'] >= 30) {
        db()->prepare('INSERT INTO spam_flags (target_type, target_id, reason, score) VALUES (?, ?, ?, ?)')
            ->execute([$targetType, $targetId, implode('; ', $result['reasons']), $result['score']]);
        require_once __DIR__ . '/logger.php';
        log_warn('spam_flagged', ['type' => $targetType, 'id' => $targetId, 'score' => $result['score']]);
    }
}
