<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$slug = $_GET['slug'] ?? '';

$stmt = $pdo->prepare(
    'SELECT q.*, u.username, u.id AS author_id, u.avatar
     FROM questions q JOIN users u ON u.id = q.user_id
     WHERE q.slug = ?'
);
$stmt->execute([$slug]);
$question = $stmt->fetch();

if (!$question) {
    http_response_code(404);
    $pageTitle = 'Question not found — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-12"><p class="text-slate-600">That question doesn\'t exist or was removed.</p><a href="/questions.php" class="text-indigo-600 hover:underline">Browse questions</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pdo->prepare('UPDATE questions SET view_count = view_count + 1 WHERE id = ?')->execute([$question['id']]);

$tagStmt = $pdo->prepare(
    'SELECT t.name, t.slug FROM tags t JOIN question_tags qt ON qt.tag_id = t.id WHERE qt.question_id = ?'
);
$tagStmt->execute([$question['id']]);
$tags = $tagStmt->fetchAll();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user = require_login();
    verify_csrf();

    if ($_POST['action'] === 'answer') {
        $body = trim($_POST['body'] ?? '');
        if (mb_strlen($body) < 10) {
            $errors[] = 'Answer must be at least 10 characters.';
        } elseif (!rate_limit('post_answer', 20, 3600)) {
            $errors[] = 'You are posting too quickly. Please slow down.';
        } else {
            $pdo->prepare('INSERT INTO answers (question_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$question['id'], $user['id'], $body]);
            $pdo->prepare('UPDATE questions SET answer_count = answer_count + 1, status = "answered" WHERE id = ?')
                ->execute([$question['id']]);
            if ($user['id'] !== (int) $question['author_id']) {
                $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "new_answer", JSON_OBJECT("question_slug", ?, "question_title", ?))')
                    ->execute([$question['author_id'], $question['slug'], $question['title']]);
            }
            redirect('/question.php?slug=' . $slug . '#answers');
        }
    } elseif ($_POST['action'] === 'comment') {
        $parentType = $_POST['parent_type'] === 'answer' ? 'answer' : 'question';
        $parentId = (int) $_POST['parent_id'];
        $body = trim($_POST['body'] ?? '');
        if ($body === '' || mb_strlen($body) > 600) {
            $errors[] = 'Comment must be 1-600 characters.';
        } else {
            $pdo->prepare('INSERT INTO comments (parent_type, parent_id, user_id, body) VALUES (?, ?, ?, ?)')
                ->execute([$parentType, $parentId, $user['id'], $body]);
            redirect('/question.php?slug=' . $slug . '#answers');
        }
    } elseif ($_POST['action'] === 'accept') {
        $answerId = (int) $_POST['answer_id'];
        if ($user['id'] === (int) $question['author_id']) {
            $pdo->prepare('UPDATE answers SET is_accepted = 0 WHERE question_id = ?')->execute([$question['id']]);
            $pdo->prepare('UPDATE answers SET is_accepted = 1 WHERE id = ? AND question_id = ?')
                ->execute([$answerId, $question['id']]);
            $author = $pdo->prepare('SELECT user_id FROM answers WHERE id = ?');
            $author->execute([$answerId]);
            $answerAuthorId = $author->fetchColumn();
            if ($answerAuthorId) {
                $pdo->prepare('UPDATE users SET reputation = reputation + 15 WHERE id = ?')->execute([$answerAuthorId]);
            }
        }
        redirect('/question.php?slug=' . $slug . '#answers');
    }
}

$answers = $pdo->prepare(
    'SELECT a.*, u.username, u.id AS author_id, u.avatar
     FROM answers a JOIN users u ON u.id = a.user_id
     WHERE a.question_id = ?
     ORDER BY a.is_accepted DESC, a.vote_score DESC, a.created_at ASC'
);
$answers->execute([$question['id']]);
$answers = $answers->fetchAll();

$commentStmt = $pdo->prepare(
    "SELECT c.*, u.username FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.parent_type = ? AND c.parent_id = ? ORDER BY c.created_at ASC"
);

function render_comments(PDO $pdo, PDOStatement $stmt, string $type, int $id, ?array $user): void
{
    $stmt->execute([$type, $id]);
    $comments = $stmt->fetchAll();
    echo '<div class="mt-2 space-y-1 pl-2 border-l-2 border-slate-100">';
    foreach ($comments as $c) {
        echo '<p class="text-sm text-slate-600"><span class="font-medium text-slate-800">' . e($c['username']) . '</span> ' . e($c['body']) . '</p>';
    }
    if ($user) {
        echo '<form method="post" class="flex gap-2 mt-1">
                <input type="hidden" name="action" value="comment">
                <input type="hidden" name="parent_type" value="' . e($type) . '">
                <input type="hidden" name="parent_id" value="' . (int) $id . '">
                <input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">
                <input type="text" name="body" maxlength="600" placeholder="Add a comment" class="flex-1 text-sm border rounded px-2 py-1">
                <button type="submit" class="text-sm text-indigo-600 hover:underline">Post</button>
              </form>';
    }
    echo '</div>';
}

$pageTitle = $question['title'] . ' — ' . SITE_NAME;
$pageDescription = mb_substr(strip_tags($question['body']), 0, 160);
require __DIR__ . '/includes/header.php';
?>
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>

<div class="bg-white border rounded-lg p-6">
  <div class="flex gap-4">
    <div class="flex flex-col items-center gap-1 text-sm shrink-0">
      <button data-vote="1" data-type="question" data-id="<?= $question['id'] ?>" class="p-1.5 rounded hover:bg-slate-100" title="Upvote">▲</button>
      <span data-score="question-<?= $question['id'] ?>" class="font-semibold"><?= (int) $question['vote_score'] ?></span>
      <button data-vote="-1" data-type="question" data-id="<?= $question['id'] ?>" class="p-1.5 rounded hover:bg-slate-100" title="Downvote">▼</button>
    </div>
    <div class="flex-1">
      <h1 class="text-2xl font-bold"><?= e($question['title']) ?></h1>
      <div class="mt-1 text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-1">
        <span>asked by <a href="/profile.php?u=<?= (int) $question['author_id'] ?>" class="text-indigo-600 hover:underline"><?= e($question['username']) ?></a></span>
        <span><?= time_ago($question['created_at']) ?></span>
        <span><?= (int) $question['view_count'] ?> views</span>
        <?php if ($question['location_city'] || $question['location_country']): ?>
          <span>📍 <?= e(trim(($question['location_city'] ?? '') . ', ' . ($question['location_country'] ?? ''), ', ')) ?></span>
        <?php endif; ?>
      </div>
      <div class="mt-4 prose prose-slate max-w-none whitespace-pre-wrap"><?= e($question['body']) ?></div>
      <?php if ($tags): ?>
        <div class="mt-4 flex flex-wrap gap-1">
          <?php foreach ($tags as $t): ?>
            <a href="/questions.php?tag=<?= e($t['slug']) ?>" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-0.5 rounded"><?= e($t['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php render_comments($pdo, $commentStmt, 'question', $question['id'], $user); ?>
    </div>
  </div>
</div>

<div id="answers" class="mt-8">
  <h2 class="text-lg font-semibold mb-3"><?= count($answers) ?> Answer<?= count($answers) === 1 ? '' : 's' ?></h2>

  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="space-y-4">
    <?php foreach ($answers as $a): ?>
      <div class="bg-white border rounded-lg p-6 <?= $a['is_accepted'] ? 'border-green-400' : '' ?>">
        <div class="flex gap-4">
          <div class="flex flex-col items-center gap-1 text-sm shrink-0">
            <button data-vote="1" data-type="answer" data-id="<?= $a['id'] ?>" class="p-1.5 rounded hover:bg-slate-100">▲</button>
            <span data-score="answer-<?= $a['id'] ?>" class="font-semibold"><?= (int) $a['vote_score'] ?></span>
            <button data-vote="-1" data-type="answer" data-id="<?= $a['id'] ?>" class="p-1.5 rounded hover:bg-slate-100">▼</button>
            <?php if ($a['is_accepted']): ?>
              <span class="text-green-600 text-lg" title="Accepted answer">✓</span>
            <?php elseif ($user && $user['id'] === (int) $question['author_id']): ?>
              <form method="post">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="answer_id" value="<?= $a['id'] ?>">
                <?= csrf_field() ?>
                <button type="submit" class="text-xs text-slate-400 hover:text-green-600" title="Accept this answer">Accept</button>
              </form>
            <?php endif; ?>
          </div>
          <div class="flex-1">
            <div class="prose prose-slate max-w-none whitespace-pre-wrap"><?= e($a['body']) ?></div>
            <div class="mt-3 text-xs text-slate-500">
              answered by <a href="/profile.php?u=<?= (int) $a['author_id'] ?>" class="text-indigo-600 hover:underline"><?= e($a['username']) ?></a>
              &middot; <?= time_ago($a['created_at']) ?>
            </div>
            <?php render_comments($pdo, $commentStmt, 'answer', $a['id'], $user); ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-6 bg-white border rounded-lg p-6">
    <h3 class="font-semibold mb-3">Your answer</h3>
    <?php if ($user): ?>
      <form method="post" class="space-y-3">
        <input type="hidden" name="action" value="answer">
        <?= csrf_field() ?>
        <textarea name="body" rows="6" required class="w-full border rounded px-3 py-2" placeholder="Share your solution..."></textarea>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Post answer</button>
      </form>
    <?php else: ?>
      <p class="text-sm text-slate-600"><a href="/login.php" class="text-indigo-600 hover:underline">Log in</a> to post an answer.</p>
    <?php endif; ?>
  </div>
</div>

<script src="/assets/js/vote.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
