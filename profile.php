<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/follow.php';
require_once __DIR__ . '/includes/badges.php';

$pdo = db();
$viewer = current_user();

if (isset($_GET['username'])) {
    $lookup = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $lookup->execute([$_GET['username']]);
    $targetId = (int) $lookup->fetchColumn();
} else {
    $targetId = isset($_GET['u']) ? (int) $_GET['u'] : ($viewer['id'] ?? 0);
}
$isOwn = $viewer && $viewer['id'] === $targetId;

if (!$targetId) {
    redirect('/login');
}

$errors = [];
if (!$isOwn && $viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_block') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $stmt->execute([$viewer['id'], $targetId]);
    if ($stmt->fetchColumn()) {
        $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?')->execute([$viewer['id'], $targetId]);
        flash_set('success', 'User unblocked.');
    } else {
        $pdo->prepare('INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)')->execute([$viewer['id'], $targetId]);
        flash_set('success', 'User blocked.');
    }
    redirect('/profile?u=' . $targetId);
}
if ($isOwn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bio = trim($_POST['bio'] ?? '');
    $city = trim($_POST['location_city'] ?? '');
    $country = trim($_POST['location_country'] ?? '');
    $hire = isset($_POST['available_for_hire']) ? 1 : 0;

    if (mb_strlen($bio) > 500) {
        $errors[] = 'Bio must be under 500 characters.';
    } else {
        // Optional avatar upload: square-ish images, png/jpg/webp, 2MB max.
        $avatarPath = null;
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
            finfo_close($finfo);
            if (isset($allowed[$mime]) && $_FILES['avatar']['size'] <= 2 * 1024 * 1024) {
                $dir = __DIR__ . '/uploads/avatars';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $avatarPath = '/uploads/avatars/u' . $viewer['id'] . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . $avatarPath);
            } else {
                $errors[] = 'Avatar must be a PNG/JPG/WebP under 2MB.';
            }
        }
        if (!$errors) {
            if ($avatarPath) {
                $pdo->prepare('UPDATE users SET bio = ?, location_city = ?, location_country = ?, available_for_hire = ?, avatar = ? WHERE id = ?')
                    ->execute([$bio ?: null, $city ?: null, $country ?: null, $hire, $avatarPath, $viewer['id']]);
            } else {
                $pdo->prepare('UPDATE users SET bio = ?, location_city = ?, location_country = ?, available_for_hire = ? WHERE id = ?')
                    ->execute([$bio ?: null, $city ?: null, $country ?: null, $hire, $viewer['id']]);
            }
            flash_set('success', 'Profile updated.');
            redirect('/profile');
        }
    }
}

$stmt = $pdo->prepare('SELECT id, username, bio, location_city, location_country, reputation, avatar, available_for_hire, is_pro, created_at FROM users WHERE id = ?');
$stmt->execute([$targetId]);
$profile = $stmt->fetch();

if (!$profile) {
    http_response_code(404);
    exit('User not found.');
}

$questions = $pdo->prepare('SELECT id, title, slug, vote_score, answer_count, created_at FROM questions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$questions->execute([$targetId]);
$questions = $questions->fetchAll();

$answers = $pdo->prepare('SELECT a.id, a.body, a.vote_score, a.is_accepted, a.created_at, q.slug, q.title FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.user_id = ? ORDER BY a.created_at DESC LIMIT 10');
$answers->execute([$targetId]);
$answers = $answers->fetchAll();

$badges = $pdo->prepare('SELECT b.name, b.icon, b.description, b.tier FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = ? ORDER BY ub.awarded_at DESC');
$badges->execute([$targetId]);
$badges = $badges->fetchAll();

$followerCount = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followable_type = 'user' AND followable_id = ?");
$followerCount->execute([$targetId]);
$followerCount = (int) $followerCount->fetchColumn();
$isFollowing = $viewer ? is_following($viewer['id'], 'user', $targetId) : false;
$isBlocked = false;
if ($viewer && !$isOwn) {
    $blockStmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockStmt->execute([$viewer['id'], $targetId]);
    $isBlocked = (bool) $blockStmt->fetchColumn();
}

$pageTitle = e($profile['username']) . ' — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <div class="md:col-span-1">
    <div class="bg-white border rounded-lg p-4 text-center">
      <?php if (!empty($profile['avatar'])): ?>
        <img src="<?= e($profile['avatar']) ?>" alt="<?= e($profile['username']) ?>'s avatar" class="w-20 h-20 rounded-full object-cover mx-auto mb-3">
      <?php else: ?>
        <div class="w-20 h-20 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-2xl font-bold mx-auto mb-3">
          <?= e(mb_strtoupper(mb_substr($profile['username'], 0, 1))) ?>
        </div>
      <?php endif; ?>
      <h1 class="font-semibold text-lg"><?= e($profile['username']) ?></h1>
      <p class="text-sm text-slate-500 mt-1"><a href="/reputation?u=<?= (int) $profile['id'] ?>" class="hover:underline" title="View reputation history"><?= (int) $profile['reputation'] ?> reputation</a> &middot; <?= $followerCount ?> follower<?= $followerCount === 1 ? '' : 's' ?></p>
      <?php if ($profile['location_city'] || $profile['location_country']): ?>
        <p class="text-sm text-slate-500 mt-1">📍 <?= e(trim(($profile['location_city'] ?? '') . ', ' . ($profile['location_country'] ?? ''), ', ')) ?></p>
      <?php endif; ?>
      <?php if ($profile['available_for_hire']): ?>
        <span class="inline-block mt-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Available for hire</span>
      <?php endif; ?>
      <?php if (!empty($profile['is_pro'])): ?>
        <span class="inline-block mt-2 text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded">⭐ Pro member</span>
      <?php endif; ?>
      <?php if ($badges): ?>
        <div class="flex flex-wrap justify-center gap-1 mt-2">
          <?php foreach ($badges as $b): ?>
            <span class="text-xs bg-slate-100 px-2 py-0.5 rounded" title="<?= e($b['description']) ?>"><?= e($b['icon'] ?? '') ?> <?= e($b['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!$isOwn && $viewer): ?>
        <div class="flex gap-2 justify-center mt-3 flex-wrap">
          <a href="/inbox?with=<?= $profile['id'] ?>" class="inline-block text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Message</a>
          <button data-follow data-type="user" data-id="<?= $profile['id'] ?>" class="text-sm px-3 py-1.5 rounded <?= $isFollowing ? 'bg-indigo-600 text-white' : 'bg-slate-100' ?>"><?= $isFollowing ? 'Following' : 'Follow' ?></button>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_block">
            <button type="submit" class="text-sm px-3 py-1.5 rounded <?= $isBlocked ? 'bg-red-600 text-white' : 'bg-slate-100 text-red-600' ?>"><?= $isBlocked ? 'Unblock' : 'Block' ?></button>
          </form>
        </div>
        <div class="mt-1">
          <?php require_once __DIR__ . '/includes/report_widget.php'; render_report_form('user', (int) $profile['id'], $viewer); ?>
        </div>
      <?php endif; ?>
      <?php if ($profile['bio']): ?>
        <p class="text-sm text-slate-700 mt-3 text-left"><?= nl2br(e($profile['bio'])) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($isOwn): ?>
      <div class="bg-white border rounded-lg p-4 mt-4 flex flex-wrap gap-3 text-sm">
        <a href="/invoices" class="text-indigo-600 hover:underline">Invoices</a>
        <a href="/affiliate" class="text-indigo-600 hover:underline">Affiliate links</a>
        <a href="/expert_apply" class="text-indigo-600 hover:underline">Become an expert</a>
        <a href="/notification_settings" class="text-indigo-600 hover:underline">Notifications</a>
        <a href="/api_keys" class="text-indigo-600 hover:underline">API keys</a>
        <a href="/drafts" class="text-indigo-600 hover:underline">Drafts</a>
        <a href="/saved_searches" class="text-indigo-600 hover:underline">Saved searches</a>
        <a href="/activity" class="text-indigo-600 hover:underline">Activity</a>
        <a href="/account_data" class="text-indigo-600 hover:underline">Data & account</a>
        <?php if (empty($profile['is_pro'])): ?><a href="/pro" class="text-amber-600 hover:underline">Go Pro</a><?php endif; ?>
      </div>
      <div class="bg-white border rounded-lg p-4 mt-4">
        <h2 class="font-semibold text-sm mb-2">Edit profile</h2>
        <?php foreach ($errors as $err): ?>
          <div class="mb-2 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" class="space-y-2">
          <?= csrf_field() ?>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Avatar (PNG/JPG/WebP, max 2MB)</label>
            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="text-xs w-full">
          </div>
          <textarea name="bio" rows="3" maxlength="500" placeholder="Short bio" class="w-full border rounded px-2 py-1 text-sm"><?= e($profile['bio'] ?? '') ?></textarea>
          <input type="text" name="location_city" placeholder="City" value="<?= e($profile['location_city'] ?? '') ?>" class="w-full border rounded px-2 py-1 text-sm">
          <input type="text" name="location_country" placeholder="Country" value="<?= e($profile['location_country'] ?? '') ?>" class="w-full border rounded px-2 py-1 text-sm">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="available_for_hire" <?= $profile['available_for_hire'] ? 'checked' : '' ?>>
            Available for hire
          </label>
          <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded px-3 py-1.5 text-sm">Save</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="md:col-span-2 space-y-6">
    <div>
      <h2 class="font-semibold mb-2">Questions</h2>
      <?php if (!$questions): ?><p class="text-sm text-slate-500">No questions yet.</p><?php endif; ?>
      <div class="space-y-2">
        <?php foreach ($questions as $q): ?>
          <a href="/question?slug=<?= e($q['slug']) ?>" class="block bg-white border rounded p-3 hover:border-indigo-400">
            <div class="text-sm font-medium"><?= e($q['title']) ?></div>
            <div class="text-xs text-slate-500"><?= (int) $q['vote_score'] ?> votes &middot; <?= (int) $q['answer_count'] ?> answers &middot; <?= time_ago($q['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <h2 class="font-semibold mb-2">Answers</h2>
      <?php if (!$answers): ?><p class="text-sm text-slate-500">No answers yet.</p><?php endif; ?>
      <div class="space-y-2">
        <?php foreach ($answers as $a): ?>
          <a href="/question?slug=<?= e($a['slug']) ?>#answers" class="block bg-white border rounded p-3 hover:border-indigo-400">
            <div class="text-sm font-medium"><?= e($a['title']) ?> <?= $a['is_accepted'] ? '<span class="text-green-600">✓</span>' : '' ?></div>
            <div class="text-xs text-slate-500"><?= (int) $a['vote_score'] ?> votes &middot; <?= time_ago($a['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<script src="/assets/dist/js/follow.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
