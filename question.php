<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';
require_once __DIR__ . '/includes/badges.php';
require_once __DIR__ . '/includes/mentions.php';
require_once __DIR__ . '/includes/follow.php';
require_once __DIR__ . '/includes/spam_detection.php';
require_once __DIR__ . '/includes/groups.php';
require_once __DIR__ . '/includes/reputation.php';
require_once __DIR__ . '/includes/report_widget.php';

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
    echo '<div class="text-center py-12"><p class="text-slate-600">That question doesn\'t exist or was removed.</p><a href="/questions" class="text-indigo-600 hover:underline">Browse questions</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($question['merged_into_id']) {
    $canon = $pdo->prepare('SELECT slug FROM questions WHERE id = ?');
    $canon->execute([$question['merged_into_id']]);
    $canonSlug = $canon->fetchColumn();
    if ($canonSlug) {
        header('Location: /q/' . $canonSlug, true, 301);
        exit;
    }
}

// Private-group questions are visible to group members (and staff) only.
if ($question['group_id']) {
    $gStmt = $pdo->prepare('SELECT id, name, slug, is_private FROM groups_tbl WHERE id = ?');
    $gStmt->execute([$question['group_id']]);
    $questionGroup = $gStmt->fetch();
    if ($questionGroup && !can_view_group($questionGroup, current_user())) {
        http_response_code(403);
        $pageTitle = 'Private question — ' . SITE_NAME;
        require __DIR__ . '/includes/header.php';
        echo '<div class="text-center py-12"><p class="text-slate-600">🔒 This question belongs to a private group. Only group members can view it.</p><a href="/groups" class="text-indigo-600 hover:underline">Browse groups</a></div>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }
} else {
    $questionGroup = null;
}

// Drafts are only visible to their author.
if ($question['status'] === 'draft' && (!current_user() || current_user()['id'] !== (int) $question['author_id'])) {
    http_response_code(404);
    $pageTitle = 'Question not found — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-12"><p class="text-slate-600">That question doesn\'t exist or was removed.</p><a href="/questions" class="text-indigo-600 hover:underline">Browse questions</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Dedupe view counting: skip the question's own author, and only count once
// per session per hour, so refreshing the page (or a bot loop) can't inflate
// a number that other features (search-suggestion ranking, trending) rely on.
$viewerId = current_user()['id'] ?? null;
if ($viewerId === null || (int) $viewerId !== (int) $question['author_id']) {
    $_SESSION['viewed_questions'] = $_SESSION['viewed_questions'] ?? [];
    $lastViewedAt = $_SESSION['viewed_questions'][$question['id']] ?? 0;
    if (time() - $lastViewedAt > 3600) {
        $pdo->prepare('UPDATE questions SET view_count = view_count + 1 WHERE id = ?')->execute([$question['id']]);
        $_SESSION['viewed_questions'][$question['id']] = time();
    }
}

$tagStmt = $pdo->prepare(
    'SELECT t.id, t.name, t.slug FROM tags t JOIN question_tags qt ON qt.tag_id = t.id WHERE qt.question_id = ?'
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
        if ($question['status'] === 'closed') {
            $errors[] = 'This question is closed and no longer accepts answers.';
        } elseif (mb_strlen($body) < 10) {
            $errors[] = 'Answer must be at least 10 characters.';
        } elseif (!rate_limit('post_answer', 20, 3600)) {
            $errors[] = 'You are posting too quickly. Please slow down.';
        } else {
            $pdo->prepare('INSERT INTO answers (question_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$question['id'], $user['id'], $body]);
            $newAnswerId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE questions SET answer_count = answer_count + 1, status = "answered" WHERE id = ?')
                ->execute([$question['id']]);
            flag_if_spammy('answer', $newAnswerId, '', $body);
            if ($user['id'] !== (int) $question['author_id']) {
                $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "new_answer", JSON_OBJECT("question_slug", ?, "question_title", ?))')
                    ->execute([$question['author_id'], $question['slug'], $question['title']]);
            }
            notify_mentions($body, $user['id'], $user['username'], $question['slug'], $question['title']);
            check_badges_for_user($user['id']);
            notify_followers_of_new_answer($question['id'], $question['title'], $question['slug'], $user['id']);
            require_once __DIR__ . '/includes/webhooks.php';
            fire_webhook('answer.created', ['id' => $newAnswerId, 'question_title' => $question['title'], 'url' => SITE_URL . '/q/' . $question['slug'], 'author' => $user['username']]);
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
            notify_mentions($body, $user['id'], $user['username'], $question['slug'], $question['title']);
            redirect('/question.php?slug=' . $slug . '#answers');
        }
    } elseif ($_POST['action'] === 'comment_edit') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $own = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
        $own->execute([$commentId]);
        $ownerId = $own->fetchColumn();
        if ($ownerId !== false && (int) $ownerId === (int) $user['id'] && $body !== '' && mb_strlen($body) <= 600) {
            $pdo->prepare('UPDATE comments SET body = ?, edited_at = NOW() WHERE id = ?')->execute([$body, $commentId]);
        }
        redirect('/question.php?slug=' . $slug . '#answers');
    } elseif ($_POST['action'] === 'comment_delete') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $own = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
        $own->execute([$commentId]);
        $ownerId = $own->fetchColumn();
        if ($ownerId !== false && ((int) $ownerId === (int) $user['id'] || in_array($user['role'], ['admin', 'moderator'], true))) {
            $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$commentId]);
        }
        redirect('/question.php?slug=' . $slug . '#answers');
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
                award_rep((int) $answerAuthorId, 15, 'answer_accepted', 'answer', $answerId);
                // Any bounty on the question transfers to the accepted answerer.
                if ((int) $question['bounty_points'] > 0) {
                    award_rep((int) $answerAuthorId, (int) $question['bounty_points'], 'bounty_awarded', 'question', (int) $question['id']);
                    $pdo->prepare('UPDATE questions SET bounty_points = 0, bounty_expires_at = NULL WHERE id = ?')->execute([$question['id']]);
                }
                check_badges_for_user((int) $answerAuthorId);
            }
        }
        redirect('/question.php?slug=' . $slug . '#answers');
    } elseif ($_POST['action'] === 'close' && in_array($user['role'], ['admin', 'moderator'], true)) {
        $reason = trim($_POST['reason'] ?? '') ?: 'Closed by a moderator';
        $pdo->prepare('UPDATE questions SET status = "closed", closed_reason = ?, closed_by = ? WHERE id = ?')
            ->execute([$reason, $user['id'], $question['id']]);
        redirect('/question.php?slug=' . $slug);
    } elseif ($_POST['action'] === 'reopen' && in_array($user['role'], ['admin', 'moderator'], true)) {
        $pdo->prepare('UPDATE questions SET status = "open", closed_reason = NULL, closed_by = NULL WHERE id = ?')
            ->execute([$question['id']]);
        redirect('/question.php?slug=' . $slug);
    } elseif ($_POST['action'] === 'add_bounty') {
        $points = (int) ($_POST['points'] ?? 0);
        if ($user['id'] === (int) $question['author_id'] && in_array($points, [25, 50, 100], true)
            && (int) $question['bounty_points'] === 0 && (int) $user['reputation'] > $points) {
            award_rep((int) $user['id'], -$points, 'bounty_offered', 'question', (int) $question['id']);
            $pdo->prepare('UPDATE questions SET bounty_points = ?, bounty_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?')->execute([$points, $question['id']]);
            flash_set('success', "Bounty of {$points} reputation added. It goes to whoever's answer you accept, or is auto-awarded to the top answer after 7 days.");
        }
        redirect('/question.php?slug=' . $slug);
    } elseif ($_POST['action'] === 'poll_vote') {
        $optionId = (int) ($_POST['option_id'] ?? 0);
        $check = $pdo->prepare('SELECT question_id FROM poll_options WHERE id = ?');
        $check->execute([$optionId]);
        if ((int) $check->fetchColumn() === (int) $question['id']) {
            $pdo->prepare('INSERT INTO poll_votes (option_id, user_id, question_id) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE option_id = VALUES(option_id)')
                ->execute([$optionId, $user['id'], $question['id']]);
        }
        redirect('/question.php?slug=' . $slug);
    } elseif ($_POST['action'] === 'suggest_edit') {
        $targetType = $_POST['target_type'] === 'answer' ? 'answer' : 'question';
        $targetId = (int) $_POST['target_id'];
        $proposedTitle = $targetType === 'question' ? trim($_POST['title'] ?? '') : null;
        $proposedBody = trim($_POST['body'] ?? '');
        $summary = mb_substr(trim($_POST['edit_summary'] ?? ''), 0, 300);
        if (mb_strlen($proposedBody) < 10) {
            $errors[] = 'Edit body must be at least 10 characters.';
        } else {
            $pdo->prepare('INSERT INTO suggested_edits (target_type, target_id, proposer_id, proposed_title, proposed_body, edit_summary) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$targetType, $targetId, $user['id'], $proposedTitle ?: null, $proposedBody, $summary ?: null]);
            flash_set('success', 'Edit suggestion submitted for review.');
            redirect('/question.php?slug=' . $slug);
        }
    } elseif ($_POST['action'] === 'edit_question') {
        if ($user['id'] === (int) $question['author_id'] || in_array($user['role'], ['admin', 'moderator'], true)) {
            $newTitle = trim($_POST['title'] ?? '');
            $newBody = trim($_POST['body'] ?? '');
            if (mb_strlen($newTitle) < 10 || mb_strlen($newBody) < 20) {
                $errors[] = 'Title must be 10+ characters and body 20+ characters.';
            } else {
                $pdo->prepare('INSERT INTO question_revisions (question_id, editor_id, prior_title, prior_body) VALUES (?, ?, ?, ?)')
                    ->execute([$question['id'], $user['id'], $question['title'], $question['body']]);
                $pdo->prepare('UPDATE questions SET title = ?, body = ?, last_edited_at = NOW() WHERE id = ?')
                    ->execute([$newTitle, $newBody, $question['id']]);
                redirect('/question.php?slug=' . $slug);
            }
        }
    } elseif ($_POST['action'] === 'edit_answer') {
        $answerId = (int) $_POST['answer_id'];
        $check = $pdo->prepare('SELECT user_id, body FROM answers WHERE id = ?');
        $check->execute([$answerId]);
        $target = $check->fetch();
        if ($target && ($user['id'] === (int) $target['user_id'] || in_array($user['role'], ['admin', 'moderator'], true))) {
            $newBody = trim($_POST['body'] ?? '');
            if (mb_strlen($newBody) < 10) {
                $errors[] = 'Answer must be at least 10 characters.';
            } else {
                $pdo->prepare('INSERT INTO answer_revisions (answer_id, editor_id, prior_body) VALUES (?, ?, ?)')
                    ->execute([$answerId, $user['id'], $target['body']]);
                $pdo->prepare('UPDATE answers SET body = ?, last_edited_at = NOW() WHERE id = ?')
                    ->execute([$newBody, $answerId]);
                redirect('/question.php?slug=' . $slug . '#answers');
            }
        }
    }
}

$related = $pdo->prepare(
    'SELECT title, slug, answer_count FROM questions
     WHERE MATCH(title, body) AGAINST (? IN NATURAL LANGUAGE MODE) AND id != ?
     LIMIT 6'
);
$related->execute([$question['title'], $question['id']]);
$related = $related->fetchAll();

$answerSort = $_GET['answers'] ?? 'votes';
$answerOrder = match ($answerSort) {
    'newest' => 'a.is_accepted DESC, a.created_at DESC',
    'oldest' => 'a.is_accepted DESC, a.created_at ASC',
    default => 'a.is_accepted DESC, a.vote_score DESC, a.created_at ASC',
};
$answers = $pdo->prepare(
    'SELECT a.*, u.username, u.id AS author_id, u.avatar
     FROM answers a JOIN users u ON u.id = a.user_id
     WHERE a.question_id = ?
     ORDER BY ' . $answerOrder
);
$answers->execute([$question['id']]);
$answers = $answers->fetchAll();

// Poll attached to this question (if any), with vote counts and the viewer's choice.
$pollStmt = $pdo->prepare(
    'SELECT po.id, po.label, COUNT(pv.user_id) AS votes
     FROM poll_options po LEFT JOIN poll_votes pv ON pv.option_id = po.id
     WHERE po.question_id = ? GROUP BY po.id ORDER BY po.sort_order, po.id'
);
$pollStmt->execute([$question['id']]);
$pollOptions = $pollStmt->fetchAll();
$pollTotalVotes = array_sum(array_column($pollOptions, 'votes'));
$myPollVote = null;
if ($pollOptions && current_user()) {
    $mv = $pdo->prepare('SELECT option_id FROM poll_votes WHERE question_id = ? AND user_id = ?');
    $mv->execute([$question['id'], current_user()['id']]);
    $myPollVote = $mv->fetchColumn() ?: null;
}

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
        $canEdit = $user && (int) $c['user_id'] === (int) $user['id'];
        $canDelete = $user && ((int) $c['user_id'] === (int) $user['id'] || in_array($user['role'], ['admin', 'moderator'], true));
        echo '<div data-comment-id="' . (int) $c['id'] . '">';
        echo '<p class="text-sm text-slate-600" data-comment-view><span class="font-medium text-slate-800">' . e($c['username']) . '</span> '
            . e($c['body']) . ($c['edited_at'] ? ' <span class="text-xs text-slate-400">(edited)</span>' : '');
        if ($canEdit) {
            echo ' <button type="button" data-comment-edit-toggle class="text-xs text-indigo-600 hover:underline">Edit</button>';
        }
        if ($canDelete) {
            echo '<form method="post" class="inline" onsubmit="return confirm(\'Delete this comment?\')">'
                . '<input type="hidden" name="action" value="comment_delete">'
                . '<input type="hidden" name="comment_id" value="' . (int) $c['id'] . '">'
                . csrf_field()
                . '<button type="submit" class="text-xs text-red-600 hover:underline ml-1">Delete</button></form>';
        }
        if ($user && (int) $c['user_id'] !== (int) $user['id']) {
            render_report_form('comment', (int) $c['id'], $user);
        }
        echo '</p>';
        if ($canEdit) {
            echo '<form method="post" class="hidden flex gap-2 mt-1" data-comment-edit-form>'
                . '<input type="hidden" name="action" value="comment_edit">'
                . '<input type="hidden" name="comment_id" value="' . (int) $c['id'] . '">'
                . csrf_field()
                . '<input type="text" name="body" maxlength="600" value="' . e($c['body']) . '" class="flex-1 text-sm border rounded px-2 py-1">'
                . '<button type="submit" class="text-xs text-indigo-600 hover:underline">Save</button>'
                . '<button type="button" data-comment-edit-toggle class="text-xs text-slate-500 hover:underline">Cancel</button></form>';
        }
        echo '</div>';
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

$canEditQuestion = $user && ($user['id'] === (int) $question['author_id'] || in_array($user['role'], ['admin', 'moderator'], true));
$isSaved = false;
$isWatching = false;
$myCollections = [];
if ($user) {
    $isWatching = is_following($user['id'], 'question', (int) $question['id']);
    $savedStmt = $pdo->prepare('SELECT 1 FROM saved_questions WHERE user_id = ? AND question_id = ?');
    $savedStmt->execute([$user['id'], $question['id']]);
    $isSaved = (bool) $savedStmt->fetchColumn();

    $collStmt = $pdo->prepare('SELECT slug, name FROM collections WHERE user_id = ? ORDER BY created_at DESC');
    $collStmt->execute([$user['id']]);
    $myCollections = $collStmt->fetchAll();
}

$pageTitle = $question['title'] . ' — ' . SITE_NAME;
$ogImage = SITE_URL . '/og_image?slug=' . urlencode($question['slug']);
$pageDescription = mb_substr(strip_tags($question['body']), 0, 160);
require __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'QAPage',
    'mainEntity' => [
        '@type' => 'Question',
        'name' => $question['title'],
        'text' => strip_tags($question['body']),
        'answerCount' => count($answers),
        'upvoteCount' => (int) $question['vote_score'],
        'dateCreated' => date('c', strtotime($question['created_at'])),
        'author' => ['@type' => 'Person', 'name' => $question['username']],
        'acceptedAnswer' => (function () use ($answers) {
            foreach ($answers as $a) {
                if ($a['is_accepted']) {
                    return ['@type' => 'Answer', 'text' => strip_tags($a['body']), 'upvoteCount' => (int) $a['vote_score'],
                            'author' => ['@type' => 'Person', 'name' => $a['username']]];
                }
            }
            return null;
        })(),
        'suggestedAnswer' => array_map(fn($a) => ['@type' => 'Answer', 'text' => strip_tags($a['body']), 'upvoteCount' => (int) $a['vote_score'],
            'author' => ['@type' => 'Person', 'name' => $a['username']]], array_filter($answers, fn($a) => !$a['is_accepted'])),
    ],
], JSON_UNESCAPED_SLASHES) ?></script>
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<link rel="stylesheet" href="/assets/vendor/prism.css">
<?php
$qCategory = null;
if (!empty($question['category_id'])) {
    $catStmt = $pdo->prepare('SELECT name, slug FROM categories WHERE id = ?');
    $catStmt->execute([$question['category_id']]);
    $qCategory = $catStmt->fetch() ?: null;
}
$breadcrumbs = [['Home', SITE_URL . '/']];
if ($qCategory) $breadcrumbs[] = [$qCategory['name'], SITE_URL . '/c/' . $qCategory['slug']];
$breadcrumbs[] = [$question['title'], SITE_URL . '/q/' . $question['slug']];
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn($b, $i) => [
        '@type' => 'ListItem', 'position' => $i + 1, 'name' => $b[0], 'item' => $b[1],
    ], $breadcrumbs, array_keys($breadcrumbs)),
], JSON_UNESCAPED_SLASHES) ?></script>

<nav aria-label="Breadcrumb" class="flex items-center justify-between gap-3 mb-3 text-sm">
  <ol class="flex items-center gap-1.5 text-slate-500 min-w-0 overflow-hidden">
    <li><a href="/" class="hover:underline">Home</a></li>
    <?php if ($qCategory): ?>
      <li aria-hidden="true">›</li>
      <li><a href="/c/<?= e($qCategory['slug']) ?>" class="hover:underline"><?= e($qCategory['name']) ?></a></li>
    <?php endif; ?>
    <li aria-hidden="true">›</li>
    <li class="truncate text-slate-700" aria-current="page"><?= e(mb_substr($question['title'], 0, 60)) ?><?= mb_strlen($question['title']) > 60 ? '…' : '' ?></li>
  </ol>
  <?php
  require_once __DIR__ . '/includes/short_links.php';
  $shareUrl = short_url_for('/q/' . $question['slug'], $user['id'] ?? null);
  ?>
  <button type="button" id="q-share" class="shrink-0 text-xs px-3 py-1.5 rounded-full border bg-white hover:bg-slate-50"
          data-title="<?= e($question['title']) ?>" data-url="<?= e($shareUrl) ?>">🔗 Share</button>
</nav>
<script>
document.getElementById('q-share').addEventListener('click', function () {
  var btn = this, data = { title: btn.dataset.title, url: btn.dataset.url };
  if (navigator.share) { navigator.share(data).catch(function () {}); return; }
  navigator.clipboard.writeText(data.url).then(function () {
    btn.textContent = '✓ Link copied';
    setTimeout(function () { btn.textContent = '🔗 Share'; }, 2000);
  });
});
</script>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
<div class="lg:col-span-3">
<div class="bg-white border rounded-lg p-6">
  <div class="flex gap-4">
    <div class="flex flex-col items-center gap-1 text-sm shrink-0">
      <button data-vote="1" data-type="question" data-id="<?= $question['id'] ?>" class="p-1.5 rounded hover:bg-slate-100" title="Upvote" aria-label="Upvote question">▲</button>
      <span data-score="question-<?= $question['id'] ?>" class="font-semibold"><?= (int) $question['vote_score'] ?></span>
      <button data-vote="-1" data-type="question" data-id="<?= $question['id'] ?>" class="p-1.5 rounded hover:bg-slate-100" title="Downvote" aria-label="Downvote question">▼</button>
      <?php if ($user): ?>
        <button data-save-question data-id="<?= $question['id'] ?>" class="text-xs mt-2 hover:underline"><?= $isSaved ? '★ Saved' : '☆ Save' ?></button>
        <?php if ((int) $question['author_id'] !== (int) $user['id']): ?>
          <button data-follow data-type="question" data-id="<?= $question['id'] ?>" class="text-xs mt-1 px-2 py-0.5 rounded <?= $isWatching ? 'bg-indigo-600 text-white' : 'bg-slate-50 border' ?>" title="Get notified about new answers"><?= $isWatching ? 'Following' : 'Follow' ?></button>
        <?php endif; ?>
        <details class="text-xs mt-1">
          <summary class="cursor-pointer hover:underline list-none text-indigo-600">+ Collection</summary>
          <div class="absolute z-10 mt-1 bg-white dark:bg-slate-800 border rounded-lg shadow-lg p-2 w-48 text-left">
            <?php foreach ($myCollections as $c): ?>
              <form method="post" action="/u/<?= rawurlencode($user['username']) ?>/<?= e($c['slug']) ?>" class="mb-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                <button type="submit" class="text-xs text-left w-full hover:bg-slate-100 dark:hover:bg-slate-700 rounded px-1.5 py-1 truncate"><?= e($c['name']) ?></button>
              </form>
            <?php endforeach; ?>
            <?php if (!$myCollections): ?><p class="text-xs text-slate-500 px-1.5 py-1">No collections yet.</p><?php endif; ?>
            <a href="/collections" class="block text-xs text-indigo-600 hover:underline px-1.5 py-1 border-t mt-1 pt-1">+ New collection</a>
          </div>
        </details>
      <?php endif; ?>
    </div>
    <div class="flex-1">
      <?php if (!empty($questionGroup)): ?>
        <a href="/g/<?= e($questionGroup['slug']) ?>" class="text-xs text-indigo-600 hover:underline"><?= $questionGroup['is_private'] ? '🔒 ' : '' ?><?= e($questionGroup['name']) ?></a>
      <?php endif; ?>
      <h1 class="text-2xl font-bold"><?= e($question['title']) ?>
        <?php if ((int) $question['bounty_points'] > 0):
          $bountyDaysLeft = $question['bounty_expires_at'] ? max(0, (int) ceil((strtotime($question['bounty_expires_at']) - time()) / 86400)) : null;
        ?>
          <span class="align-middle text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded" title="Bounty: extra reputation for the accepted answer">
            +<?= (int) $question['bounty_points'] ?> bounty<?= $bountyDaysLeft !== null ? ' · ends in ' . $bountyDaysLeft . 'd' : '' ?>
          </span>
        <?php endif; ?>
      </h1>
      <?php if ($question['status'] === 'closed'): ?>
        <div class="mt-2 rounded border border-amber-300 bg-amber-50 text-amber-800 px-3 py-2 text-sm">
          🔒 This question is closed<?= $question['closed_reason'] ? ': ' . e($question['closed_reason']) : '.' ?> New answers are disabled.
        </div>
      <?php endif; ?>
      <div class="mt-1 text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-1">
        <span>asked by <a href="/profile?u=<?= (int) $question['author_id'] ?>" class="text-indigo-600 hover:underline"><?= e($question['username']) ?></a></span>
        <span><?= time_ago($question['created_at']) ?></span>
        <span><?= (int) $question['view_count'] ?> views</span>
        <?php if ($question['last_edited_at']): ?>
          <span>edited <?= time_ago($question['last_edited_at']) ?> &middot; <a href="/revisions?type=question&id=<?= $question['id'] ?>" class="text-indigo-600 hover:underline">history</a></span>
        <?php endif; ?>
        <?php if ($question['location_city'] || $question['location_country']): ?>
          <span>📍 <?= e(trim(($question['location_city'] ?? '') . ', ' . ($question['location_country'] ?? ''), ', ')) ?></span>
        <?php endif; ?>
      </div>
      <div class="mt-4 prose prose-slate max-w-none"><?= render_markdown($question['body']) ?></div>
      <?php if ($tags): ?>
        <div class="mt-4 flex flex-wrap gap-1 items-center">
          <?php foreach ($tags as $t): ?>
            <a href="/tag/<?= e($t['slug']) ?>" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-0.5 rounded"><?= e($t['name']) ?></a>
            <?php if ($user): ?>
              <?php $followingTag = is_following($user['id'], 'tag', $t['id']); ?>
              <button data-follow data-type="tag" data-id="<?= $t['id'] ?>" class="text-xs px-2 py-0.5 rounded <?= $followingTag ? 'bg-indigo-600 text-white' : 'bg-slate-50 border' ?>"><?= $followingTag ? 'Following' : '+ Follow' ?></button>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($pollOptions): ?>
        <div class="mt-4 border rounded-lg p-4 bg-slate-50 dark:bg-slate-800">
          <h3 class="text-sm font-semibold mb-2">📊 Poll <span class="font-normal text-slate-500">(<?= (int) $pollTotalVotes ?> vote<?= $pollTotalVotes === 1 ? '' : 's' ?>)</span></h3>
          <div class="space-y-2">
            <?php foreach ($pollOptions as $opt): $pct = $pollTotalVotes ? round($opt['votes'] / $pollTotalVotes * 100) : 0; ?>
              <form method="post" class="flex items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="poll_vote">
                <input type="hidden" name="option_id" value="<?= (int) $opt['id'] ?>">
                <button type="submit" <?= !$user ? 'disabled title="Log in to vote"' : '' ?> class="shrink-0 w-4 h-4 rounded-full border-2 <?= (int) $myPollVote === (int) $opt['id'] ? 'bg-indigo-600 border-indigo-600' : 'border-slate-400 hover:border-indigo-500' ?>" aria-label="Vote for <?= e($opt['label']) ?>"></button>
                <div class="flex-1">
                  <div class="flex justify-between text-sm"><span><?= e($opt['label']) ?></span><span class="text-slate-500"><?= $pct ?>%</span></div>
                  <div class="h-1.5 bg-slate-200 dark:bg-slate-700 rounded overflow-hidden"><div class="h-full bg-indigo-500" style="width:<?= $pct ?>%"></div></div>
                </div>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="mt-4 flex flex-wrap items-center gap-3 text-xs">
        <span class="text-slate-500">Share:</span>
        <?php $shareUrl = urlencode(SITE_URL . '/q/' . $question['slug']); $shareTitle = urlencode($question['title']); ?>
        <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">X/Twitter</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Facebook</a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">LinkedIn</a>
        <a href="https://wa.me/?text=<?= $shareTitle ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">WhatsApp</a>
        <button type="button" data-copy-link="<?= e(SITE_URL . '/q/' . $question['slug']) ?>" class="text-indigo-600 hover:underline">Copy link</button>
      </div>

      <?php if ($user && $user['id'] === (int) $question['author_id'] && (int) $question['bounty_points'] === 0 && $question['status'] !== 'closed' && (int) $user['reputation'] > 25): ?>
        <details class="mt-3">
          <summary class="text-xs text-amber-700 hover:underline cursor-pointer">Add a bounty (spend reputation to attract answers)</summary>
          <form method="post" class="mt-2 flex items-center gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_bounty">
            <select name="points" class="border rounded px-2 py-1 text-sm">
              <?php foreach ([25, 50, 100] as $b): if ((int) $user['reputation'] > $b): ?><option value="<?= $b ?>">+<?= $b ?></option><?php endif; endforeach; ?>
            </select>
            <button type="submit" class="text-sm bg-amber-500 hover:bg-amber-400 text-white px-3 py-1.5 rounded">Offer bounty</button>
          </form>
        </details>
      <?php endif; ?>

      <?php if ($user && in_array($user['role'], ['admin', 'moderator'], true)): ?>
        <details class="mt-3">
          <summary class="text-xs text-slate-500 hover:underline cursor-pointer">Moderate</summary>
          <?php if ($question['status'] !== 'closed'): ?>
            <form method="post" class="mt-2 flex items-center gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="close">
              <input type="text" name="reason" placeholder="Reason (e.g. duplicate, off-topic)" class="border rounded px-2 py-1 text-sm flex-1">
              <button type="submit" class="text-sm bg-slate-600 hover:bg-slate-500 text-white px-3 py-1.5 rounded">Close question</button>
            </form>
          <?php else: ?>
            <form method="post" class="mt-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="reopen">
              <button type="submit" class="text-sm bg-slate-600 hover:bg-slate-500 text-white px-3 py-1.5 rounded">Reopen question</button>
            </form>
          <?php endif; ?>
        </details>
      <?php endif; ?>

      <?php if ($canEditQuestion): ?>
        <details class="mt-3">
          <summary class="text-xs text-indigo-600 hover:underline cursor-pointer">Edit</summary>
          <form method="post" class="mt-2 space-y-2">
            <input type="hidden" name="action" value="edit_question">
            <?= csrf_field() ?>
            <input type="text" name="title" required maxlength="200" value="<?= e($question['title']) ?>" class="w-full border rounded px-3 py-2 text-sm">
            <textarea name="body" required rows="6" class="w-full border rounded px-3 py-2 text-sm"><?= e($question['body']) ?></textarea>
            <button type="submit" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Save edit</button>
          </form>
        </details>
      <?php elseif ($user): ?>
        <details class="mt-3">
          <summary class="text-xs text-indigo-600 hover:underline cursor-pointer">Suggest an edit</summary>
          <form method="post" class="mt-2 space-y-2">
            <input type="hidden" name="action" value="suggest_edit">
            <input type="hidden" name="target_type" value="question">
            <input type="hidden" name="target_id" value="<?= (int) $question['id'] ?>">
            <?= csrf_field() ?>
            <input type="text" name="title" maxlength="200" value="<?= e($question['title']) ?>" class="w-full border rounded px-3 py-2 text-sm">
            <textarea name="body" required rows="6" class="w-full border rounded px-3 py-2 text-sm"><?= e($question['body']) ?></textarea>
            <input type="text" name="edit_summary" maxlength="300" placeholder="What did you change and why? (optional)" class="w-full border rounded px-3 py-2 text-sm">
            <button type="submit" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Submit suggestion</button>
          </form>
        </details>
      <?php endif; ?>
      <?php if ($user && (int) $question['author_id'] !== (int) $user['id']): ?>
        <div class="mt-1"><?php render_report_form('question', (int) $question['id'], $user); ?></div>
      <?php endif; ?>
      <?php render_comments($pdo, $commentStmt, 'question', $question['id'], $user); ?>
    </div>
  </div>
</div>

<div id="answers" class="mt-8">
  <div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-semibold"><?= count($answers) ?> Answer<?= count($answers) === 1 ? '' : 's' ?></h2>
    <?php if (count($answers) > 1): ?>
      <div class="flex gap-1 text-xs">
        <?php foreach (['votes' => 'Top', 'newest' => 'Newest', 'oldest' => 'Oldest'] as $key => $label): ?>
          <a href="/q/<?= e($question['slug']) ?>?answers=<?= $key ?>#answers" class="px-2 py-1 rounded <?= $answerSort === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="space-y-4">
    <?php foreach ($answers as $a): ?>
      <div class="bg-white border rounded-lg p-6 <?= $a['is_accepted'] ? 'border-green-400' : '' ?>">
        <div class="flex gap-4">
          <div class="flex flex-col items-center gap-1 text-sm shrink-0">
            <button data-vote="1" data-type="answer" data-id="<?= $a['id'] ?>" class="p-1.5 rounded hover:bg-slate-100" aria-label="Upvote answer">▲</button>
            <span data-score="answer-<?= $a['id'] ?>" class="font-semibold"><?= (int) $a['vote_score'] ?></span>
            <button data-vote="-1" data-type="answer" data-id="<?= $a['id'] ?>" class="p-1.5 rounded hover:bg-slate-100" aria-label="Downvote answer">▼</button>
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
            <div class="prose prose-slate max-w-none"><?= render_markdown($a['body']) ?></div>
            <div class="mt-3 text-xs text-slate-500">
              answered by <a href="/profile?u=<?= (int) $a['author_id'] ?>" class="text-indigo-600 hover:underline"><?= e($a['username']) ?></a>
              &middot; <?= time_ago($a['created_at']) ?>
              <?php if ($a['last_edited_at']): ?>
                &middot; edited <?= time_ago($a['last_edited_at']) ?> &middot; <a href="/revisions?type=answer&id=<?= $a['id'] ?>" class="text-indigo-600 hover:underline">history</a>
              <?php endif; ?>
            </div>
            <?php if ($user && ($user['id'] === (int) $a['author_id'] || in_array($user['role'], ['admin', 'moderator'], true))): ?>
              <details class="mt-2">
                <summary class="text-xs text-indigo-600 hover:underline cursor-pointer">Edit</summary>
                <form method="post" class="mt-2 space-y-2">
                  <input type="hidden" name="action" value="edit_answer">
                  <input type="hidden" name="answer_id" value="<?= $a['id'] ?>">
                  <?= csrf_field() ?>
                  <textarea name="body" required rows="5" class="w-full border rounded px-3 py-2 text-sm"><?= e($a['body']) ?></textarea>
                  <button type="submit" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Save edit</button>
                </form>
              </details>
            <?php elseif ($user): ?>
              <details class="mt-2">
                <summary class="text-xs text-indigo-600 hover:underline cursor-pointer">Suggest an edit</summary>
                <form method="post" class="mt-2 space-y-2">
                  <input type="hidden" name="action" value="suggest_edit">
                  <input type="hidden" name="target_type" value="answer">
                  <input type="hidden" name="target_id" value="<?= (int) $a['id'] ?>">
                  <?= csrf_field() ?>
                  <textarea name="body" required rows="5" class="w-full border rounded px-3 py-2 text-sm"><?= e($a['body']) ?></textarea>
                  <input type="text" name="edit_summary" maxlength="300" placeholder="What did you change and why? (optional)" class="w-full border rounded px-3 py-2 text-sm">
                  <button type="submit" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Submit suggestion</button>
                </form>
              </details>
            <?php endif; ?>
            <?php if ($user && (int) $a['user_id'] !== (int) $user['id']): ?>
              <div class="mt-1"><?php render_report_form('answer', (int) $a['id'], $user); ?></div>
            <?php endif; ?>
            <?php render_comments($pdo, $commentStmt, 'answer', $a['id'], $user); ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-6 bg-white border rounded-lg p-6">
    <h3 class="font-semibold mb-3">Your answer</h3>
    <?php if ($question['status'] === 'closed'): ?>
      <p class="text-sm text-slate-600">This question is closed and no longer accepts new answers.</p>
    <?php elseif ($user): ?>
      <form method="post" class="space-y-3">
        <input type="hidden" name="action" value="answer">
        <?= csrf_field() ?>
        <div data-markdown-editor>
          <?php $textareaId = 'answer-body'; require __DIR__ . '/includes/editor_toolbar.php'; ?>
          <textarea name="body" id="answer-body" rows="6" required class="w-full border border-t-0 rounded-b px-3 py-2" placeholder="Share your solution... Markdown supported."></textarea>
          <div class="mt-2">
            <p class="text-xs text-slate-500 mb-1">Preview</p>
            <div data-md-preview class="prose prose-slate max-w-none border rounded p-3 bg-slate-50 min-h-[3rem] text-sm"></div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Post answer</button>
          <span id="answer-draft-status" class="text-xs text-slate-400" aria-live="polite"></span>
        </div>
      </form>
      <script>
      (function () {
        // Local draft autosave: a long answer must survive an accidental
        // navigation, refresh or crash. Kept per-question in localStorage and
        // cleared on submit (the posted answer is now the source of truth).
        var ta = document.getElementById('answer-body');
        var form = ta && ta.closest('form');
        var status = document.getElementById('answer-draft-status');
        if (!ta || !form) return;
        var key = 'answer_draft_<?= (int) $question['id'] ?>';
        var timer = null;

        try {
          var saved = localStorage.getItem(key);
          if (saved && !ta.value) {
            ta.value = saved;
            status.textContent = 'Restored your unsaved draft.';
            ta.dispatchEvent(new Event('input', { bubbles: true })); // refresh preview
          }
        } catch (e) { return; }

        ta.addEventListener('input', function () {
          clearTimeout(timer);
          timer = setTimeout(function () {
            try {
              if (ta.value.trim()) {
                localStorage.setItem(key, ta.value);
                status.textContent = 'Draft saved locally · ' + new Date().toLocaleTimeString();
              } else {
                localStorage.removeItem(key);
                status.textContent = '';
              }
            } catch (e) { /* storage full or blocked — typing still works */ }
          }, 800);
        });

        form.addEventListener('submit', function () {
          try { localStorage.removeItem(key); } catch (e) {}
        });
      })();
      </script>
    <?php else: ?>
      <p class="text-sm text-slate-600"><a href="/login" class="text-indigo-600 hover:underline">Log in</a> to post an answer.</p>
    <?php endif; ?>
  </div>
</div>
</div>

<div class="lg:col-span-1">
  <?php if ($related): ?>
    <div class="bg-white border rounded-lg p-4">
      <h3 class="font-semibold text-sm mb-2">Related questions</h3>
      <div class="space-y-2">
        <?php foreach ($related as $r): ?>
          <a href="/question?slug=<?= e($r['slug']) ?>" class="block text-sm text-indigo-600 hover:underline"><?= e($r['title']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
</div>

<script src="/assets/dist/js/vote.js"></script>
<script src="/assets/dist/js/follow.js"></script>
<script src="/assets/dist/js/save_question.js"></script>
<script src="/assets/dist/js/markdown.js"></script>
<script src="/assets/vendor/prism.js"></script>
<script src="/assets/vendor/prism-php.js"></script>
<script src="/assets/vendor/prism-bash.js"></script>
<script src="/assets/vendor/prism-sql.js"></script>
<script src="/assets/vendor/prism-json.js"></script>
<script>if (window.Prism) Prism.highlightAll();</script>
<?php require_once __DIR__ . '/includes/ad_slots.php'; ?>
<?= ad_slot('question_footer') ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
