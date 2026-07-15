<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($_POST['action'] === 'send_message') {
        $recipientId = (int) $_POST['recipient_id'];
        $body = trim($_POST['body'] ?? '');
        if ($recipientId === $user['id']) {
            flash_set('error', "You can't message yourself.");
        } elseif ($body === '' || mb_strlen($body) > 2000) {
            flash_set('error', 'Message must be 1-2000 characters.');
        } elseif (!rate_limit('send_message', 30, 3600)) {
            flash_set('error', 'You are sending messages too quickly.');
        } else {
            $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)')
                ->execute([$user['id'], $recipientId, $body]);
            $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "new_message", JSON_OBJECT("from", ?))')
                ->execute([$recipientId, $user['username']]);
        }
        redirect('/inbox.php?with=' . $recipientId);
    } elseif ($_POST['action'] === 'mark_read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
        redirect('/inbox.php');
    }
}

$with = isset($_GET['with']) ? (int) $_GET['with'] : 0;

$threads = $pdo->prepare(
    "SELECT other.id, other.username, MAX(m.created_at) AS last_at,
            SUM(CASE WHEN m.recipient_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread
     FROM messages m
     JOIN users other ON other.id = IF(m.sender_id = ?, m.recipient_id, m.sender_id)
     WHERE m.sender_id = ? OR m.recipient_id = ?
     GROUP BY other.id, other.username
     ORDER BY last_at DESC"
);
$threads->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$threads = $threads->fetchAll();

$conversation = [];
$otherUser = null;
if ($with) {
    $ou = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $ou->execute([$with]);
    $otherUser = $ou->fetch();

    if ($otherUser) {
        $pdo->prepare('UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND sender_id = ?')
            ->execute([$user['id'], $with]);

        $conv = $pdo->prepare(
            'SELECT * FROM messages WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?) ORDER BY created_at ASC LIMIT 200'
        );
        $conv->execute([$user['id'], $with, $with, $user['id']]);
        $conversation = $conv->fetchAll();
    }
}

$notifications = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$notifications->execute([$user['id']]);
$notifications = $notifications->fetchAll();

$pageTitle = 'Inbox — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <div class="md:col-span-1 space-y-4">
    <div class="bg-white border rounded-lg p-3">
      <h2 class="font-semibold text-sm mb-2">Conversations</h2>
      <?php if (!$threads): ?><p class="text-sm text-slate-500">No conversations yet. Visit a profile to message someone.</p><?php endif; ?>
      <div class="space-y-1">
        <?php foreach ($threads as $t): ?>
          <a href="/inbox.php?with=<?= $t['id'] ?>" class="flex justify-between items-center px-2 py-1.5 rounded hover:bg-slate-100 <?= $with === (int) $t['id'] ? 'bg-slate-100' : '' ?>">
            <span class="text-sm"><?= e($t['username']) ?></span>
            <?php if ($t['unread'] > 0): ?><span class="text-xs bg-indigo-600 text-white rounded-full px-1.5"><?= (int) $t['unread'] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="bg-white border rounded-lg p-3">
      <div class="flex items-center justify-between mb-2">
        <h2 class="font-semibold text-sm">Notifications</h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_read"><button class="text-xs text-indigo-600 hover:underline">Mark all read</button></form>
      </div>
      <?php if (!$notifications): ?><p class="text-sm text-slate-500">No notifications.</p><?php endif; ?>
      <div class="space-y-1">
        <?php foreach ($notifications as $n): $data = json_decode($n['data'] ?? '{}', true); ?>
          <div class="text-sm <?= $n['is_read'] ? 'text-slate-500' : 'text-slate-900 font-medium' ?>">
            <?php if ($n['type'] === 'new_answer'): ?>
              New answer on <a href="/question.php?slug=<?= e($data['question_slug'] ?? '') ?>" class="text-indigo-600 hover:underline"><?= e($data['question_title'] ?? 'your question') ?></a>
            <?php elseif ($n['type'] === 'new_message'): ?>
              New message from <?= e($data['from'] ?? 'someone') ?>
            <?php else: ?>
              <?= e($n['type']) ?>
            <?php endif; ?>
            <div class="text-xs text-slate-400"><?= time_ago($n['created_at']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="md:col-span-2">
    <?php if ($otherUser): ?>
      <div class="bg-white border rounded-lg p-4 flex flex-col h-[500px]">
        <h2 class="font-semibold mb-3">Conversation with <?= e($otherUser['username']) ?></h2>
        <div class="flex-1 overflow-y-auto space-y-2 mb-3">
          <?php foreach ($conversation as $m): ?>
            <div class="text-sm <?= (int) $m['sender_id'] === $user['id'] ? 'text-right' : '' ?>">
              <span class="inline-block px-3 py-1.5 rounded-lg <?= (int) $m['sender_id'] === $user['id'] ? 'bg-indigo-600 text-white' : 'bg-slate-100' ?>">
                <?= e($m['body']) ?>
              </span>
              <div class="text-xs text-slate-400"><?= time_ago($m['created_at']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="send_message">
          <input type="hidden" name="recipient_id" value="<?= $otherUser['id'] ?>">
          <input type="text" name="body" required maxlength="2000" placeholder="Type a message..." class="flex-1 border rounded px-3 py-2 text-sm">
          <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Send</button>
        </form>
      </div>
    <?php else: ?>
      <div class="bg-white border rounded-lg p-8 text-center text-slate-500">Select a conversation.</div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
