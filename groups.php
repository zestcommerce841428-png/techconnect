<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/groups.php';

$user = current_user();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$user) redirect('/login');

    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        if (!rate_limit('create_group', 3, 86400)) {
            $errors[] = 'You have reached the daily group-creation limit.';
        }
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPrivate = isset($_POST['is_private']) ? 1 : 0;
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $errors[] = 'Group name must be 3-120 characters.';
        }
        if (!$errors) {
            $slug = unique_slug('groups_tbl', $name);
            $pdo->prepare('INSERT INTO groups_tbl (name, slug, description, is_private, created_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $slug, $description ?: null, $isPrivate, $user['id']]);
            $groupId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, "owner")')
                ->execute([$groupId, $user['id']]);
            flash_set('success', 'Group created.');
            redirect('/g/' . $slug);
        }
    } elseif ($action === 'join' || $action === 'leave') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM groups_tbl WHERE id = ?');
        $stmt->execute([$groupId]);
        $g = $stmt->fetch();
        if ($g) {
            if ($action === 'join' && !$g['is_private']) {
                $pdo->prepare('INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)')
                    ->execute([$groupId, $user['id']]);
                flash_set('success', 'Joined ' . $g['name'] . '.');
            } elseif ($action === 'leave' && group_role($groupId, $user['id']) !== 'owner') {
                $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')
                    ->execute([$groupId, $user['id']]);
                flash_set('success', 'Left ' . $g['name'] . '.');
            }
        }
        redirect('/groups');
    }
}

$groups = $pdo->query(
    "SELECT g.*, u.username AS owner_name,
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS member_count,
            (SELECT COUNT(*) FROM questions q WHERE q.group_id = g.id) AS question_count
     FROM groups_tbl g JOIN users u ON u.id = g.created_by
     ORDER BY member_count DESC, g.created_at DESC LIMIT 100"
)->fetchAll();

$myGroupIds = [];
if ($user) {
    $stmt = $pdo->prepare('SELECT group_id FROM group_members WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $myGroupIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$pageTitle = 'Groups — ' . SITE_NAME;
$pageDescription = 'Join topic and location groups on ' . SITE_NAME . ' to ask and answer within a focused community.';
require __DIR__ . '/includes/header.php';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold">Groups</h1>
</div>

<?php foreach ($errors as $err): ?>
  <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <div class="md:col-span-2 space-y-3">
    <?php if (!$groups): ?>
      <p class="text-sm text-slate-500">No groups yet — create the first one!</p>
    <?php endif; ?>
    <?php foreach ($groups as $g): $joined = in_array((int) $g['id'], $myGroupIds, true); ?>
      <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-4 flex items-start justify-between gap-4">
        <div>
          <a href="/g/<?= e($g['slug']) ?>" class="font-semibold text-indigo-700 dark:text-indigo-400 hover:underline"><?= e($g['name']) ?></a>
          <?php if ($g['is_private']): ?><span class="text-xs bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded ml-1">🔒 Private</span><?php endif; ?>
          <?php if ($g['description']): ?><p class="text-sm text-slate-600 dark:text-slate-400 mt-1"><?= e(mb_strimwidth($g['description'], 0, 140, '…')) ?></p><?php endif; ?>
          <p class="text-xs text-slate-500 mt-1"><?= (int) $g['member_count'] ?> member<?= $g['member_count'] == 1 ? '' : 's' ?> · <?= (int) $g['question_count'] ?> question<?= $g['question_count'] == 1 ? '' : 's' ?> · by <?= e($g['owner_name']) ?></p>
        </div>
        <?php if ($user): ?>
          <form method="post" class="shrink-0">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>">
            <?php if ($joined): ?>
              <button type="submit" name="action" value="leave" class="text-sm bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded">Leave</button>
            <?php elseif (!$g['is_private']): ?>
              <button type="submit" name="action" value="join" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Join</button>
            <?php else: ?>
              <span class="text-xs text-slate-400">Invite only</span>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div>
    <?php if ($user): ?>
      <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-4">
        <h2 class="font-semibold text-sm mb-3">Create a group</h2>
        <form method="post" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">
          <input type="text" name="name" required maxlength="120" placeholder="Group name" class="w-full border rounded px-3 py-2 text-sm">
          <textarea name="description" rows="3" placeholder="What is this group about?" class="w-full border rounded px-3 py-2 text-sm"></textarea>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_private">
            Private (members added by owner only)
          </label>
          <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2 rounded text-sm">Create group</button>
        </form>
      </div>
    <?php else: ?>
      <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-4 text-sm text-slate-600 dark:text-slate-400">
        <a href="/login" class="text-indigo-600 hover:underline">Log in</a> to create or join groups.
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
