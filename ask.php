<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('ask_question', 10, 3600)) {
        $errors[] = 'You are posting too quickly. Please slow down.';
    }
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $city = trim($_POST['location_city'] ?? '');
    $country = trim($_POST['location_country'] ?? '');
    $tagsInput = trim($_POST['tags'] ?? '');

    if (mb_strlen($title) < 10 || mb_strlen($title) > 200) {
        $errors[] = 'Title must be between 10 and 200 characters.';
    }
    if (mb_strlen($body) < 20) {
        $errors[] = 'Please describe your problem in at least 20 characters.';
    }
    $tagNames = array_slice(array_filter(array_unique(array_map(
        fn($t) => strtolower(trim($t)),
        explode(',', $tagsInput)
    ))), 0, 5);
    if (!$tagNames) {
        $errors[] = 'Add at least one tag (e.g. php, networking, laptop-repair).';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $slug = unique_slug('questions', $title);
            $stmt = $pdo->prepare(
                'INSERT INTO questions (user_id, title, slug, body, location_city, location_country) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'], $title, $slug, $body, $city ?: null, $country ?: null]);
            $questionId = (int) $pdo->lastInsertId();

            $findTag = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
            $insertTag = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
            $linkTag = $pdo->prepare('INSERT IGNORE INTO question_tags (question_id, tag_id) VALUES (?, ?)');
            $bumpTag = $pdo->prepare('UPDATE tags SET use_count = use_count + 1 WHERE id = ?');

            foreach ($tagNames as $name) {
                if ($name === '') continue;
                $findTag->execute([$name]);
                $tagId = $findTag->fetchColumn();
                if (!$tagId) {
                    $insertTag->execute([$name, slugify($name)]);
                    $tagId = (int) $pdo->lastInsertId();
                }
                $linkTag->execute([$questionId, $tagId]);
                $bumpTag->execute([$tagId]);
            }

            $pdo->commit();
            redirect('/question.php?slug=' . $slug);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong posting your question. Please try again.';
        }
    }
}

$pageTitle = 'Ask a question — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Ask a question</h1>
  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Title</label>
      <input type="text" name="title" required maxlength="200" placeholder="Be specific — e.g. 'PHP PDO throws timeout on Hostinger shared hosting'"
             class="w-full border rounded px-3 py-2" value="<?= e($_POST['title'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Details</label>
      <textarea name="body" required rows="8" class="w-full border rounded px-3 py-2"
                placeholder="What have you tried? Include error messages, code, environment details."><?= e($_POST['body'] ?? '') ?></textarea>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Tags (comma separated, up to 5)</label>
      <input type="text" name="tags" required placeholder="php, mysql, hosting"
             class="w-full border rounded px-3 py-2" value="<?= e($_POST['tags'] ?? '') ?>">
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1">City <span class="text-slate-400">(optional, for "near me" matching)</span></label>
        <input type="text" name="location_city" class="w-full border rounded px-3 py-2" value="<?= e($_POST['location_city'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Country <span class="text-slate-400">(optional)</span></label>
        <input type="text" name="location_country" class="w-full border rounded px-3 py-2" value="<?= e($_POST['location_country'] ?? '') ?>">
      </div>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Post question</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
