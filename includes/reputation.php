<?php
/** Adjusts a user's reputation and records the change in the reputation_events ledger. */
function award_rep(int $userId, int $points, string $reason, ?string $refType = null, ?int $refId = null): void
{
    if ($points === 0) return;
    $pdo = db();
    // Reputation never goes below 1.
    $pdo->prepare('UPDATE users SET reputation = GREATEST(1, reputation + ?) WHERE id = ?')->execute([$points, $userId]);
    $pdo->prepare('INSERT INTO reputation_events (user_id, points, reason, ref_type, ref_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $points, $reason, $refType, $refId]);
}
