<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/captcha.php';

$user = current_user();
$types = ['bug' => 'Bug report', 'feature' => 'Feature request', 'ui' => 'UI / UX issue', 'performance' => 'Performance problem', 'other' => 'Other feedback'];
$severities = ['low', 'medium', 'high', 'critical'];

$sent = false;
$errors = [];
$old = ['type' => $_GET['type'] ?? 'bug', 'subject' => '', 'message' => '', 'email' => '', 'severity' => 'medium', 'page_url' => trim($_GET['page'] ?? '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = [
        'type' => $_POST['type'] ?? 'other',
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'severity' => $_POST['severity'] ?? 'medium',
        'page_url' => trim($_POST['page_url'] ?? ''),
    ];

    if (honeypot_tripped()) {
        $sent = true; // bot: fake success, store nothing
    } elseif (!rate_limit('feedback', 5, 600)) {
        $errors[] = 'Too many submissions. Please try again in a few minutes.';
    } elseif (!captcha_verify()) {
        $errors[] = 'Captcha verification failed. Please try again.';
    } else {
        if (!isset($types[$old['type']])) $old['type'] = 'other';
        if (!in_array($old['severity'], $severities, true)) $old['severity'] = 'medium';
        if (mb_strlen($old['subject']) < 5 || mb_strlen($old['subject']) > 200) {
            $errors[] = 'Subject must be between 5 and 200 characters.';
        }
        if (mb_strlen($old['message']) < 20) {
            $errors[] = 'Please describe the issue in at least 20 characters — steps to reproduce help a lot.';
        }
        if (!$user && $old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address (or leave it blank).';
        }
        // Only accept same-site page URLs; drop anything else silently.
        $pageUrl = $old['page_url'];
        if ($pageUrl !== '' && (!str_starts_with($pageUrl, '/') || str_starts_with($pageUrl, '//'))) {
            $pageUrl = '';
        }

        if (!$errors) {
            db()->prepare('INSERT INTO feedback (user_id, email, type, severity, subject, message, page_url, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $user['id'] ?? null,
                    $user['email'] ?? ($old['email'] !== '' ? $old['email'] : null),
                    $old['type'],
                    $old['type'] === 'bug' ? $old['severity'] : null,
                    $old['subject'],
                    $old['message'],
                    $pageUrl !== '' ? mb_substr($pageUrl, 0, 500) : null,
                    mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]);

            // Alert admins about critical bug reports; everything else waits in the queue.
            if ($old['type'] === 'bug' && $old['severity'] === 'critical') {
                try {
                    send_mail(SMTP_FROM, SITE_NAME, '[Critical bug report] ' . $old['subject'],
                        '<p><strong>From:</strong> ' . e($user['username'] ?? ($old['email'] ?: 'Anonymous')) . '</p>'
                        . ($pageUrl !== '' ? '<p><strong>Page:</strong> ' . e($pageUrl) . '</p>' : '')
                        . '<p>' . nl2br(e($old['message'])) . '</p>');
                } catch (Throwable $e) {
                    // never fail the submission because mail is down
                }
            }
            $sent = true;
        }
    }
}

$myFeedback = [];
if ($user) {
    $stmt = db()->prepare('SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([$user['id']]);
    $myFeedback = $stmt->fetchAll();
}

$statusStyles = [
    'new' => 'bg-slate-100 text-slate-700',
    'in_review' => 'bg-amber-100 text-amber-800',
    'planned' => 'bg-indigo-100 text-indigo-800',
    'resolved' => 'bg-green-100 text-green-800',
    'dismissed' => 'bg-slate-100 text-slate-500',
];

$pageTitle = 'Feedback & bug reports — ' . SITE_NAME;
$pageDescription = 'Report a bug or share feedback to help us improve ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto space-y-6">
  <div class="bg-white border rounded-lg p-6">
    <h1 class="text-xl font-semibold mb-1">Feedback &amp; bug reports</h1>
    <p class="text-sm text-slate-500 mb-4">Found a bug or have an idea? Tell us — every report is read. Feature ideas may also appear on the <a href="/roadmap" class="text-indigo-600 hover:underline">roadmap</a>.</p>

    <?php if ($sent): ?>
      <div class="rounded border border-green-300 bg-green-50 text-green-800 px-4 py-3 text-sm">
        Thank you! Your report has been submitted<?= $user ? ' — you can track its status below' : '' ?>.
      </div>
      <a href="/feedback" class="inline-block mt-3 text-sm text-indigo-600 hover:underline">Submit another</a>
    <?php else: ?>
      <?php foreach ($errors as $err): ?>
        <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
      <?php endforeach; ?>
      <form method="post" class="space-y-3">
        <?= csrf_field() . honeypot_field() ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="fb_type" class="block text-sm font-medium mb-1">Type</label>
            <select id="fb_type" name="type" class="w-full border rounded px-3 py-2 text-sm"
                    onchange="document.getElementById('fb_severity_wrap').classList.toggle('hidden', this.value !== 'bug')">
              <?php foreach ($types as $val => $label): ?>
                <option value="<?= $val ?>" <?= $old['type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="fb_severity_wrap" class="<?= $old['type'] === 'bug' ? '' : 'hidden' ?>">
            <label for="fb_severity" class="block text-sm font-medium mb-1">Severity</label>
            <select id="fb_severity" name="severity" class="w-full border rounded px-3 py-2 text-sm">
              <?php foreach ($severities as $s): ?>
                <option value="<?= $s ?>" <?= $old['severity'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label for="fb_subject" class="block text-sm font-medium mb-1">Subject</label>
          <input id="fb_subject" type="text" name="subject" required minlength="5" maxlength="200" value="<?= e($old['subject']) ?>"
                 placeholder="Short summary of the issue or idea" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label for="fb_message" class="block text-sm font-medium mb-1">Details</label>
          <textarea id="fb_message" name="message" required minlength="20" rows="6"
                    placeholder="What happened? What did you expect? For bugs, steps to reproduce help a lot." class="w-full border rounded px-3 py-2 text-sm"><?= e($old['message']) ?></textarea>
        </div>
        <div>
          <label for="fb_page" class="block text-sm font-medium mb-1">Page where it happened <span class="text-slate-400 font-normal">(optional)</span></label>
          <input id="fb_page" type="text" name="page_url" value="<?= e($old['page_url']) ?>" placeholder="/questions/example"
                 class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <?php if (!$user): ?>
          <div>
            <label for="fb_email" class="block text-sm font-medium mb-1">Your email <span class="text-slate-400 font-normal">(optional, for follow-up)</span></label>
            <input id="fb_email" type="email" name="email" value="<?= e($old['email']) ?>" placeholder="you@example.com" class="w-full border rounded px-3 py-2 text-sm">
          </div>
        <?php endif; ?>
        <?= captcha_field('feedback') ?>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm font-medium">Submit feedback</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($myFeedback): ?>
    <div class="bg-white border rounded-lg divide-y">
      <div class="p-4 font-semibold text-sm">Your recent reports</div>
      <?php foreach ($myFeedback as $f): ?>
        <div class="p-4 text-sm">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-medium"><?= e($f['subject']) ?></span>
            <span class="text-xs px-2 py-0.5 rounded-full <?= $statusStyles[$f['status']] ?? 'bg-slate-100 text-slate-700' ?>"><?= ucwords(str_replace('_', ' ', $f['status'])) ?></span>
          </div>
          <div class="text-xs text-slate-500 mt-1"><?= e($types[$f['type']] ?? $f['type']) ?> · <?= time_ago($f['created_at']) ?></div>
          <?php if ($f['admin_note'] && in_array($f['status'], ['resolved', 'planned', 'dismissed'], true)): ?>
            <div class="mt-2 text-xs bg-slate-50 border rounded px-3 py-2 text-slate-600"><strong>Team response:</strong> <?= nl2br(e($f['admin_note'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
