<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$prefKeys = [
    'email_on_answer' => 'Email me when someone answers my question',
    'email_on_mention' => 'Email me when someone @mentions me',
    'email_on_message' => 'Email me when I receive a private message',
    'email_tag_digest' => 'Send me the weekly digest for tags I follow',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [];
    foreach (array_keys($prefKeys) as $key) {
        $values[$key] = isset($_POST[$key]) ? 1 : 0;
    }
    $muteDays = (int) ($_POST['mute_days'] ?? 0);
    $mutedUntil = $muteDays > 0 ? date('Y-m-d H:i:s', time() + $muteDays * 86400) : null;

    $pdo->prepare(
        'INSERT INTO notification_prefs (user_id, email_on_answer, email_on_mention, email_on_message, email_tag_digest, muted_until)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE email_on_answer = VALUES(email_on_answer), email_on_mention = VALUES(email_on_mention),
                                 email_on_message = VALUES(email_on_message), email_tag_digest = VALUES(email_tag_digest),
                                 muted_until = VALUES(muted_until)'
    )->execute([$user['id'], $values['email_on_answer'], $values['email_on_mention'], $values['email_on_message'], $values['email_tag_digest'], $mutedUntil]);
    flash_set('success', 'Notification preferences saved.');
    redirect('/notification_settings');
}

$stmt = $pdo->prepare('SELECT * FROM notification_prefs WHERE user_id = ?');
$stmt->execute([$user['id']]);
$prefs = $stmt->fetch() ?: array_fill_keys(array_keys($prefKeys), 1);

$pageTitle = 'Notification settings — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-lg mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Email notifications</h1>
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <?php foreach ($prefKeys as $key => $label): ?>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="<?= e($key) ?>" <?= !empty($prefs[$key]) ? 'checked' : '' ?>>
        <?= e($label) ?>
      </label>
    <?php endforeach; ?>
    <div class="pt-2 border-t dark:border-slate-700">
      <label class="block text-sm font-medium mb-1">Mute all email notifications for</label>
      <select name="mute_days" class="border rounded px-2 py-1.5 text-sm">
        <option value="0" <?= empty($prefs['muted_until']) ? 'selected' : '' ?>>Not muted</option>
        <option value="1">1 day</option>
        <option value="7">1 week</option>
        <option value="30">1 month</option>
      </select>
      <?php if (!empty($prefs['muted_until']) && strtotime($prefs['muted_until']) > time()): ?>
        <p class="text-xs text-slate-500 mt-1">Currently muted until <?= e(date('M j, Y', strtotime($prefs['muted_until']))) ?>.</p>
      <?php endif; ?>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save preferences</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
