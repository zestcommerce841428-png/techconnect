<?php
/**
 * Auto-resolves expired question bounties (StackOverflow-style): the highest-voted
 * answer wins the bounty; if the question has no answers at all, the reputation is
 * refunded to the asker rather than lost. Intended for a daily Hostinger cron job,
 * e.g.: 0 3 * * * php /path/to/cron/resolve_bounties.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/reputation.php';

$pdo = db();
$expired = $pdo->query(
    "SELECT id, title, slug, user_id, bounty_points FROM questions
     WHERE bounty_points > 0 AND bounty_expires_at IS NOT NULL AND bounty_expires_at <= NOW()"
)->fetchAll();

foreach ($expired as $q) {
    $top = $pdo->prepare(
        'SELECT id, user_id FROM answers WHERE question_id = ? ORDER BY vote_score DESC, created_at ASC LIMIT 1'
    );
    $top->execute([$q['id']]);
    $winner = $top->fetch();

    if ($winner) {
        award_rep((int) $winner['user_id'], (int) $q['bounty_points'], 'bounty_awarded', 'question', (int) $q['id']);
        $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "bounty_awarded", JSON_OBJECT("question_slug", ?, "question_title", ?, "points", ?))')
            ->execute([$winner['user_id'], $q['slug'], $q['title'], (int) $q['bounty_points']]);
        echo "Awarded {$q['bounty_points']} rep to user {$winner['user_id']} for \"{$q['title']}\"\n";
    } else {
        award_rep((int) $q['user_id'], (int) $q['bounty_points'], 'bounty_refunded', 'question', (int) $q['id']);
        $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "bounty_refunded", JSON_OBJECT("question_slug", ?, "question_title", ?, "points", ?))')
            ->execute([$q['user_id'], $q['slug'], $q['title'], (int) $q['bounty_points']]);
        echo "Refunded {$q['bounty_points']} rep to asker for \"{$q['title']}\" (no answers)\n";
    }

    $pdo->prepare('UPDATE questions SET bounty_points = 0, bounty_expires_at = NULL WHERE id = ?')->execute([$q['id']]);
}

if (!$expired) {
    echo "No expired bounties.\n";
}
