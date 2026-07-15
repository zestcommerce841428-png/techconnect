<?php
/**
 * Weekly digest email for users who follow tags: summarizes new questions
 * posted in their followed tags since last run. Intended to be triggered by
 * a Hostinger cron job, e.g.: php /home/user/domains/site/cron/tag_digest.php
 * Run at most once a week — the query window is fixed at 7 days regardless
 * of how often the cron actually fires, so a missed run doesn't lose data
 * for more than one extra digest cycle.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$pdo = db();

$users = $pdo->query(
    "SELECT DISTINCT u.id, u.username, u.email
     FROM users u
     JOIN follows f ON f.follower_id = u.id AND f.followable_type = 'tag'"
)->fetchAll();

$sentCount = 0;
foreach ($users as $user) {
    $tagIds = $pdo->prepare("SELECT followable_id FROM follows WHERE follower_id = ? AND followable_type = 'tag'");
    $tagIds->execute([$user['id']]);
    $tagIds = $tagIds->fetchAll(PDO::FETCH_COLUMN);
    if (!$tagIds) continue;

    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT q.title, q.slug
         FROM questions q
         JOIN question_tags qt ON qt.question_id = q.id
         WHERE qt.tag_id IN ($placeholders)
           AND q.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY q.created_at DESC
         LIMIT 10"
    );
    $stmt->execute($tagIds);
    $questions = $stmt->fetchAll();
    if (!$questions) continue;

    $items = '';
    foreach ($questions as $q) {
        $link = SITE_URL . '/q/' . $q['slug'];
        $items .= '<li><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($q['title']) . '</a></li>';
    }
    $html = '<p>Hi ' . htmlspecialchars($user['username']) . ',</p>'
        . '<p>New questions this week in tags you follow:</p>'
        . '<ul>' . $items . '</ul>'
        . '<p><a href="' . htmlspecialchars(SITE_URL) . '/feed">View your full feed</a></p>';

    if (!user_email_pref((int) $user['id'], 'tag_digest')) continue;
    if (send_mail($user['email'], $user['username'], 'Your weekly digest — ' . SITE_NAME, $html)) {
        $sentCount++;
    }
}

echo "Digest sent to {$sentCount} user(s).\n";
