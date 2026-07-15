<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/diff.php';

$pdo = db();
$type = $_GET['type'] === 'answer' ? 'answer' : 'question';
$id = (int) ($_GET['id'] ?? 0);

if ($type === 'question') {
    $current = $pdo->prepare('SELECT title, body, slug FROM questions WHERE id = ?');
    $current->execute([$id]);
    $current = $current->fetch();
    $revisions = $pdo->prepare(
        'SELECT r.*, u.username FROM question_revisions r JOIN users u ON u.id = r.editor_id WHERE question_id = ? ORDER BY created_at ASC'
    );
    $backLink = $current ? '/question.php?slug=' . e($current['slug']) : '/questions.php';
} else {
    $current = $pdo->prepare('SELECT a.body, q.slug FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.id = ?');
    $current->execute([$id]);
    $current = $current->fetch();
    $revisions = $pdo->prepare(
        'SELECT r.*, u.username FROM answer_revisions r JOIN users u ON u.id = r.editor_id WHERE answer_id = ? ORDER BY created_at ASC'
    );
    $backLink = $current ? '/question.php?slug=' . e($current['slug']) . '#answers' : '/questions.php';
}

if (!$current) {
    http_response_code(404);
    exit('Not found.');
}

$revisions->execute([$id]);
$revisions = $revisions->fetchAll();

// Build the version chain oldest -> current: each entry's "prior_*" is the
// state *before* that edit, so the chain of bodies is prior[0], prior[1], ..., current.
$chain = [];
foreach ($revisions as $r) {
    $chain[] = [
        'title' => $r['prior_title'] ?? null,
        'body' => $r['prior_body'],
        'editor' => null, // this state existed before anyone in this list edited it
        'label' => 'Version from before ' . e($r['username']) . "'s edit, " . time_ago($r['created_at']),
    ];
}
$chain[] = [
    'title' => $current['title'] ?? null,
    'body' => $current['body'],
    'editor' => null,
    'label' => 'Current version',
];

$pageTitle = 'Edit history — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto space-y-4">
  <a href="<?= $backLink ?>" class="text-sm text-indigo-600 hover:underline">&larr; Back</a>
  <h1 class="text-xl font-semibold">Edit history</h1>
  <p class="text-sm text-slate-500">Showing what changed at each edit — <del class="bg-red-100 text-red-700 line-through px-0.5">removed</del> <ins class="bg-green-100 text-green-800 no-underline px-0.5">added</ins></p>

  <?php if (count($chain) === 1): ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="text-xs text-slate-500 mb-1">Current version — no edits yet</div>
      <?php if (!empty($chain[0]['title'])): ?><div class="font-medium mb-1"><?= e($chain[0]['title']) ?></div><?php endif; ?>
      <div class="prose prose-slate max-w-none text-sm whitespace-pre-wrap"><?= e($chain[0]['body']) ?></div>
    </div>
  <?php else: ?>
    <?php for ($k = 1; $k < count($chain); $k++): $prev = $chain[$k - 1]; $cur = $chain[$k]; ?>
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-slate-500 mb-2"><?= $cur['label'] ?></div>
        <?php if (isset($cur['title']) && $cur['title'] !== null && $cur['title'] !== $prev['title']): ?>
          <div class="font-medium mb-1"><?= text_diff((string) $prev['title'], (string) $cur['title']) ?></div>
        <?php elseif (isset($cur['title']) && $cur['title'] !== null): ?>
          <div class="font-medium mb-1"><?= e($cur['title']) ?></div>
        <?php endif; ?>
        <div class="prose prose-slate max-w-none text-sm whitespace-pre-wrap"><?= text_diff($prev['body'], $cur['body']) ?></div>
      </div>
    <?php endfor; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
