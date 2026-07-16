<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_widget.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
verify_csrf();

$redirectTo = $_POST['redirect'] ?? '/';
if (!str_starts_with($redirectTo, '/')) $redirectTo = '/';

$targetType = $_POST['target_type'] ?? '';
$targetId = (int) ($_POST['target_id'] ?? 0);
$reasonKey = $_POST['reason_key'] ?? '';

$reasons = report_reasons();
if (!isset($reasons[$reasonKey]) || !in_array($targetType, ['question', 'answer', 'comment', 'user'], true) || $targetId < 1) {
    flash_set('error', 'Please choose a valid reason.');
    redirect($redirectTo);
}

if (!rate_limit('report_content', 10, 3600)) {
    flash_set('error', 'You have submitted too many reports recently. Please try again later.');
    redirect($redirectTo);
}

$pdo = db();

$table = match ($targetType) {
    'question' => 'questions', 'answer' => 'answers', 'comment' => 'comments', 'user' => 'users',
};
$exists = $pdo->prepare("SELECT id FROM {$table} WHERE id = ?");
$exists->execute([$targetId]);
if (!$exists->fetchColumn()) {
    flash_set('error', 'That content no longer exists.');
    redirect($redirectTo);
}

if ($targetType === 'user' && $targetId === $user['id']) {
    flash_set('error', "You can't report yourself.");
    redirect($redirectTo);
}

$dupe = $pdo->prepare("SELECT id FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status = 'open'");
$dupe->execute([$user['id'], $targetType, $targetId]);
if ($dupe->fetchColumn()) {
    flash_set('success', "You've already reported this — our team will review it.");
    redirect($redirectTo);
}

$pdo->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)')
    ->execute([$user['id'], $targetType, $targetId, $reasons[$reasonKey]]);

flash_set('success', 'Thanks — this has been reported to our moderation team.');
redirect($redirectTo);
