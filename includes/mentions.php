<?php
/**
 * Parses @username mentions out of freshly posted text and notifies each
 * mentioned user (if they exist and aren't the actor themselves).
 */
function notify_mentions(string $body, int $actorId, string $actorUsername, string $questionSlug, string $questionTitle): void
{
    if (!preg_match_all('/@([a-zA-Z0-9_]{3,30})/', $body, $matches)) {
        return;
    }
    $usernames = array_unique($matches[1]);
    if (!$usernames) return;

    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username IN ($placeholders)");
    $stmt->execute($usernames);
    $mentioned = $stmt->fetchAll();

    $insert = $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "mention", JSON_OBJECT("by", ?, "question_slug", ?, "question_title", ?))');
    foreach ($mentioned as $m) {
        if ((int) $m['id'] === $actorId) continue;
        $insert->execute([$m['id'], $actorUsername, $questionSlug, $questionTitle]);
    }
}
