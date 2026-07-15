<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
verify_csrf();

$pollId = (int) ($_POST['poll_id'] ?? 0);
$action = $_POST['action'] ?? '';
$redirectTo = $_POST['redirect'] ?? '/';
if (!str_starts_with($redirectTo, '/')) $redirectTo = '/';

$poll = $pdo->prepare("SELECT id FROM site_polls WHERE id = ? AND is_active = 1 AND (closes_at IS NULL OR closes_at > NOW())");
$poll->execute([$pollId]);
if (!$poll->fetchColumn()) {
    redirect($redirectTo);
}

if ($action === 'vote') {
    $optionId = (int) ($_POST['option_id'] ?? 0);
    $valid = $pdo->prepare('SELECT id FROM site_poll_options WHERE id = ? AND poll_id = ?');
    $valid->execute([$optionId, $pollId]);
    if ($valid->fetchColumn()) {
        $pdo->prepare('INSERT IGNORE INTO site_poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)')
            ->execute([$pollId, $optionId, $user['id']]);
    }
} elseif ($action === 'dismiss') {
    $pdo->prepare('INSERT IGNORE INTO site_poll_dismissals (poll_id, user_id) VALUES (?, ?)')
        ->execute([$pollId, $user['id']]);
}

redirect($redirectTo);
