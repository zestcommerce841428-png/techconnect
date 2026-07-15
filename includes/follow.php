<?php
function is_following(int $followerId, string $type, int $targetId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followable_type = ? AND followable_id = ?');
    $stmt->execute([$followerId, $type, $targetId]);
    return (bool) $stmt->fetchColumn();
}

function notify_followers_of_new_answer(int $questionId, string $questionTitle, string $questionSlug, int $actorId): void
{
    $pdo = db();
    $notified = [$actorId];

    $watchers = $pdo->prepare("SELECT follower_id FROM follows WHERE followable_type = 'question' AND followable_id = ?");
    $watchers->execute([$questionId]);
    $watchInsert = $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "watched_question_answer", JSON_OBJECT("question_slug", ?, "question_title", ?))');
    foreach ($watchers->fetchAll(PDO::FETCH_COLUMN) as $watcherId) {
        $watcherId = (int) $watcherId;
        if (in_array($watcherId, $notified, true)) continue;
        $watchInsert->execute([$watcherId, $questionSlug, $questionTitle]);
        $notified[] = $watcherId;
    }

    $tagIds = $pdo->prepare('SELECT tag_id FROM question_tags WHERE question_id = ?');
    $tagIds->execute([$questionId]);
    $tagIds = $tagIds->fetchAll(PDO::FETCH_COLUMN);
    if (!$tagIds) return;

    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT follower_id FROM follows WHERE followable_type = 'tag' AND followable_id IN ($placeholders)"
    );
    $stmt->execute($tagIds);
    $followerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $insert = $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "followed_tag_activity", JSON_OBJECT("question_slug", ?, "question_title", ?))');
    foreach ($followerIds as $followerId) {
        $followerId = (int) $followerId;
        if (in_array($followerId, $notified, true)) continue;
        $insert->execute([$followerId, $questionSlug, $questionTitle]);
        $notified[] = $followerId;
    }
}
