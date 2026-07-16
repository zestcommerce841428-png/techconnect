<?php
/**
 * Question of the Day: posts the most engaging recent question to the
 * configured Telegram chat/channel. Intended for a daily Hostinger cron:
 *   php /home/.../cron/question_of_the_day.php
 *
 * Picks the highest-scoring open question from the last 7 days that hasn't
 * been featured before (tracked in site_settings under qotd_last_ids).
 * No-op if Telegram isn't configured.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/telegram_notify.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$pdo = db();

// Recently featured ids (last 30) — avoid repeats.
$recentRow = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'qotd_last_ids'");
$recentRow->execute();
$featured = array_filter(array_map('intval', explode(',', (string) $recentRow->fetchColumn())));

$placeholders = $featured ? implode(',', array_fill(0, count($featured), '?')) : '0';
$stmt = $pdo->prepare(
    "SELECT q.id, q.title, q.slug, q.answer_count, q.view_count, u.username
     FROM questions q JOIN users u ON u.id = q.user_id
     WHERE q.status = 'open' AND q.group_id IS NULL
       AND q.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
       AND q.id NOT IN ($placeholders)
     ORDER BY (q.vote_score * 3 + q.answer_count * 2 + q.view_count / 10) DESC, q.created_at DESC
     LIMIT 1"
);
$stmt->execute($featured);
$q = $stmt->fetch();

if (!$q) {
    echo "No eligible question today.\n";
    exit;
}

$url = SITE_URL . '/q/' . $q['slug'];
$answers = (int) $q['answer_count'];
$msg = "❓ <b>Question of the Day</b>\n\n"
     . htmlspecialchars($q['title'], ENT_QUOTES) . "\n\n"
     . "👤 Asked by " . htmlspecialchars($q['username'], ENT_QUOTES)
     . ($answers > 0 ? " · 💬 {$answers} answer" . ($answers === 1 ? '' : 's') : " · 🙋 Be the first to answer!")
     . "\n\n👉 {$url}";

telegram_notify($msg);

// Remember what we featured (cap at last 30 ids).
$featured[] = (int) $q['id'];
$featured = array_slice($featured, -30);
$pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('qotd_last_ids', ?)
               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
    ->execute([implode(',', $featured)]);

echo "Posted QOTD: {$q['title']}\n";
