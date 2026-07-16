<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Users — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();
$statusReady = user_status_ready();

// ---- Actions -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $returnQs = trim((string) ($_POST['return_qs'] ?? ''), '?&');
    $back = '/admin/users' . ($returnQs !== '' ? '?' . $returnQs : '');

    // An admin must never be able to lock themselves out or demote themselves
    // by accident — every destructive action refuses to target the actor.
    if ($userId === (int) $admin['id'] && in_array($action, ['set_role', 'set_status'], true)) {
        flash_set('error', 'You cannot change your own role or status.');
        redirect($back);
    }

    if ($action === 'set_role') {
        if ($admin['role'] !== 'admin') {
            flash_set('error', 'Only admins can change roles.');
            redirect($back);
        }
        $role = in_array($_POST['role'] ?? '', ['user', 'moderator', 'admin'], true) ? $_POST['role'] : 'user';
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);
        // Role changes must take effect immediately, not on next login.
        $pdo->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$userId]);
        audit_log($admin['id'], 'set_role', 'user', $userId, "role={$role}");
        flash_set('success', 'Role updated. The user will pick it up on their next request.');

    } elseif ($action === 'set_status' && $statusReady) {
        $status = in_array($_POST['status'] ?? '', ['active', 'suspended', 'banned'], true) ? $_POST['status'] : 'active';
        $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 255);
        $days = (int) ($_POST['days'] ?? 0);

        if ($status === 'active') {
            $pdo->prepare('UPDATE users SET status = ?, status_reason = NULL, status_until = NULL, status_by = ?, status_at = NOW() WHERE id = ?')
                ->execute(['active', $admin['id'], $userId]);
            audit_log($admin['id'], 'user_reinstated', 'user', $userId);
            flash_set('success', 'Account reinstated.');
        } else {
            $until = ($status === 'suspended' && $days > 0) ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
            $pdo->prepare('UPDATE users SET status = ?, status_reason = ?, status_until = ?, status_by = ?, status_at = NOW() WHERE id = ?')
                ->execute([$status, $reason ?: null, $until, $admin['id'], $userId]);
            // The status check alone stops future requests; these two lines end
            // the sessions and cookies that already exist.
            $pdo->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$userId]);
            try {
                $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM trusted_devices WHERE user_id = ?')->execute([$userId]);
            } catch (Throwable $e) {
                // tables may not exist on an older schema — status check still holds
            }
            audit_log($admin['id'], 'user_' . $status, 'user', $userId, $reason);
            flash_set('success', ucfirst($status) . ' applied. Existing sessions and remembered devices were revoked.');
        }

    } elseif ($action === 'add_note' && $userId) {
        $note = trim($_POST['note'] ?? '');
        if ($note !== '') {
            try {
                $pdo->prepare('INSERT INTO user_notes (user_id, author_id, note) VALUES (?, ?, ?)')
                    ->execute([$userId, $admin['id'], mb_substr($note, 0, 2000)]);
                audit_log($admin['id'], 'user_note_added', 'user', $userId);
                flash_set('success', 'Note added.');
            } catch (Throwable $e) {
                flash_set('error', 'Notes need migration 033_user_status.sql to be applied.');
            }
        }
    }
    redirect($back);
}

// ---- Filters -----------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$roleFilter = in_array($_GET['role'] ?? '', ['user', 'moderator', 'admin'], true) ? $_GET['role'] : '';
$statusFilter = ($statusReady && in_array($_GET['status'] ?? '', ['active', 'suspended', 'banned'], true)) ? $_GET['status'] : '';
$verifiedFilter = in_array($_GET['verified'] ?? '', ['yes', 'no'], true) ? $_GET['verified'] : '';
$sort = in_array($_GET['sort'] ?? '', ['newest', 'oldest', 'rep', 'name'], true) ? $_GET['sort'] : 'newest';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(username LIKE ? OR email LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($roleFilter !== '') { $where[] = 'role = ?'; $params[] = $roleFilter; }
if ($statusFilter !== '') { $where[] = 'status = ?'; $params[] = $statusFilter; }
if ($verifiedFilter === 'yes') { $where[] = 'email_verified_at IS NOT NULL'; }
if ($verifiedFilter === 'no') { $where[] = 'email_verified_at IS NULL'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderBy = match ($sort) {
    'oldest' => 'created_at ASC',
    'rep' => 'reputation DESC',
    'name' => 'username ASC',
    default => 'created_at DESC',
};

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = paginate_offset($page, $perPage);

$cols = 'id, username, email, role, reputation, created_at, email_verified_at'
    . ($statusReady ? ', status, status_reason, status_until' : '');
$stmt = $pdo->prepare("SELECT $cols FROM users $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

$qs = fn(array $over = []) => http_build_query(array_filter(array_merge([
    'q' => $search, 'role' => $roleFilter, 'status' => $statusFilter,
    'verified' => $verifiedFilter, 'sort' => $sort !== 'newest' ? $sort : '', 'page' => $page > 1 ? $page : '',
], $over), fn($v) => $v !== '' && $v !== null));

$statusStyles = [
    'active' => 'bg-green-100 text-green-800',
    'suspended' => 'bg-amber-100 text-amber-800',
    'banned' => 'bg-red-100 text-red-800',
];
?>
<div class="flex flex-wrap items-center justify-between gap-2 mb-1">
  <h1 class="text-2xl font-bold">Users <span class="text-base font-normal text-slate-500">(<?= number_format($total) ?>)</span></h1>
</div>

<?php if (!$statusReady): ?>
  <div class="mb-4 rounded border border-amber-300 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
    Suspension and ban controls need <code>migrations/033_user_status.sql</code> applied.
    Until then the old ban only blocks password sign-in — passkey, OTP, social login and existing sessions bypass it.
  </div>
<?php endif; ?>

<form method="get" class="bg-white border rounded-lg p-3 mb-4 flex flex-wrap items-end gap-2">
  <div class="flex-1 min-w-[180px]">
    <label for="uq" class="block text-xs font-medium mb-1">Search</label>
    <input id="uq" type="search" name="q" value="<?= e($search) ?>" placeholder="Username or email" class="w-full border rounded px-3 py-2 text-sm">
  </div>
  <div>
    <label for="urole" class="block text-xs font-medium mb-1">Role</label>
    <select id="urole" name="role" class="border rounded px-2 py-2 text-sm">
      <option value="">Any</option>
      <?php foreach (['user', 'moderator', 'admin'] as $r): ?>
        <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($statusReady): ?>
    <div>
      <label for="ustatus" class="block text-xs font-medium mb-1">Status</label>
      <select id="ustatus" name="status" class="border rounded px-2 py-2 text-sm">
        <option value="">Any</option>
        <?php foreach (['active', 'suspended', 'banned'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div>
    <label for="uver" class="block text-xs font-medium mb-1">Email</label>
    <select id="uver" name="verified" class="border rounded px-2 py-2 text-sm">
      <option value="">Any</option>
      <option value="yes" <?= $verifiedFilter === 'yes' ? 'selected' : '' ?>>Verified</option>
      <option value="no" <?= $verifiedFilter === 'no' ? 'selected' : '' ?>>Unverified</option>
    </select>
  </div>
  <div>
    <label for="usort" class="block text-xs font-medium mb-1">Sort</label>
    <select id="usort" name="sort" class="border rounded px-2 py-2 text-sm">
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
      <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
      <option value="rep" <?= $sort === 'rep' ? 'selected' : '' ?>>Reputation</option>
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
    </select>
  </div>
  <button class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Filter</button>
  <?php if ($search || $roleFilter || $statusFilter || $verifiedFilter || $sort !== 'newest'): ?>
    <a href="/admin/users" class="text-sm text-slate-500 px-2">Clear</a>
  <?php endif; ?>
</form>

<div class="space-y-2">
  <?php foreach ($users as $u): $st = $u['status'] ?? 'active'; ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <a href="/u/<?= e($u['username']) ?>" class="font-medium text-indigo-600"><?= e($u['username']) ?></a>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600"><?= e($u['role']) ?></span>
            <?php if ($statusReady): ?>
              <span class="text-[10px] px-1.5 py-0.5 rounded <?= $statusStyles[$st] ?? '' ?>"><?= e($st) ?></span>
            <?php endif; ?>
            <?php if (empty($u['email_verified_at'])): ?>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500" title="Email not verified">unverified</span>
            <?php endif; ?>
            <?php if ((int) $u['id'] === (int) $admin['id']): ?>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">you</span>
            <?php endif; ?>
          </div>
          <div class="text-xs text-slate-500 mt-0.5">
            <?= e($u['email']) ?> · <?= (int) $u['reputation'] ?> rep · joined <?= e(date('M j, Y', strtotime($u['created_at']))) ?>
          </div>
          <?php if ($statusReady && $st !== 'active' && !empty($u['status_reason'])): ?>
            <div class="text-xs text-amber-700 mt-1">
              <?= e($u['status_reason']) ?>
              <?php if (!empty($u['status_until'])): ?> · until <?= e(date('j M Y', strtotime($u['status_until']))) ?><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <?php if ($admin['role'] === 'admin'): ?>
              <form method="post" class="flex items-center gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_role">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="return_qs" value="<?= e($qs()) ?>">
                <select name="role" class="border rounded px-2 py-1 text-xs" onchange="this.form.submit()">
                  <?php foreach (['user', 'moderator', 'admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php endif; ?>

            <?php if ($statusReady): ?>
              <?php if ($st === 'active'): ?>
                <details class="relative">
                  <summary class="text-xs border border-amber-200 text-amber-700 hover:bg-amber-50 px-3 py-1.5 rounded cursor-pointer list-none">Suspend / Ban</summary>
                  <form method="post" class="absolute right-0 mt-1 z-20 w-64 bg-white border rounded-lg shadow-lg p-3 space-y-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="return_qs" value="<?= e($qs()) ?>">
                    <select name="status" class="w-full border rounded px-2 py-1.5 text-xs">
                      <option value="suspended">Suspend (temporary)</option>
                      <option value="banned">Ban (permanent)</option>
                    </select>
                    <input type="number" name="days" min="0" max="365" value="7" placeholder="Days (suspension)" class="w-full border rounded px-2 py-1.5 text-xs">
                    <input type="text" name="reason" maxlength="255" placeholder="Reason (shown to the user)" class="w-full border rounded px-2 py-1.5 text-xs">
                    <button class="w-full bg-red-600 hover:bg-red-500 text-white rounded px-2 py-1.5 text-xs">Apply</button>
                  </form>
                </details>
              <?php else: ?>
                <form method="post" onsubmit="return confirm('Reinstate this account?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="status" value="active">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="return_qs" value="<?= e($qs()) ?>">
                  <button class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded">Reinstate</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$users): ?>
    <div class="bg-white border rounded-lg p-8 text-center text-sm text-slate-500">No users match these filters.</div>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
  <div class="flex flex-wrap gap-1 mt-4 text-sm">
    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
      <a href="/admin/users?<?= e($qs(['page' => $p])) ?>"
         class="px-3 py-1.5 rounded border <?= $p === $page ? 'bg-slate-900 text-white' : 'bg-white hover:bg-slate-100' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <span class="px-3 py-1.5 text-slate-400">of <?= $totalPages ?></span>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
