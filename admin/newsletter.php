<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Newsletter — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if (mb_strlen($subject) < 3 || mb_strlen($body) < 10) {
        flash_set('error', 'Please provide a subject and a body.');
        redirect('/admin/newsletter');
    }

    $broadcastId = null;
    $pdo->prepare('INSERT INTO newsletter_broadcasts (subject, body, created_by) VALUES (?, ?, ?)')
        ->execute([$subject, $body, $admin['id']]);
    $broadcastId = (int) $pdo->lastInsertId();

    $subscribers = $pdo->query('SELECT email, unsubscribe_token FROM newsletter_subscribers WHERE unsubscribed_at IS NULL')->fetchAll();
    $sent = 0;
    foreach ($subscribers as $s) {
        $unsubUrl = SITE_URL . '/newsletter_unsubscribe?token=' . $s['unsubscribe_token'];
        $html = nl2br(htmlspecialchars($body)) . '<p style="font-size:12px;color:#888;margin-top:24px;"><a href="' . htmlspecialchars($unsubUrl) . '">Unsubscribe</a></p>';
        if (@send_mail($s['email'], '', $subject, $html)) $sent++;
    }
    $pdo->prepare('UPDATE newsletter_broadcasts SET sent_count = ?, sent_at = NOW() WHERE id = ?')->execute([$sent, $broadcastId]);
    audit_log($admin['id'], 'newsletter_sent', 'newsletter_broadcast', $broadcastId, "{$sent} recipients");

    flash_set('success', "Newsletter sent to {$sent} subscriber(s).");
    redirect('/admin/newsletter');
}

$subscriberCount = (int) $pdo->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE unsubscribed_at IS NULL')->fetchColumn();
$broadcasts = $pdo->query('SELECT * FROM newsletter_broadcasts ORDER BY created_at DESC LIMIT 20')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-2">Newsletter</h1>
<p class="text-sm text-slate-600 mb-6"><?= $subscriberCount ?> active subscriber<?= $subscriberCount === 1 ? '' : 's' ?>.</p>

<div class="bg-white border rounded-lg p-6 max-w-2xl mb-8">
  <h2 class="font-semibold mb-3">Send a broadcast</h2>
  <form method="post" class="space-y-3" onsubmit="return confirm('Send this to all active subscribers now?');">
    <?= csrf_field() ?>
    <input type="text" name="subject" required placeholder="Subject" class="w-full border rounded px-3 py-2 text-sm">
    <textarea name="body" required rows="8" placeholder="Plain text or simple HTML body" class="w-full border rounded px-3 py-2 text-sm"></textarea>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Send to <?= $subscriberCount ?> subscriber(s)</button>
  </form>
</div>

<h2 class="font-semibold mb-2">Past broadcasts</h2>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($broadcasts as $b): ?>
    <div class="p-4 text-sm">
      <div class="font-medium"><?= e($b['subject']) ?></div>
      <div class="text-xs text-slate-500"><?= $b['sent_at'] ? 'Sent to ' . (int) $b['sent_count'] . ' on ' . date('M j, Y', strtotime($b['sent_at'])) : 'Not sent' ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$broadcasts): ?><div class="p-4 text-sm text-slate-500">No broadcasts yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
