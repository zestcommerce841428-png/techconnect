<?php
/**
 * Checks and awards badge rules for a user. Called after the actions that could
 * trigger them (asking, answering, being accepted, reputation changes). Cheap
 * COUNT/comparison queries only — no heavy background job needed at this scale.
 */
function award_badge(int $userId, string $code): void
{
    $pdo = db();
    $badge = $pdo->prepare('SELECT id FROM badges WHERE code = ?');
    $badge->execute([$code]);
    $badgeId = $badge->fetchColumn();
    if (!$badgeId) return;

    $pdo->prepare('INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)')
        ->execute([$userId, $badgeId]);
}

function check_badges_for_user(int $userId): void
{
    $pdo = db();

    $questionCount = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE user_id = ?');
    $questionCount->execute([$userId]);
    if ((int) $questionCount->fetchColumn() >= 1) {
        award_badge($userId, 'first_question');
    }

    $answerCount = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE user_id = ?');
    $answerCount->execute([$userId]);
    if ((int) $answerCount->fetchColumn() >= 1) {
        award_badge($userId, 'first_answer');
    }

    $acceptedCount = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE user_id = ? AND is_accepted = 1');
    $acceptedCount->execute([$userId]);
    $accepted = (int) $acceptedCount->fetchColumn();
    if ($accepted >= 1) {
        award_badge($userId, 'first_accepted');
    }
    if ($accepted >= 10) {
        award_badge($userId, 'ten_accepted');
    }

    $rep = $pdo->prepare('SELECT reputation FROM users WHERE id = ?');
    $rep->execute([$userId]);
    $reputation = (int) $rep->fetchColumn();
    if ($reputation >= 50) {
        award_badge($userId, 'fifty_reputation');
    }
    if ($reputation >= 200) {
        award_badge($userId, 'two_hundred_reputation');
    }
}

function check_popular_question_badge(int $questionId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id, vote_score FROM questions WHERE id = ?');
    $stmt->execute([$questionId]);
    $row = $stmt->fetch();
    if ($row && (int) $row['vote_score'] >= 10) {
        award_badge((int) $row['user_id'], 'popular_question');
    }
}
