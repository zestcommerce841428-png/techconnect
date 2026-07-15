<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/badges.php';
require_once __DIR__ . '/includes/spam_detection.php';
require_once __DIR__ . '/includes/groups.php';

$user = require_login();
$errors = [];

// Optional group context: ?group=<id> from a group page. Only members can post into a group.
$groupId = (int) ($_GET['group'] ?? $_POST['group_id'] ?? 0) ?: null;
$group = null;
if ($groupId) {
    $stmt = db()->prepare('SELECT id, name, slug, is_private FROM groups_tbl WHERE id = ?');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch() ?: null;
    if (!$group || group_role($groupId, (int) $user['id']) === null) {
        flash_set('error', 'You must be a member of that group to post in it.');
        redirect('/groups');
    }
}

// Continuing an existing draft: ?draft_id=<id>, must belong to this user.
$draftId = (int) ($_GET['draft_id'] ?? 0) ?: null;
$draft = null;
if ($draftId) {
    $stmt = db()->prepare("SELECT * FROM questions WHERE id = ? AND user_id = ? AND status = 'draft'");
    $stmt->execute([$draftId, $user['id']]);
    $draft = $stmt->fetch() ?: null;
    if (!$draft) redirect('/drafts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $isDraftSave = ($_POST['submit_action'] ?? '') === 'draft';

    if (!$isDraftSave && !rate_limit('ask_question', 10, 3600)) {
        $errors[] = 'You are posting too quickly. Please slow down.';
    }
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $city = trim($_POST['location_city'] ?? '');
    $country = trim($_POST['location_country'] ?? '');
    $tagsInput = trim($_POST['tags'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
    $postedDraftId = (int) ($_POST['draft_id'] ?? 0) ?: null;

    if ($isDraftSave) {
        if (mb_strlen($title) < 3) {
            $errors[] = 'Please add at least a short title before saving.';
        }
    } else {
        if (mb_strlen($title) < 10 || mb_strlen($title) > 200) {
            $errors[] = 'Title must be between 10 and 200 characters.';
        }
        if (!$categoryId) {
            $errors[] = 'Please choose a category.';
        }
        if (mb_strlen($body) < 20) {
            $errors[] = 'Please describe your problem in at least 20 characters.';
        }
    }
    $tagNames = array_slice(array_filter(array_unique(array_map(
        fn($t) => strtolower(trim($t)),
        explode(',', $tagsInput)
    ))), 0, 5);
    if (!$isDraftSave && !$tagNames) {
        $errors[] = 'Add at least one tag (e.g. php, networking, laptop-repair).';
    }

    if (!$errors && $isDraftSave) {
        $pdo = db();
        if ($postedDraftId) {
            $pdo->prepare("UPDATE questions SET category_id = ?, title = ?, body = ?, location_city = ?, location_country = ? WHERE id = ? AND user_id = ? AND status = 'draft'")
                ->execute([$categoryId, $title, $body, $city ?: null, $country ?: null, $postedDraftId, $user['id']]);
            flash_set('success', 'Draft updated.');
        } else {
            $slug = unique_slug('questions', $title ?: 'draft-' . bin2hex(random_bytes(4)));
            $pdo->prepare("INSERT INTO questions (user_id, category_id, group_id, title, slug, body, location_city, location_country, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')")
                ->execute([$user['id'], $categoryId, $group['id'] ?? null, $title, $slug, $body, $city ?: null, $country ?: null]);
            flash_set('success', 'Draft saved.');
        }
        redirect('/drafts');
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($postedDraftId) {
                $slug = unique_slug('questions', $title);
                $pdo->prepare("UPDATE questions SET category_id = ?, title = ?, slug = ?, body = ?, location_city = ?, location_country = ?, status = 'open' WHERE id = ? AND user_id = ?")
                    ->execute([$categoryId, $title, $slug, $body, $city ?: null, $country ?: null, $postedDraftId, $user['id']]);
                $questionId = $postedDraftId;
            } else {
                $slug = unique_slug('questions', $title);
                $stmt = $pdo->prepare(
                    'INSERT INTO questions (user_id, category_id, group_id, title, slug, body, location_city, location_country) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$user['id'], $categoryId, $group['id'] ?? null, $title, $slug, $body, $city ?: null, $country ?: null]);
                $questionId = (int) $pdo->lastInsertId();
            }

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

            // Optional poll: up to 5 non-empty options attached to the question.
            $pollOptions = array_slice(array_values(array_filter(array_map('trim', $_POST['poll_options'] ?? []))), 0, 5);
            if (count($pollOptions) >= 2) {
                $insOpt = $pdo->prepare('INSERT INTO poll_options (question_id, label, sort_order) VALUES (?, ?, ?)');
                foreach ($pollOptions as $i => $label) {
                    $insOpt->execute([$questionId, mb_substr($label, 0, 150), $i]);
                }
            }

            $pdo->commit();
            check_badges_for_user($user['id']);
            flag_if_spammy('question', $questionId, $title, $body);
            require_once __DIR__ . '/includes/telegram_notify.php';
            telegram_notify("🆕 New question by <b>" . htmlspecialchars($user['username']) . "</b>: " . htmlspecialchars($title) . "\n" . SITE_URL . '/q/' . $slug);
            require_once __DIR__ . '/includes/webhooks.php';
            fire_webhook('question.created', ['id' => $questionId, 'title' => $title, 'url' => SITE_URL . '/q/' . $slug, 'author' => $user['username']]);
            redirect('/question.php?slug=' . $slug);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong posting your question. Please try again.';
        }
    }
}

$categories = db()->query('SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$pageTitle = 'Ask a question — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Ask a question</h1>
  <?php if ($group): ?>
    <div class="mb-3 rounded border border-indigo-200 bg-indigo-50 text-indigo-800 px-3 py-2 text-sm">
      Posting in group: <strong><?= e($group['name']) ?></strong><?= $group['is_private'] ? ' — only group members will see this question.' : '' ?>
    </div>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>
  <?php if ($draft): ?>
    <div class="mb-3 rounded border border-amber-200 bg-amber-50 text-amber-800 px-3 py-2 text-sm">Continuing a saved draft. <a href="/drafts" class="underline">Back to drafts</a></div>
  <?php endif; ?>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <?php if ($group): ?><input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>"><?php endif; ?>
    <?php if ($draft): ?><input type="hidden" name="draft_id" value="<?= (int) $draft['id'] ?>"><?php endif; ?>
    <div>
      <label for="ask-category" class="block text-sm font-medium mb-1">Category</label>
      <select name="category_id" id="ask-category" class="w-full border rounded px-3 py-2">
        <option value="">Select a category&hellip;</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (int) ($_POST['category_id'] ?? $draft['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['icon'] ? $c['icon'] . ' ' : '') . e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="ask-title" class="block text-sm font-medium mb-1">Title</label>
      <input type="text" name="title" id="ask-title" maxlength="200" placeholder="Be specific — e.g. 'PHP PDO throws timeout on Hostinger shared hosting'"
             class="w-full border rounded px-3 py-2" value="<?= e($_POST['title'] ?? $draft['title'] ?? $_GET['title'] ?? '') ?>" autocomplete="off">
      <div id="related-questions" class="mt-2 hidden">
        <p class="text-xs text-slate-500 mb-1">Did you mean one of these?</p>
        <div id="related-list" class="space-y-1"></div>
      </div>
    </div>
    <div data-markdown-editor>
      <label for="ask-body" class="block text-sm font-medium mb-1">Details</label>
      <?php $textareaId = 'ask-body'; require __DIR__ . '/includes/editor_toolbar.php'; ?>
      <textarea name="body" id="ask-body" rows="8" class="w-full border border-t-0 rounded-b px-3 py-2"
                placeholder="What have you tried? Include error messages, code, environment details. Markdown supported: **bold**, `code`, ```code blocks```, [links](url)."><?= e($_POST['body'] ?? $draft['body'] ?? '') ?></textarea>
      <div class="mt-2">
        <p class="text-xs text-slate-500 mb-1">Preview</p>
        <div data-md-preview class="prose prose-slate max-w-none border rounded p-3 bg-slate-50 min-h-[3rem] text-sm"></div>
      </div>
    </div>
    <div>
      <label for="ask-tags" class="block text-sm font-medium mb-1">Tags (comma separated, up to 5)</label>
      <input type="text" name="tags" id="ask-tags" placeholder="php, mysql, hosting"
             class="w-full border rounded px-3 py-2" value="<?= e($_POST['tags'] ?? '') ?>">
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label for="ask-city" class="block text-sm font-medium mb-1">City <span class="text-slate-400">(optional, for "near me" matching)</span></label>
        <input type="text" name="location_city" id="ask-city" class="w-full border rounded px-3 py-2" value="<?= e($_POST['location_city'] ?? $draft['location_city'] ?? '') ?>">
      </div>
      <div>
        <label for="ask-country" class="block text-sm font-medium mb-1">Country <span class="text-slate-400">(optional)</span></label>
        <input type="text" name="location_country" id="ask-country" class="w-full border rounded px-3 py-2" value="<?= e($_POST['location_country'] ?? $draft['location_country'] ?? '') ?>">
      </div>
    </div>
    <details>
      <summary class="text-sm text-indigo-600 hover:underline cursor-pointer">📊 Attach a poll (optional)</summary>
      <div class="mt-2 space-y-2">
        <p class="text-xs text-slate-500">Add 2-5 options for the community to vote on.</p>
        <?php for ($i = 0; $i < 5; $i++): ?>
          <input type="text" name="poll_options[]" maxlength="150" placeholder="Option <?= $i + 1 ?><?= $i > 1 ? ' (optional)' : '' ?>" class="w-full border rounded px-3 py-2 text-sm">
        <?php endfor; ?>
      </div>
    </details>
    <div class="flex gap-2">
      <button type="submit" name="submit_action" value="publish" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Post question</button>
      <button type="submit" name="submit_action" value="draft" formnovalidate class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded text-sm">Save draft</button>
    </div>
  </form>
</div>
<script src="/assets/dist/js/markdown.js"></script>
<script src="/assets/dist/js/related_questions.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
