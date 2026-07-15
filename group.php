<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/groups.php';

$user = current_user();
$pdo = db();

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare('SELECT g.*, u.username AS owner_name FROM groups_tbl g JOIN users u ON u.id = g.created_by WHERE g.slug = ?');
$stmt->execute([$slug]);
$group = $stmt->fetch();

if (!$group) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$myRole = $user ? group_role((int) $group['id'], (int) $user['id']) : null;
$canView = can_view_group($group, $user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$user) redirect('/login');
    $action = $_POST['action'] ?? '';

    if ($action === 'join' && !$group['is_private']) {
        $pdo->prepare('INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)')
            ->execute([$group['id'], $user['id']]);
        flash_set('success', 'Joined ' . $group['name'] . '.');
    } elseif ($action === 'leave' && $myRole && $myRole !== 'owner') {
        $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')
            ->execute([$group['id'], $user['id']]);
        flash_set('success', 'Left ' . $group['name'] . '.');
    } elseif ($action === 'add_member' && $myRole === 'owner') {
        $username = trim($_POST['username'] ?? '');
        $u = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $u->execute([$username]);
        $memberId = $u->fetchColumn();
        if ($memberId) {
            $pdo->prepare('INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)')
                ->execute([$group['id'], $memberId]);
            flash_set('success', 'Added ' . $username . ' to the group.');
        } else {
            flash_set('error', 'No user with that username.');
        }
    } elseif ($action === 'remove_member' && $myRole === 'owner') {
        $memberId = (int) ($_POST['user_id'] ?? 0);
        if ($memberId !== (int) $user['id']) {
            $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')
                ->execute([$group['id'], $memberId]);
            flash_set('success', 'Member removed.');
        }
    }
    redirect('/g/' . $group['slug']);
}

$pageTitle = e($group['name']) . ' — Groups — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';

if (!$canView): ?>
  <div class="max-w-md mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6 text-center">
    <h1 class="text-xl font-semibold mb-2">🔒 <?= e($group['name']) ?></h1>
    <p class="text-sm text-slate-600 dark:text-slate-400">This is a private group. Membership is by invitation from the group owner.</p>
  </div>
<?php else:
    $questions = $pdo->prepare(
        "SELECT q.id, q.title, q.slug, q.vote_score, q.answer_count, q.view_count, q.status, q.created_at,
                u.username, u.location_city, u.location_country,
                (SELECT GROUP_CONCAT(t.name) FROM question_tags qt JOIN tags t ON t.id = qt.tag_id WHERE qt.question_id = q.id) AS tags
         FROM questions q JOIN users u ON u.id = q.user_id
         WHERE q.group_id = ? ORDER BY q.created_at DESC LIMIT 50"
    );
    $questions->execute([$group['id']]);
    $questions = $questions->fetchAll();

    $members = $pdo->prepare(
        "SELECT u.id, u.username, gm.role FROM group_members gm JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = ? ORDER BY FIELD(gm.role,'owner','moderator','member'), u.username LIMIT 100"
    );
    $members->execute([$group['id']]);
    $members = $members->fetchAll();
?>
  <div class="flex items-start justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold"><?= e($group['name']) ?>
        <?php if ($group['is_private']): ?><span class="text-sm bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded align-middle">🔒 Private</span><?php endif; ?>
      </h1>
      <?php if ($group['description']): ?><p class="text-sm text-slate-600 dark:text-slate-400 mt-1"><?= e($group['description']) ?></p><?php endif; ?>
    </div>
    <div class="flex gap-2 shrink-0">
      <?php if ($myRole): ?>
        <a href="/ask?group=<?= (int) $group['id'] ?>" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Ask in group</a>
        <?php if ($myRole !== 'owner'): ?>
          <form method="post"><?= csrf_field() ?><button type="submit" name="action" value="leave" class="text-sm bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded">Leave</button></form>
        <?php endif; ?>
      <?php elseif ($user && !$group['is_private']): ?>
        <form method="post"><?= csrf_field() ?><button type="submit" name="action" value="join" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Join group</button></form>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-2 space-y-3">
      <?php if (!$questions): ?><p class="text-sm text-slate-500">No questions in this group yet.</p><?php endif; ?>
      <?php foreach ($questions as $q): ?>
        <?php require __DIR__ . '/includes/question_card.php'; ?>
      <?php endforeach; ?>
    </div>
    <div>
      <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-4">
        <h2 class="font-semibold text-sm mb-3">Members (<?= count($members) ?>)</h2>
        <ul class="space-y-1.5 text-sm">
          <?php foreach ($members as $m): ?>
            <li class="flex items-center justify-between gap-2">
              <span>
                <a href="/u/<?= e($m['username']) ?>" class="hover:underline"><?= e($m['username']) ?></a>
                <?php if ($m['role'] !== 'member'): ?><span class="text-xs text-slate-500">(<?= e($m['role']) ?>)</span><?php endif; ?>
              </span>
              <?php if ($myRole === 'owner' && $m['role'] !== 'owner'): ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                  <button type="submit" name="action" value="remove_member" class="text-xs text-red-600 hover:underline">Remove</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($myRole === 'owner'): ?>
          <form method="post" class="mt-3 flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_member">
            <input type="text" name="username" placeholder="Add by username" class="flex-1 border rounded px-2 py-1.5 text-sm">
            <button type="submit" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Add</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
