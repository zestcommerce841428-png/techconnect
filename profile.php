<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$viewer = current_user();
$targetId = isset($_GET['u']) ? (int) $_GET['u'] : ($viewer['id'] ?? 0);
$isOwn = $viewer && $viewer['id'] === $targetId;

if (!$targetId) {
    redirect('/login.php');
}

$errors = [];
if ($isOwn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bio = trim($_POST['bio'] ?? '');
    $city = trim($_POST['location_city'] ?? '');
    $country = trim($_POST['location_country'] ?? '');
    $hire = isset($_POST['available_for_hire']) ? 1 : 0;

    if (mb_strlen($bio) > 500) {
        $errors[] = 'Bio must be under 500 characters.';
    } else {
        $pdo->prepare('UPDATE users SET bio = ?, location_city = ?, location_country = ?, available_for_hire = ? WHERE id = ?')
            ->execute([$bio ?: null, $city ?: null, $country ?: null, $hire, $viewer['id']]);
        flash_set('success', 'Profile updated.');
        redirect('/profile.php');
    }
}

$stmt = $pdo->prepare('SELECT id, username, bio, location_city, location_country, reputation, avatar, available_for_hire, created_at FROM users WHERE id = ?');
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

$pageTitle = e($profile['username']) . ' — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <div class="md:col-span-1">
    <div class="bg-white border rounded-lg p-4 text-center">
      <div class="w-20 h-20 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-2xl font-bold mx-auto mb-3">
        <?= e(mb_strtoupper(mb_substr($profile['username'], 0, 1))) ?>
      </div>
      <h1 class="font-semibold text-lg"><?= e($profile['username']) ?></h1>
      <p class="text-sm text-slate-500 mt-1"><?= (int) $profile['reputation'] ?> reputation</p>
      <?php if ($profile['location_city'] || $profile['location_country']): ?>
        <p class="text-sm text-slate-500 mt-1">📍 <?= e(trim(($profile['location_city'] ?? '') . ', ' . ($profile['location_country'] ?? ''), ', ')) ?></p>
      <?php endif; ?>
      <?php if ($profile['available_for_hire']): ?>
        <span class="inline-block mt-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Available for hire</span>
      <?php endif; ?>
      <?php if (!$isOwn && $viewer): ?>
        <a href="/inbox.php?with=<?= $profile['id'] ?>" class="inline-block mt-3 text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Message</a>
      <?php endif; ?>
      <?php if ($profile['bio']): ?>
        <p class="text-sm text-slate-700 mt-3 text-left"><?= nl2br(e($profile['bio'])) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($isOwn): ?>
      <div class="bg-white border rounded-lg p-4 mt-4">
        <h2 class="font-semibold text-sm mb-2">Edit profile</h2>
        <?php foreach ($errors as $err): ?>
          <div class="mb-2 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="space-y-2">
          <?= csrf_field() ?>
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
          <a href="/question.php?slug=<?= e($q['slug']) ?>" class="block bg-white border rounded p-3 hover:border-indigo-400">
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
          <a href="/question.php?slug=<?= e($a['slug']) ?>#answers" class="block bg-white border rounded p-3 hover:border-indigo-400">
            <div class="text-sm font-medium"><?= e($a['title']) ?> <?= $a['is_accepted'] ? '<span class="text-green-600">✓</span>' : '' ?></div>
            <div class="text-xs text-slate-500"><?= (int) $a['vote_score'] ?> votes &middot; <?= time_ago($a['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
